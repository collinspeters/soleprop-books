<?php

namespace App\Listeners;

use App\Models\Banking\Transaction;
use App\Services\Receipt\ReceiptProcessingService;
use Illuminate\Support\Facades\Log;

class HandleTransactionCreated
{
    protected $receiptProcessingService;

    /**
     * Create the event listener.
     */
    public function __construct(ReceiptProcessingService $receiptProcessingService)
    {
        $this->receiptProcessingService = $receiptProcessingService;
    }

    /**
     * Handle the event.
     */
    public function handle($event): void
    {
        // Check if the event has a transaction
        $transaction = null;
        
        if (isset($event->transaction)) {
            $transaction = $event->transaction;
        } elseif (isset($event->model) && $event->model instanceof Transaction) {
            $transaction = $event->model;
        }

        if (!$transaction) {
            return;
        }

        try {
            // Only process expense transactions
            if ($transaction->type === 'expense') {
                Log::info('Checking for receipt matches for new transaction', [
                    'transaction_id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'description' => $transaction->description,
                ]);

                $this->receiptProcessingService->handleNewTransaction($transaction);
            }

        } catch (\Exception $e) {
            Log::error('Failed to process transaction for receipt matching', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
