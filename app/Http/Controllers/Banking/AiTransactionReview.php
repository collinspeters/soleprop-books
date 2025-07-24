<?php

namespace App\Http\Controllers\Banking;

use App\Abstracts\Http\Controller;
use App\Models\Banking\Transaction;
use App\Models\Setting\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiTransactionReview extends Controller
{
    /**
     * Display a listing of transactions requiring AI review.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        // Get confidence threshold from request or use default
        $confidenceThreshold = $request->get('confidence_threshold', 0.7);
        
        // Get filter type from request
        $filter = $request->get('filter', 'low_confidence');
        
        $query = Transaction::with('account', 'category', 'aiSuggestedCategory', 'contact', 'aiReviewedBy');
        
        switch ($filter) {
            case 'low_confidence':
                $transactions = $query->lowConfidenceAi($confidenceThreshold);
                break;
            case 'unreviewed':
                $transactions = $query->unreviewedAi();
                break;
            case 'reviewed':
                $transactions = $query->reviewedAi();
                break;
            default:
                $transactions = $query->hasAiSuggestion();
        }
        
        $transactions = $transactions->latest('paid_at')->paginate(25);
        
        // Get all categories for the dropdown
        $categories = Category::enabled()->orderBy('name')->get();
        
        // Statistics for the dashboard
        $stats = [
            'total_ai_suggestions' => Transaction::hasAiSuggestion()->count(),
            'low_confidence' => Transaction::lowConfidenceAi($confidenceThreshold)->count(),
            'unreviewed' => Transaction::unreviewedAi()->count(),
            'reviewed' => Transaction::reviewedAi()->count(),
        ];
        
        return view('banking.ai-review.index', compact(
            'transactions',
            'categories',
            'stats',
            'confidenceThreshold',
            'filter'
        ));
    }

    /**
     * Update a transaction's category after AI review.
     *
     * @param Request $request
     * @param Transaction $transaction
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Transaction $transaction)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'action' => 'required|in:approve,override',
        ]);

        $categoryId = $request->get('category_id');
        $action = $request->get('action');

        // Update the transaction
        $transaction->update([
            'category_id' => $categoryId,
            'ai_reviewed' => true,
            'ai_reviewed_at' => now(),
            'ai_reviewed_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $action === 'approve' 
                ? trans('transactions.ai_suggestion_approved')
                : trans('transactions.ai_suggestion_overridden'),
            'transaction' => $transaction->load('category', 'aiSuggestedCategory', 'aiReviewedBy')
        ]);
    }

    /**
     * Bulk update multiple transactions.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'exists:transactions,id',
            'action' => 'required|in:approve_all,mark_reviewed',
        ]);

        $transactionIds = $request->get('transaction_ids');
        $action = $request->get('action');

        $transactions = Transaction::whereIn('id', $transactionIds)->get();
        
        $updatedCount = 0;
        
        foreach ($transactions as $transaction) {
            if ($action === 'approve_all' && $transaction->ai_suggested_category_id) {
                $transaction->update([
                    'category_id' => $transaction->ai_suggested_category_id,
                    'ai_reviewed' => true,
                    'ai_reviewed_at' => now(),
                    'ai_reviewed_by' => Auth::id(),
                ]);
                $updatedCount++;
            } elseif ($action === 'mark_reviewed') {
                $transaction->update([
                    'ai_reviewed' => true,
                    'ai_reviewed_at' => now(),
                    'ai_reviewed_by' => Auth::id(),
                ]);
                $updatedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => trans('transactions.bulk_ai_review_completed', ['count' => $updatedCount]),
            'updated_count' => $updatedCount
        ]);
    }
}