<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banking\Transaction;
use App\Models\Setting\Category;
use App\Models\Banking\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class FinancialAssistantController extends Controller
{
    /**
     * Handle financial assistant queries
     */
    public function query(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'context.company_id' => 'required|integer'
        ]);

        $message = $request->input('message');
        $companyId = $request->input('context.company_id');

        try {
            // Get financial data context
            $financialContext = $this->getFinancialContext($companyId);
            
            // Generate AI response
            $response = $this->generateAIResponse($message, $financialContext);

            return response()->json([
                'success' => true,
                'response' => $response
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to process your request. Please try again.'
            ], 500);
        }
    }

    /**
     * Get financial context for the AI
     */
    private function getFinancialContext($companyId)
    {
        $currentMonth = Carbon::now();
        $lastMonth = Carbon::now()->subMonth();
        $lastQuarter = Carbon::now()->subQuarter();
        $last6Months = Carbon::now()->subMonths(6);

        // Get recent transactions
        $recentTransactions = Transaction::where('company_id', $companyId)
            ->where('paid_at', '>=', $last6Months)
            ->with(['category', 'account', 'contact'])
            ->orderBy('paid_at', 'desc')
            ->limit(100)
            ->get();

        // Current month summary
        $currentMonthIncome = Transaction::where('company_id', $companyId)
            ->income()
            ->whereMonth('paid_at', $currentMonth->month)
            ->whereYear('paid_at', $currentMonth->year)
            ->sum('amount');

        $currentMonthExpenses = Transaction::where('company_id', $companyId)
            ->expense()
            ->whereMonth('paid_at', $currentMonth->month)
            ->whereYear('paid_at', $currentMonth->year)
            ->sum('amount');

        // Last month summary
        $lastMonthIncome = Transaction::where('company_id', $companyId)
            ->income()
            ->whereMonth('paid_at', $lastMonth->month)
            ->whereYear('paid_at', $lastMonth->year)
            ->sum('amount');

        $lastMonthExpenses = Transaction::where('company_id', $companyId)
            ->expense()
            ->whereMonth('paid_at', $lastMonth->month)
            ->whereYear('paid_at', $lastMonth->year)
            ->sum('amount');

        // Q1 summary (last 3 months)
        $q1Income = Transaction::where('company_id', $companyId)
            ->income()
            ->where('paid_at', '>=', $lastQuarter)
            ->sum('amount');

        $q1Expenses = Transaction::where('company_id', $companyId)
            ->expense()
            ->where('paid_at', '>=', $lastQuarter)
            ->sum('amount');

        // Top expense categories last month
        $topExpenseCategories = Transaction::where('company_id', $companyId)
            ->expense()
            ->whereMonth('paid_at', $lastMonth->month)
            ->whereYear('paid_at', $lastMonth->year)
            ->with('category')
            ->selectRaw('category_id, SUM(amount) as total_amount')
            ->groupBy('category_id')
            ->orderBy('total_amount', 'desc')
            ->limit(5)
            ->get();

        // Uncategorized transactions
        $uncategorizedTransactions = Transaction::where('company_id', $companyId)
            ->whereNull('category_id')
            ->where('paid_at', '>=', $last6Months)
            ->limit(10)
            ->get();

        // Account balances
        $accounts = Account::where('company_id', $companyId)
            ->where('enabled', 1)
            ->get();

        return [
            'current_month' => [
                'income' => $currentMonthIncome,
                'expenses' => $currentMonthExpenses,
                'net' => $currentMonthIncome - $currentMonthExpenses,
                'month' => $currentMonth->format('F Y')
            ],
            'last_month' => [
                'income' => $lastMonthIncome,
                'expenses' => $lastMonthExpenses,
                'net' => $lastMonthIncome - $lastMonthExpenses,
                'month' => $lastMonth->format('F Y')
            ],
            'quarter' => [
                'income' => $q1Income,
                'expenses' => $q1Expenses,
                'net' => $q1Income - $q1Expenses,
                'period' => 'Last 3 months'
            ],
            'top_expense_categories' => $topExpenseCategories->map(function ($item) {
                return [
                    'category' => $item->category->name ?? 'Uncategorized',
                    'amount' => $item->total_amount
                ];
            }),
            'uncategorized_count' => $uncategorizedTransactions->count(),
            'uncategorized_transactions' => $uncategorizedTransactions->map(function ($transaction) {
                return [
                    'amount' => $transaction->amount,
                    'description' => $transaction->description,
                    'date' => $transaction->paid_at->format('M d, Y'),
                    'account' => $transaction->account->name ?? 'Unknown'
                ];
            }),
            'accounts' => $accounts->map(function ($account) {
                return [
                    'name' => $account->name,
                    'balance' => $account->balance,
                    'currency' => $account->currency_code
                ];
            }),
            'recent_transactions' => $recentTransactions->take(20)->map(function ($transaction) {
                return [
                    'amount' => $transaction->amount,
                    'type' => $transaction->isIncome() ? 'Income' : 'Expense',
                    'description' => $transaction->description,
                    'category' => $transaction->category->name ?? 'Uncategorized',
                    'date' => $transaction->paid_at->format('M d, Y'),
                    'account' => $transaction->account->name ?? 'Unknown'
                ];
            })
        ];
    }

    /**
     * Generate AI response using OpenAI API
     */
    private function generateAIResponse($userMessage, $financialContext)
    {
        $openaiApiKey = env('OPENAI_API_KEY');
        
        if (!$openaiApiKey) {
            return $this->generateMockResponse($userMessage, $financialContext);
        }

        $systemPrompt = $this->buildSystemPrompt($financialContext);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $openaiApiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ],
                    [
                        'role' => 'user',
                        'content' => $userMessage
                    ]
                ],
                'max_tokens' => 500,
                'temperature' => 0.7
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['choices'][0]['message']['content'] ?? 'I apologize, but I could not generate a response at this time.';
            } else {
                return $this->generateMockResponse($userMessage, $financialContext);
            }
        } catch (\Exception $e) {
            return $this->generateMockResponse($userMessage, $financialContext);
        }
    }

    /**
     * Build system prompt with financial context
     */
    private function buildSystemPrompt($context)
    {
        $prompt = "You are a helpful financial assistant for an accounting software. You have access to the user's financial data and should provide insights, analysis, and recommendations based on this data.\n\n";
        
        $prompt .= "FINANCIAL DATA CONTEXT:\n";
        $prompt .= "Current Month ({$context['current_month']['month']}):\n";
        $prompt .= "- Income: $" . number_format($context['current_month']['income'], 2) . "\n";
        $prompt .= "- Expenses: $" . number_format($context['current_month']['expenses'], 2) . "\n";
        $prompt .= "- Net: $" . number_format($context['current_month']['net'], 2) . "\n\n";
        
        $prompt .= "Last Month ({$context['last_month']['month']}):\n";
        $prompt .= "- Income: $" . number_format($context['last_month']['income'], 2) . "\n";
        $prompt .= "- Expenses: $" . number_format($context['last_month']['expenses'], 2) . "\n";
        $prompt .= "- Net: $" . number_format($context['last_month']['net'], 2) . "\n\n";
        
        $prompt .= "Quarter Summary ({$context['quarter']['period']}):\n";
        $prompt .= "- Income: $" . number_format($context['quarter']['income'], 2) . "\n";
        $prompt .= "- Expenses: $" . number_format($context['quarter']['expenses'], 2) . "\n";
        $prompt .= "- Net: $" . number_format($context['quarter']['net'], 2) . "\n\n";
        
        if ($context['top_expense_categories']->isNotEmpty()) {
            $prompt .= "Top Expense Categories (Last Month):\n";
            foreach ($context['top_expense_categories'] as $category) {
                $prompt .= "- {$category['category']}: $" . number_format($category['amount'], 2) . "\n";
            }
            $prompt .= "\n";
        }
        
        if ($context['uncategorized_count'] > 0) {
            $prompt .= "Uncategorized Transactions: {$context['uncategorized_count']} transactions need categorization\n\n";
        }
        
        $prompt .= "INSTRUCTIONS:\n";
        $prompt .= "- Provide specific, actionable insights based on the financial data\n";
        $prompt .= "- Use actual numbers from the data when relevant\n";
        $prompt .= "- Be concise but informative\n";
        $prompt .= "- Highlight important trends, patterns, or concerns\n";
        $prompt .= "- Suggest practical next steps when appropriate\n";
        $prompt .= "- Format responses with bullet points or short paragraphs for readability\n";
        
        return $prompt;
    }

    /**
     * Generate mock response when OpenAI is not available
     */
    private function generateMockResponse($userMessage, $context)
    {
        $message = strtolower($userMessage);

        if (strpos($message, 'biggest expenses') !== false || strpos($message, 'largest expenses') !== false) {
            if ($context['top_expense_categories']->isNotEmpty()) {
                $response = "**Your Biggest Expenses Last Month:**\n\n";
                foreach ($context['top_expense_categories']->take(3) as $category) {
                    $response .= "• **{$category['category']}**: $" . number_format($category['amount'], 2) . "\n";
                }
                $response .= "\nThese categories represent your highest spending areas. Consider reviewing these for potential savings opportunities.";
                return $response;
            }
        }

        if (strpos($message, 'summary') !== false && (strpos($message, 'q1') !== false || strpos($message, 'quarter') !== false)) {
            $response = "**Q1 Financial Summary (Last 3 Months):**\n\n";
            $response .= "• **Total Income**: $" . number_format($context['quarter']['income'], 2) . "\n";
            $response .= "• **Total Expenses**: $" . number_format($context['quarter']['expenses'], 2) . "\n";
            $response .= "• **Net Profit/Loss**: $" . number_format($context['quarter']['net'], 2) . "\n\n";
            
            if ($context['quarter']['net'] > 0) {
                $response .= "✅ You had a positive cash flow this quarter!";
            } else {
                $response .= "⚠️ You had negative cash flow this quarter. Consider reviewing your expenses.";
            }
            
            return $response;
        }

        if (strpos($message, 'uncategorized') !== false) {
            if ($context['uncategorized_count'] > 0) {
                $response = "**Uncategorized Transactions Review:**\n\n";
                $response .= "You have **{$context['uncategorized_count']} uncategorized transactions** that need attention.\n\n";
                
                if ($context['uncategorized_transactions']->isNotEmpty()) {
                    $response .= "Recent uncategorized transactions:\n";
                    foreach ($context['uncategorized_transactions']->take(5) as $transaction) {
                        $response .= "• $" . number_format($transaction['amount'], 2) . " - {$transaction['description']} ({$transaction['date']})\n";
                    }
                }
                
                $response .= "\n💡 **Tip**: Categorizing these transactions will help you get better insights into your spending patterns.";
                return $response;
            } else {
                return "Great news! All your recent transactions are properly categorized. Your financial records are well organized! 🎉";
            }
        }

        if (strpos($message, 'cash flow') !== false) {
            $response = "**Current Cash Flow Analysis:**\n\n";
            $response .= "**This Month ({$context['current_month']['month']}):**\n";
            $response .= "• Income: $" . number_format($context['current_month']['income'], 2) . "\n";
            $response .= "• Expenses: $" . number_format($context['current_month']['expenses'], 2) . "\n";
            $response .= "• Net: $" . number_format($context['current_month']['net'], 2) . "\n\n";
            
            $lastMonthNet = $context['last_month']['net'];
            $currentMonthNet = $context['current_month']['net'];
            
            if ($currentMonthNet > $lastMonthNet) {
                $response .= "📈 Your cash flow improved compared to last month!";
            } elseif ($currentMonthNet < $lastMonthNet) {
                $response .= "📉 Your cash flow decreased compared to last month. Consider reviewing recent expenses.";
            } else {
                $response .= "➡️ Your cash flow is similar to last month.";
            }
            
            return $response;
        }

        // Default response
        return "I've analyzed your financial data. Based on your recent transactions and account activity, here are some key insights:\n\n" .
               "• **Current Month Net**: $" . number_format($context['current_month']['net'], 2) . "\n" .
               "• **Last Month Net**: $" . number_format($context['last_month']['net'], 2) . "\n" .
               "• **Uncategorized Transactions**: {$context['uncategorized_count']}\n\n" .
               "Would you like me to dive deeper into any specific area of your finances?";
    }
}