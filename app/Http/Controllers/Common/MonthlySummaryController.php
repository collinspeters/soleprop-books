<?php

namespace App\Http\Controllers\Common;

use App\Abstracts\Http\Controller;
use App\Jobs\Common\GenerateMonthlySummary;
use App\Reports\MonthlySummary;
use App\Services\AiExplanationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MonthlySummaryController extends Controller
{
    /**
     * Instantiate a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('permission:read-reports');
    }

    /**
     * Display the monthly summary report
     */
    public function show(Request $request, $month = null, $year = null)
    {
        $month = $month ?? Carbon::now()->subMonth()->month;
        $year = $year ?? Carbon::now()->subMonth()->year;

        // Generate the monthly summary
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

        // Get available months for dropdown
        $availableMonths = $this->getAvailableMonths();

        return view('reports.monthly-summary.show', compact(
            'summaryData',
            'aiExplanation',
            'availableMonths',
            'month',
            'year'
        ));
    }

    /**
     * Send monthly summary via email
     */
    public function sendEmail(Request $request)
    {
        $month = $request->get('month', Carbon::now()->subMonth()->month);
        $year = $request->get('year', Carbon::now()->subMonth()->year);
        
        // Get selected users or default to current user
        $users = $request->has('users') ? 
            user()->company->users()->whereIn('id', $request->get('users'))->get() : 
            collect([user()]);

        // Dispatch the job to generate and send the summary
        GenerateMonthlySummary::dispatch(company(), $month, $year, $users);

        $message = trans('messages.success.sent', ['type' => 'Monthly Summary']);
        
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        }

        flash($message)->success();
        return redirect()->back();
    }

    /**
     * Get monthly summary for dashboard widget
     */
    public function getDashboardData(Request $request)
    {
        $month = $request->get('month', Carbon::now()->subMonth()->month);
        $year = $request->get('year', Carbon::now()->subMonth()->year);

        // Try to get cached summary first
        $cacheKey = "monthly_summary_" . company_id() . "_{$year}_{$month}";
        $cachedSummary = cache()->get($cacheKey);

        if ($cachedSummary) {
            return response()->json([
                'success' => true,
                'data' => $cachedSummary
            ]);
        }

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

        $data = [
            'summary_data' => $summaryData,
            'ai_explanation' => $aiExplanation,
            'generated_at' => now(),
        ];

        // Cache the result
        cache()->put($cacheKey, $data, now()->addHours(6));

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Schedule automatic monthly summary generation
     */
    public function scheduleAutoGeneration(Request $request)
    {
        $request->validate([
            'enabled' => 'required|boolean',
            'day_of_month' => 'required_if:enabled,true|integer|min:1|max:28',
            'users' => 'array',
            'users.*' => 'exists:users,id'
        ]);

        // Store the schedule settings
        setting([
            'monthly_summary.auto_generation.enabled' => $request->get('enabled'),
            'monthly_summary.auto_generation.day_of_month' => $request->get('day_of_month', 1),
            'monthly_summary.auto_generation.users' => $request->get('users', []),
        ]);

        setting()->save();

        $message = $request->get('enabled') ? 
            'Automatic monthly summary generation has been enabled.' :
            'Automatic monthly summary generation has been disabled.';

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        }

        flash($message)->success();
        return redirect()->back();
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