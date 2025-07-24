<?php

namespace App\Jobs\Common;

use App\Abstracts\Job;
use App\Models\Auth\User;
use App\Models\Common\Company;
use App\Notifications\Common\MonthlySummary as MonthlySummaryNotification;
use App\Reports\MonthlySummary;
use App\Services\AiExplanationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateMonthlySummary extends Job implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $company;
    protected $month;
    protected $year;
    protected $users;

    /**
     * Create a new job instance.
     */
    public function __construct(Company $company, $month = null, $year = null, $users = null)
    {
        $this->company = $company;
        $this->month = $month ?? Carbon::now()->subMonth()->month;
        $this->year = $year ?? Carbon::now()->subMonth()->year;
        $this->users = $users;

        $this->onQueue('reports');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Set the company context
        company($this->company);
        
        try {
            // Generate the monthly summary report
            $report = new MonthlySummary($this->month, $this->year);
            $report->setData();
            
            $summaryData = $report->array();
            
            // Generate AI explanation
            $aiService = new AiExplanationService();
            $currency = default_currency();
            
            $aiExplanation = $aiService->generateMonthlySummaryExplanation(
                $report->getTotalIncome(),
                $report->getTotalExpense(),
                $report->getNetProfit(),
                $report->getMonthName(),
                $currency
            );

            // Determine which users to notify
            $usersToNotify = $this->getUsersToNotify();

            // Send notifications to users
            foreach ($usersToNotify as $user) {
                $user->notify(new MonthlySummaryNotification($summaryData, $aiExplanation));
            }

            // Store the summary for dashboard display
            $this->storeMonthlySummary($summaryData, $aiExplanation);

        } catch (\Exception $e) {
            \Log::error('Monthly Summary Generation Failed: ' . $e->getMessage(), [
                'company_id' => $this->company->id,
                'month' => $this->month,
                'year' => $this->year,
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Get users to notify based on permissions and preferences
     */
    protected function getUsersToNotify()
    {
        if ($this->users) {
            return collect($this->users);
        }

        // Get all company users who can read reports
        return $this->company->users()
            ->whereHas('roles', function ($query) {
                $query->whereHas('permissions', function ($permQuery) {
                    $permQuery->where('name', 'like', '%read-reports%')
                             ->orWhere('name', 'like', '%read-admin-panel%');
                });
            })
            ->where('enabled', 1)
            ->get();
    }

    /**
     * Store monthly summary for dashboard display
     */
    protected function storeMonthlySummary($summaryData, $aiExplanation)
    {
        // You could store this in a dedicated table or cache
        // For now, we'll use Laravel's cache system
        $cacheKey = "monthly_summary_{$this->company->id}_{$this->year}_{$this->month}";
        
        cache()->put($cacheKey, [
            'summary_data' => $summaryData,
            'ai_explanation' => $aiExplanation,
            'generated_at' => now(),
        ], now()->addMonths(12)); // Keep for 12 months
    }
}