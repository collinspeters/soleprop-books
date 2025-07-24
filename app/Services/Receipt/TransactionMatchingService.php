<?php

namespace App\Services\Receipt;

use App\Models\Banking\Receipt;
use App\Models\Banking\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TransactionMatchingService
{
    /**
     * Attempt to match receipt to existing transaction
     */
    public function matchReceiptToTransaction(Receipt $receipt): ?Transaction
    {
        if (!$receipt->amount || !$receipt->receipt_date) {
            return null;
        }

        // Look for exact matches first
        $exactMatch = $this->findExactMatch($receipt);
        if ($exactMatch) {
            return $this->attachReceiptToTransaction($receipt, $exactMatch);
        }

        // Look for close matches
        $closeMatch = $this->findCloseMatch($receipt);
        if ($closeMatch) {
            return $this->attachReceiptToTransaction($receipt, $closeMatch);
        }

        // No match found, create new transaction
        return $this->createTransactionFromReceipt($receipt);
    }

    /**
     * Find exact match based on amount, vendor, and date
     */
    protected function findExactMatch(Receipt $receipt): ?Transaction
    {
        $startDate = Carbon::parse($receipt->receipt_date)->subDays(5);
        $endDate = Carbon::parse($receipt->receipt_date)->addDays(5);

        return Transaction::where('company_id', $receipt->company_id)
            ->where('amount', $receipt->amount)
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->whereDoesntHave('receipt') // Only unmatched transactions
            ->first();
    }

    /**
     * Find close match with fuzzy matching
     */
    protected function findCloseMatch(Receipt $receipt): ?Transaction
    {
        $startDate = Carbon::parse($receipt->receipt_date)->subDays(5);
        $endDate = Carbon::parse($receipt->receipt_date)->addDays(5);

        $potentialMatches = Transaction::where('company_id', $receipt->company_id)
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->whereDoesntHave('receipt') // Only unmatched transactions
            ->get();

        $bestMatch = null;
        $bestScore = 0;

        foreach ($potentialMatches as $transaction) {
            $score = $this->calculateMatchScore($receipt, $transaction);
            
            if ($score > $bestScore && $score >= 0.7) { // Minimum 70% match
                $bestScore = $score;
                $bestMatch = $transaction;
            }
        }

        return $bestMatch;
    }

    /**
     * Calculate match score between receipt and transaction
     */
    protected function calculateMatchScore(Receipt $receipt, Transaction $transaction): float
    {
        $score = 0;
        $maxScore = 0;

        // Amount matching (40% weight)
        $maxScore += 0.4;
        $amountDiff = abs($receipt->amount - abs($transaction->amount));
        $amountScore = max(0, 1 - ($amountDiff / max($receipt->amount, abs($transaction->amount))));
        $score += $amountScore * 0.4;

        // Date matching (30% weight)
        $maxScore += 0.3;
        $receiptDate = Carbon::parse($receipt->receipt_date);
        $transactionDate = Carbon::parse($transaction->paid_at);
        $daysDiff = abs($receiptDate->diffInDays($transactionDate));
        $dateScore = max(0, 1 - ($daysDiff / 5)); // 5 days max difference
        $score += $dateScore * 0.3;

        // Vendor/Description matching (30% weight)
        $maxScore += 0.3;
        $vendorScore = $this->calculateTextSimilarity(
            $receipt->vendor_name ?? '',
            $transaction->description ?? ''
        );
        $score += $vendorScore * 0.3;

        return $maxScore > 0 ? $score / $maxScore : 0;
    }

    /**
     * Calculate text similarity between two strings
     */
    protected function calculateTextSimilarity(string $text1, string $text2): float
    {
        if (empty($text1) || empty($text2)) {
            return 0;
        }

        $text1 = strtolower(trim($text1));
        $text2 = strtolower(trim($text2));

        // Exact match
        if ($text1 === $text2) {
            return 1.0;
        }

        // Check if one contains the other
        if (strpos($text1, $text2) !== false || strpos($text2, $text1) !== false) {
            return 0.8;
        }

        // Calculate Levenshtein distance
        $distance = levenshtein($text1, $text2);
        $maxLength = max(strlen($text1), strlen($text2));
        
        if ($maxLength === 0) {
            return 1.0;
        }

        return max(0, 1 - ($distance / $maxLength));
    }

    /**
     * Attach receipt to transaction
     */
    protected function attachReceiptToTransaction(Receipt $receipt, Transaction $transaction): Transaction
    {
        try {
            // Update receipt with transaction reference
            $receipt->update([
                'transaction_id' => $transaction->id,
                'status' => 'matched',
            ]);

            // Update transaction with receipt data if needed
            $this->updateTransactionFromReceipt($transaction, $receipt);

            Log::info('Receipt matched to transaction', [
                'receipt_id' => $receipt->id,
                'transaction_id' => $transaction->id,
                'vendor_name' => $receipt->vendor_name,
                'amount' => $receipt->amount,
            ]);

            return $transaction;

        } catch (\Exception $e) {
            Log::error('Failed to attach receipt to transaction', [
                'receipt_id' => $receipt->id,
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create new transaction from receipt
     */
    protected function createTransactionFromReceipt(Receipt $receipt): Transaction
    {
        try {
            $transactionData = [
                'company_id' => $receipt->company_id,
                'type' => 'expense',
                'account_id' => $this->getDefaultExpenseAccount($receipt->company_id),
                'paid_at' => $receipt->receipt_date,
                'amount' => $receipt->amount,
                'currency_code' => $receipt->currency_code ?? setting('default.currency'),
                'currency_rate' => 1,
                'description' => $receipt->vendor_name ?? 'Receipt Upload',
                'category_id' => $receipt->category_id,
                'payment_method' => 'unknown',
                'reference' => 'Receipt #' . $receipt->id,
                'created_from' => 'receipt_upload',
            ];

            $transaction = Transaction::create($transactionData);

            // Link receipt to transaction
            $receipt->update([
                'transaction_id' => $transaction->id,
                'status' => 'matched',
            ]);

            Log::info('New transaction created from receipt', [
                'receipt_id' => $receipt->id,
                'transaction_id' => $transaction->id,
                'vendor_name' => $receipt->vendor_name,
                'amount' => $receipt->amount,
            ]);

            return $transaction;

        } catch (\Exception $e) {
            Log::error('Failed to create transaction from receipt', [
                'receipt_id' => $receipt->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update transaction with receipt data
     */
    protected function updateTransactionFromReceipt(Transaction $transaction, Receipt $receipt): void
    {
        $updates = [];

        // Update description if receipt has vendor name and transaction description is generic
        if ($receipt->vendor_name && 
            (empty($transaction->description) || 
             in_array(strtolower($transaction->description), ['expense', 'payment', 'transaction']))) {
            $updates['description'] = $receipt->vendor_name;
        }

        // Update category if receipt has category and transaction doesn't
        if ($receipt->category_id && !$transaction->category_id) {
            $updates['category_id'] = $receipt->category_id;
        }

        if (!empty($updates)) {
            $transaction->update($updates);
        }
    }

    /**
     * Get default expense account for company
     */
    protected function getDefaultExpenseAccount(int $companyId): int
    {
        // Try to find a default expense account
        $account = \App\Models\Banking\Account::where('company_id', $companyId)
            ->where('type', 'expense')
            ->where('enabled', 1)
            ->first();

        if (!$account) {
            // Fallback to any enabled account
            $account = \App\Models\Banking\Account::where('company_id', $companyId)
                ->where('enabled', 1)
                ->first();
        }

        return $account ? $account->id : 1; // Fallback to ID 1 if nothing found
    }

    /**
     * Process unmatched receipts for new transactions
     */
    public function processUnmatchedReceipts(): void
    {
        $unmatchedReceipts = Receipt::unmatched()
            ->processed()
            ->with(['company'])
            ->get();

        foreach ($unmatchedReceipts as $receipt) {
            try {
                $this->checkForNewMatches($receipt);
            } catch (\Exception $e) {
                Log::error('Failed to process unmatched receipt', [
                    'receipt_id' => $receipt->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Check for new transaction matches for unmatched receipt
     */
    protected function checkForNewMatches(Receipt $receipt): void
    {
        // Only check receipts that were created from receipt upload
        // and don't already have a transaction
        if ($receipt->transaction_id || $receipt->created_from !== 'receipt_upload') {
            return;
        }

        $match = $this->findExactMatch($receipt);
        if (!$match) {
            $match = $this->findCloseMatch($receipt);
        }

        if ($match) {
            $this->attachReceiptToTransaction($receipt, $match);
        }
    }

    /**
     * Handle new transaction creation - check if it matches any unmatched receipts
     */
    public function handleNewTransaction(Transaction $transaction): void
    {
        if ($transaction->type !== 'expense') {
            return;
        }

        // Find unmatched receipts that might match this transaction
        $startDate = Carbon::parse($transaction->paid_at)->subDays(5);
        $endDate = Carbon::parse($transaction->paid_at)->addDays(5);

        $potentialReceipts = Receipt::where('company_id', $transaction->company_id)
            ->whereNull('transaction_id')
            ->where('status', 'processed')
            ->where('created_from', 'receipt_upload')
            ->whereBetween('receipt_date', [$startDate, $endDate])
            ->get();

        $bestMatch = null;
        $bestScore = 0;

        foreach ($potentialReceipts as $receipt) {
            $score = $this->calculateMatchScore($receipt, $transaction);
            
            if ($score > $bestScore && $score >= 0.8) { // Higher threshold for auto-matching
                $bestScore = $score;
                $bestMatch = $receipt;
            }
        }

        if ($bestMatch) {
            $this->attachReceiptToTransaction($bestMatch, $transaction);
        }
    }
}