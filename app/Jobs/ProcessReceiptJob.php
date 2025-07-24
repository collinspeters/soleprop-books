<?php

namespace App\Jobs;

use App\Models\Banking\Receipt;
use App\Services\Receipt\ReceiptProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessReceiptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $receipt;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(Receipt $receipt)
    {
        $this->receipt = $receipt;
        $this->onQueue('receipts'); // Use dedicated queue for receipt processing
    }

    /**
     * Execute the job.
     */
    public function handle(ReceiptProcessingService $receiptProcessingService): void
    {
        try {
            Log::info('Processing receipt job started', [
                'receipt_id' => $this->receipt->id,
                'attempt' => $this->attempts(),
            ]);

            $receiptProcessingService->processReceipt($this->receipt);

            Log::info('Processing receipt job completed', [
                'receipt_id' => $this->receipt->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Processing receipt job failed', [
                'receipt_id' => $this->receipt->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // If this is the final attempt, mark receipt as failed
            if ($this->attempts() >= $this->tries) {
                $this->receipt->update(['status' => 'failed']);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Receipt processing job failed permanently', [
            'receipt_id' => $this->receipt->id,
            'error' => $exception->getMessage(),
        ]);

        $this->receipt->update(['status' => 'failed']);
    }
}
