<?php

namespace App\Http\Controllers\Api\Banking;

use App\Abstracts\Http\ApiController;
use App\Http\Requests\Banking\Receipt as Request;
use App\Http\Resources\Banking\Receipt as Resource;
use App\Models\Banking\Receipt;
use App\Services\Receipt\ReceiptProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;

class ReceiptsController extends ApiController
{
    protected $receiptProcessingService;

    public function __construct(ReceiptProcessingService $receiptProcessingService)
    {
        $this->receiptProcessingService = $receiptProcessingService;
    }

    /**
     * Display a listing of receipts.
     */
    public function index(HttpRequest $request): AnonymousResourceCollection
    {
        $limit = (int) $request->get('limit', setting('default.list_limit', 25));

        $receipts = Receipt::with(['transaction', 'category', 'media'])
            ->where('company_id', company_id())
            ->when($request->get('status'), function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->get('vendor'), function ($query, $vendor) {
                return $query->where('vendor_name', 'like', '%' . $vendor . '%');
            })
            ->when($request->get('amount_min'), function ($query, $amount) {
                return $query->where('amount', '>=', $amount);
            })
            ->when($request->get('amount_max'), function ($query, $amount) {
                return $query->where('amount', '<=', $amount);
            })
            ->when($request->get('date_from'), function ($query, $date) {
                return $query->whereDate('receipt_date', '>=', $date);
            })
            ->when($request->get('date_to'), function ($query, $date) {
                return $query->whereDate('receipt_date', '<=', $date);
            })
            ->when($request->get('category_id'), function ($query, $categoryId) {
                return $query->where('category_id', $categoryId);
            })
            ->when($request->get('matched'), function ($query, $matched) {
                if ($matched === 'true') {
                    return $query->whereNotNull('transaction_id');
                } elseif ($matched === 'false') {
                    return $query->whereNull('transaction_id');
                }
                return $query;
            })
            ->orderBy('created_at', 'desc')
            ->paginate($limit);

        return Resource::collection($receipts);
    }

    /**
     * Store a newly uploaded receipt.
     */
    public function store(HttpRequest $request): JsonResponse
    {
        $request->validate([
            'receipt' => 'required|file|mimes:jpg,jpeg,png,gif,pdf|max:10240', // 10MB max
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $receipt = $this->receiptProcessingService->processUploadedReceipt(
                $request->file('receipt'),
                company_id(),
                $request->get('description')
            );

            return response()->json([
                'success' => true,
                'message' => trans('messages.success.added', ['type' => trans_choice('general.receipts', 1)]),
                'data' => new Resource($receipt),
            ], 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => trans('messages.error.upload_failed'),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Display the specified receipt.
     */
    public function show(Receipt $receipt): Resource
    {
        $this->authorize('view', $receipt);

        return new Resource($receipt->load(['transaction', 'category', 'media']));
    }

    /**
     * Update the specified receipt.
     */
    public function update(Request $request, Receipt $receipt): JsonResponse
    {
        $this->authorize('update', $receipt);

        $receipt->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => trans('messages.success.updated', ['type' => trans_choice('general.receipts', 1)]),
            'data' => new Resource($receipt->fresh(['transaction', 'category', 'media'])),
        ]);
    }

    /**
     * Remove the specified receipt.
     */
    public function destroy(Receipt $receipt): JsonResponse
    {
        $this->authorize('delete', $receipt);

        try {
            $deleted = $this->receiptProcessingService->deleteReceipt($receipt);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => trans('messages.success.deleted', ['type' => trans_choice('general.receipts', 1)]),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => trans('messages.error.delete_failed'),
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => trans('messages.error.delete_failed'),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Retry processing a failed receipt.
     */
    public function retry(Receipt $receipt): JsonResponse
    {
        $this->authorize('update', $receipt);

        try {
            $processedReceipt = $this->receiptProcessingService->retryFailedReceipt($receipt);

            return response()->json([
                'success' => true,
                'message' => 'Receipt processing retried successfully',
                'data' => new Resource($processedReceipt->fresh(['transaction', 'category', 'media'])),
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry receipt processing',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Reprocess a receipt.
     */
    public function reprocess(Receipt $receipt): JsonResponse
    {
        $this->authorize('update', $receipt);

        try {
            $processedReceipt = $this->receiptProcessingService->reprocessReceipt($receipt);

            return response()->json([
                'success' => true,
                'message' => 'Receipt reprocessed successfully',
                'data' => new Resource($processedReceipt->fresh(['transaction', 'category', 'media'])),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reprocess receipt',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get receipt processing statistics.
     */
    public function stats(HttpRequest $request): JsonResponse
    {
        $period = $request->get('period', 'month'); // today, week, month, or null for all time
        
        $stats = $this->receiptProcessingService->getProcessingStats(company_id(), $period);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Download receipt file.
     */
    public function download(Receipt $receipt): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('view', $receipt);

        $media = $receipt->getMedia('receipt')->first();
        
        if (!$media) {
            abort(404, 'Receipt file not found');
        }

        return response()->download($media->getAbsolutePath(), $media->filename);
    }

    /**
     * Match receipt to a specific transaction.
     */
    public function matchToTransaction(HttpRequest $request, Receipt $receipt): JsonResponse
    {
        $this->authorize('update', $receipt);

        $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
        ]);

        try {
            $transaction = \App\Models\Banking\Transaction::findOrFail($request->transaction_id);
            
            // Check if transaction belongs to same company
            if ($transaction->company_id !== $receipt->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction does not belong to the same company',
                ], 422);
            }

            // Check if transaction already has a receipt
            if ($transaction->receipt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction already has a receipt attached',
                ], 422);
            }

            // Update receipt
            $receipt->update([
                'transaction_id' => $transaction->id,
                'status' => 'matched',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Receipt matched to transaction successfully',
                'data' => new Resource($receipt->fresh(['transaction', 'category', 'media'])),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to match receipt to transaction',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Unmatch receipt from transaction.
     */
    public function unmatch(Receipt $receipt): JsonResponse
    {
        $this->authorize('update', $receipt);

        try {
            $wasCreatedFromReceipt = $receipt->transaction && 
                                   $receipt->transaction->created_from === 'receipt_upload';

            if ($wasCreatedFromReceipt) {
                // Delete the transaction if it was created from this receipt
                $receipt->transaction->delete();
            }

            $receipt->update([
                'transaction_id' => null,
                'status' => 'processed',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Receipt unmatched successfully',
                'data' => new Resource($receipt->fresh(['transaction', 'category', 'media'])),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unmatch receipt',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
