<?php

namespace App\Widgets;

use App\Abstracts\Widget;
use App\Reports\MonthlySummary;
use App\Services\AiExplanationService;
use Carbon\Carbon;

class MonthlySummaryWidget extends Widget
{
    public $default_name = 'widgets.monthly_summary';

    public $description = 'widgets.monthly_summary_description';

    public $report_class = 'App\Reports\MonthlySummary';

    public $views = [
        'header' => 'widgets.monthly-summary.header',
        'content' => 'widgets.monthly-summary.content',
    ];

    public function show()
    {
        $month = request('month', Carbon::now()->subMonth()->month);
        $year = request('year', Carbon::now()->subMonth()->year);

        // Try to get cached summary first
        $cacheKey = "monthly_summary_" . company_id() . "_{$year}_{$month}";
        $cachedSummary = cache()->get($cacheKey);

        if ($cachedSummary) {
            $summaryData = $cachedSummary['summary_data'];
            $aiExplanation = $cachedSummary['ai_explanation'];
        } else {
            // Generate fresh summary
            $report = new MonthlySummary($month, $year);
            $report->setData();
            
            $summaryData = $report->array();
            
            // Get AI explanation
            $aiService = new AiExplanationService();
            $currency = default_currency();
            
            $aiExplanation = $aiService->generateMonthlySummaryExplanation(
                $report->getTotalIncome(),
                $report->getTotalExpense(),
                $report->getNetProfit(),
                $report->getMonthName(),
                $currency
            );

            // Cache the result
            cache()->put($cacheKey, [
                'summary_data' => $summaryData,
                'ai_explanation' => $aiExplanation,
                'generated_at' => now(),
            ], now()->addHours(6));
        }

        // Get available months for dropdown
        $availableMonths = $this->getAvailableMonths();

        return $this->view('widgets.monthly-summary.index', compact(
            'summaryData',
            'aiExplanation',
            'availableMonths',
            'month',
            'year'
        ));
    }

    /**
     * Get available months with financial data
     */
    protected function getAvailableMonths()
    {
        $months = [];
        $currentDate = Carbon::now();
        
        // Get last 12 months
        for ($i = 1; $i <= 12; $i++) {
            $date = $currentDate->copy()->subMonths($i);
            $months[] = [
                'month' => $date->month,
                'year' => $date->year,
                'name' => $date->format('F Y'),
                'value' => $date->format('Y-m')
            ];
        }

        return $months;
    }
}