<?php

namespace App\Services\Receipt;

use App\Models\Banking\Receipt;
use App\Models\Banking\Transaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceiptProcessingService
{
    protected $ocrService;
    protected $aiCategorizationService;
    protected $transactionMatchingService;

    public function __construct(
        OcrService $ocrService,
        AiCategorizationService $aiCategorizationService,
        TransactionMatchingService $transactionMatchingService
    ) {
        $this->ocrService = $ocrService;
        $this->aiCategorizationService = $aiCategorizationService;
        $this->transactionMatchingService = $transactionMatchingService;
    }

    /**
     * Process uploaded receipt file
     */
    public function processUploadedReceipt(UploadedFile $file, int $companyId, ?string $description = null): Receipt
    {
        DB::beginTransaction();

        try {
            // Create receipt record
            $receipt = $this->createReceiptRecord($file, $companyId, $description);

            // Attach file to receipt
            $this->attachFileToReceipt($receipt, $file);

            // Process receipt asynchronously or synchronously based on configuration
            if (config('receipt.async_processing', true)) {
                // Queue the processing job
                dispatch(new \App\Jobs\ProcessReceiptJob($receipt));
            } else {
                // Process immediately
                $this->processReceipt($receipt);
            }

            DB::commit();

            return $receipt;

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Receipt upload failed', [
                'company_id' => $companyId,
                'file_name' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process receipt (OCR, categorization, matching)
     */
    public function processReceipt(Receipt $receipt): Receipt
    {
        try {
            $receipt->update(['status' => 'processing']);

            // Step 1: OCR Processing
            Log::info('Starting OCR processing', ['receipt_id' => $receipt->id]);
            $extractedData = $this->ocrService->processReceipt($receipt);

            // Step 2: AI Categorization
            Log::info('Starting AI categorization', ['receipt_id' => $receipt->id]);
            $category = $this->aiCategorizationService->categorizeReceipt($receipt);

            // Step 3: Transaction Matching
            Log::info('Starting transaction matching', ['receipt_id' => $receipt->id]);
            $transaction = $this->transactionMatchingService->matchReceiptToTransaction($receipt);

            // Refresh receipt to get updated data
            $receipt->refresh();

            Log::info('Receipt processing completed', [
                'receipt_id' => $receipt->id,
                'vendor_name' => $receipt->vendor_name,
                'amount' => $receipt->amount,
                'category' => $category?->name,
                'transaction_id' => $transaction?->id,
                'status' => $receipt->status,
            ]);

            return $receipt;

        } catch (\Exception $e) {
            Log::error('Receipt processing failed', [
                'receipt_id' => $receipt->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $receipt->update(['status' => 'failed']);
            
            throw $e;
        }
    }

    /**
     * Create initial receipt record
     */
    protected function createReceiptRecord(UploadedFile $file, int $companyId, ?string $description): Receipt
    {
        return Receipt::create([
            'company_id' => $companyId,
            'description' => $description,
            'status' => 'pending',
            'created_from' => 'receipt_upload',
        ]);
    }

    /**
     * Attach file to receipt using mediable
     */
    protected function attachFileToReceipt(Receipt $receipt, UploadedFile $file): void
    {
        // Validate file type
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        $extension = strtolower($file->getClientOriginalExtension());
        
        if (!in_array($extension, $allowedTypes)) {
            throw new \InvalidArgumentException('Unsupported file type. Allowed: ' . implode(', ', $allowedTypes));
        }

        // Validate file size (max 10MB)
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new \InvalidArgumentException('File size too large. Maximum 10MB allowed.');
        }

        // Store the file
        $receipt->attachMedia($file, 'receipt');
    }

    /**
     * Retry failed receipt processing
     */
    public function retryFailedReceipt(Receipt $receipt): Receipt
    {
        if ($receipt->status !== 'failed') {
            throw new \InvalidArgumentException('Receipt is not in failed status');
        }

        Log::info('Retrying failed receipt processing', ['receipt_id' => $receipt->id]);

        return $this->processReceipt($receipt);
    }

    /**
     * Reprocess receipt (useful after updating OCR/AI services)
     */
    public function reprocessReceipt(Receipt $receipt): Receipt
    {
        Log::info('Reprocessing receipt', ['receipt_id' => $receipt->id]);

        // Reset receipt data
        $receipt->update([
            'vendor_name' => null,
            'amount' => null,
            'receipt_date' => null,
            'category_id' => null,
            'ocr_data' => null,
            'ai_category_confidence' => null,
            'status' => 'pending',
        ]);

        return $this->processReceipt($receipt);
    }

    /**
     * Handle new transaction creation to check for receipt matches
     */
    public function handleNewTransaction(Transaction $transaction): void
    {
        $this->transactionMatchingService->handleNewTransaction($transaction);
    }

    /**
     * Process unmatched receipts periodically
     */
    public function processUnmatchedReceipts(): void
    {
        Log::info('Processing unmatched receipts');
        $this->transactionMatchingService->processUnmatchedReceipts();
    }

    /**
     * Get receipt processing statistics
     */
    public function getProcessingStats(int $companyId, ?string $period = null): array
    {
        $query = Receipt::where('company_id', $companyId);

        if ($period) {
            switch ($period) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'week':
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'month':
                    $query->whereMonth('created_at', now()->month)
                          ->whereYear('created_at', now()->year);
                    break;
            }
        }

        $receipts = $query->get();

        return [
            'total' => $receipts->count(),
            'pending' => $receipts->where('status', 'pending')->count(),
            'processing' => $receipts->where('status', 'processing')->count(),
            'processed' => $receipts->where('status', 'processed')->count(),
            'matched' => $receipts->where('status', 'matched')->count(),
            'failed' => $receipts->where('status', 'failed')->count(),
            'total_amount' => $receipts->sum('amount'),
            'average_confidence' => $receipts->where('ai_category_confidence', '>', 0)->avg('ai_category_confidence'),
            'match_rate' => $receipts->count() > 0 ? 
                ($receipts->whereNotNull('transaction_id')->count() / $receipts->count()) * 100 : 0,
        ];
    }

    /**
     * Delete receipt and associated files
     */
    public function deleteReceipt(Receipt $receipt): bool
    {
        try {
            DB::beginTransaction();

            // If receipt is matched to a transaction created from receipt, delete the transaction
            if ($receipt->transaction_id && $receipt->transaction && 
                $receipt->transaction->created_from === 'receipt_upload') {
                $receipt->transaction->delete();
            } else if ($receipt->transaction_id) {
                // Just unlink from existing transaction
                $receipt->transaction->update(['receipt_id' => null]);
            }

            // Delete media files
            $receipt->detachMedia();

            // Delete receipt
            $receipt->delete();

            DB::commit();

            Log::info('Receipt deleted', ['receipt_id' => $receipt->id]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to delete receipt', [
                'receipt_id' => $receipt->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}