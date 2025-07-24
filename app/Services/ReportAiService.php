<?php

namespace App\Services;

use App\Abstracts\Report;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReportAiService
{
    protected $apiKey;
    protected $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        $this->apiUrl = 'https://api.openai.com/v1/chat/completions';
        
        // Allow override from settings
        if (function_exists('setting')) {
            $provider = setting('ai.reports.provider', 'openai');
            if ($provider === 'openai') {
                $this->apiUrl = 'https://api.openai.com/v1/chat/completions';
            }
        }
    }

    /**
     * Generate AI summary for a report
     *
     * @param Report $report
     * @param string $userQuestion
     * @return array
     */
    public function summarizeReport(Report $report, string $userQuestion = null): array
    {
        try {
            $reportData = $this->extractReportData($report);
            $prompt = $this->buildPrompt($reportData, $userQuestion);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->apiUrl, [
                'model' => function_exists('setting') ? setting('ai.reports.model', 'gpt-3.5-turbo') : 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a financial analyst AI assistant specializing in business report analysis. Provide clear, concise, and actionable insights in plain language that business owners can easily understand.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 500,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'summary' => $data['choices'][0]['message']['content'] ?? 'No summary generated.',
                    'usage' => $data['usage'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to generate AI summary: ' . $response->body(),
            ];

        } catch (\Exception $e) {
            Log::error('AI Report Summary Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'An error occurred while generating the summary. Please try again.',
            ];
        }
    }

    /**
     * Extract relevant data from the report
     *
     * @param Report $report
     * @return array
     */
    protected function extractReportData(Report $report): array
    {
        $data = [
            'report_name' => $report->model->name,
            'report_type' => class_basename($report),
            'period' => $report->getPeriod(),
            'basis' => $report->getBasis(),
            'totals' => [],
            'tables' => $report->tables ?? [],
        ];

        // Extract footer totals if available
        if (property_exists($report, 'footer_totals') && !empty($report->footer_totals)) {
            $data['totals'] = $report->footer_totals;
        }

        // Extract net profit for P&L reports
        if (property_exists($report, 'net_profit') && !empty($report->net_profit)) {
            $data['net_profit'] = $report->net_profit;
        }

        // Extract row values for detailed analysis
        if (property_exists($report, 'row_values') && !empty($report->row_values)) {
            $data['row_values'] = $report->row_values;
        }

        return $data;
    }

    /**
     * Build the AI prompt based on report data and user question
     *
     * @param array $reportData
     * @param string|null $userQuestion
     * @return string
     */
    protected function buildPrompt(array $reportData, ?string $userQuestion): string
    {
        $prompt = "Please analyze the following {$reportData['report_type']} report data and provide insights in plain language:\n\n";
        
        $prompt .= "Report: {$reportData['report_name']}\n";
        $prompt .= "Period: {$reportData['period']}\n";
        $prompt .= "Accounting Basis: {$reportData['basis']}\n\n";

        // Add totals information
        if (!empty($reportData['totals'])) {
            $prompt .= "Financial Totals:\n";
            foreach ($reportData['totals'] as $category => $periods) {
                $prompt .= "- {$category}: ";
                if (is_array($periods)) {
                    $total = array_sum($periods);
                    $prompt .= number_format($total, 2) . "\n";
                } else {
                    $prompt .= number_format($periods, 2) . "\n";
                }
            }
            $prompt .= "\n";
        }

        // Add net profit for P&L reports
        if (!empty($reportData['net_profit'])) {
            $prompt .= "Net Profit Analysis:\n";
            if (is_array($reportData['net_profit'])) {
                $totalProfit = array_sum($reportData['net_profit']);
                $prompt .= "Total Net Profit: " . number_format($totalProfit, 2) . "\n";
            }
            $prompt .= "\n";
        }

        // Add user's specific question if provided
        if ($userQuestion) {
            $prompt .= "Specific Question: {$userQuestion}\n\n";
            $prompt .= "Please focus your analysis on answering this question while providing overall insights about the report.\n\n";
        }

        $prompt .= "Please provide:\n";
        $prompt .= "1. A brief overview of the financial performance\n";
        $prompt .= "2. Key insights and trends\n";
        $prompt .= "3. Areas of concern or opportunity\n";
        $prompt .= "4. Actionable recommendations\n\n";
        $prompt .= "Keep the language simple and avoid technical jargon. Focus on what these numbers mean for the business.";

        return $prompt;
    }

    /**
     * Check if AI service is configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        $hasApiKey = !empty($this->apiKey);
        $isEnabled = function_exists('setting') ? setting('ai.reports.enabled', false) : true;
        
        return $hasApiKey && $isEnabled;
    }
}