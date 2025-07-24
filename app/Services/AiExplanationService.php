<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class AiExplanationService
{
    protected $client;
    protected $apiKey;
    protected $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
    }

    /**
     * Generate an AI explanation for monthly financial summary
     *
     * @param float $totalIncome
     * @param float $totalExpense
     * @param float $netProfit
     * @param string $monthName
     * @param string $currency
     * @return string
     */
    public function generateMonthlySummaryExplanation($totalIncome, $totalExpense, $netProfit, $monthName, $currency = 'USD')
    {
        // If no API key is configured, return a default explanation
        if (empty($this->apiKey)) {
            return $this->getDefaultExplanation($totalIncome, $totalExpense, $netProfit, $monthName, $currency);
        }

        try {
            $prompt = $this->buildPrompt($totalIncome, $totalExpense, $netProfit, $monthName, $currency);
            
            $response = $this->client->post($this->baseUrl . '/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a financial advisor providing brief, professional insights about monthly business performance. Keep explanations concise, actionable, and under 200 words.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => 200,
                    'temperature' => 0.7,
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['choices'][0]['message']['content'])) {
                return trim($data['choices'][0]['message']['content']);
            }

            return $this->getDefaultExplanation($totalIncome, $totalExpense, $netProfit, $monthName, $currency);

        } catch (RequestException $e) {
            Log::error('AI Explanation Service Error: ' . $e->getMessage());
            return $this->getDefaultExplanation($totalIncome, $totalExpense, $netProfit, $monthName, $currency);
        }
    }

    /**
     * Build the prompt for AI explanation
     *
     * @param float $totalIncome
     * @param float $totalExpense
     * @param float $netProfit
     * @param string $monthName
     * @param string $currency
     * @return string
     */
    protected function buildPrompt($totalIncome, $totalExpense, $netProfit, $monthName, $currency)
    {
        $profitMargin = $totalIncome > 0 ? round(($netProfit / $totalIncome) * 100, 2) : 0;
        $expenseRatio = $totalIncome > 0 ? round(($totalExpense / $totalIncome) * 100, 2) : 0;

        return sprintf(
            "Analyze this monthly financial summary for %s:\n\n" .
            "Total Income: %s %.2f\n" .
            "Total Expenses: %s %.2f\n" .
            "Net Profit: %s %.2f\n" .
            "Profit Margin: %.2f%%\n" .
            "Expense Ratio: %.2f%%\n\n" .
            "Provide a brief professional analysis focusing on:\n" .
            "1. Overall performance assessment\n" .
            "2. Key insights about income vs expenses\n" .
            "3. One actionable recommendation for improvement\n\n" .
            "Keep it concise and business-focused.",
            $monthName,
            $currency,
            $totalIncome,
            $currency,
            $totalExpense,
            $currency,
            $netProfit,
            $profitMargin,
            $expenseRatio
        );
    }

    /**
     * Get default explanation when AI service is unavailable
     *
     * @param float $totalIncome
     * @param float $totalExpense
     * @param float $netProfit
     * @param string $monthName
     * @param string $currency
     * @return string
     */
    protected function getDefaultExplanation($totalIncome, $totalExpense, $netProfit, $monthName, $currency)
    {
        $profitMargin = $totalIncome > 0 ? round(($netProfit / $totalIncome) * 100, 2) : 0;
        
        if ($netProfit > 0) {
            $performance = "positive";
            $recommendation = "Consider reinvesting profits into growth opportunities or building cash reserves for future expansion.";
        } elseif ($netProfit == 0) {
            $performance = "break-even";
            $recommendation = "Focus on increasing revenue streams or optimizing expenses to improve profitability.";
        } else {
            $performance = "challenging";
            $recommendation = "Review expense categories to identify cost-cutting opportunities and explore ways to boost income.";
        }

        return sprintf(
            "Your business had a %s month in %s with a profit margin of %.2f%%. " .
            "Total income of %s %.2f covered expenses of %s %.2f, resulting in a net %s of %s %.2f. " .
            "%s This summary provides a snapshot of your financial health for strategic planning.",
            $performance,
            $monthName,
            $profitMargin,
            $currency,
            $totalIncome,
            $currency,
            $totalExpense,
            $netProfit >= 0 ? 'profit' : 'loss',
            $currency,
            abs($netProfit),
            $recommendation
        );
    }
}