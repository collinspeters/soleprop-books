<?php

namespace App\Console\Commands;

use App\Jobs\Common\GenerateMonthlySummary;
use App\Models\Common\Company;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlySummaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'summaries:generate-monthly 
                            {--company= : Generate for specific company ID}
                            {--month= : Generate for specific month (1-12)}
                            {--year= : Generate for specific year}
                            {--force : Force generation even if already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and send monthly financial summaries for all companies or a specific company';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $companyId = $this->option('company');
        $month = $this->option('month') ?? Carbon::now()->subMonth()->month;
        $year = $this->option('year') ?? Carbon::now()->subMonth()->year;
        $force = $this->option('force');

        $this->info("Generating monthly summaries for {$month}/{$year}...");

        if ($companyId) {
            $companies = Company::where('id', $companyId)->where('enabled', 1)->get();
            
            if ($companies->isEmpty()) {
                $this->error("Company with ID {$companyId} not found or disabled.");
                return 1;
            }
        } else {
            $companies = Company::where('enabled', 1)->get();
        }

        $this->info("Found " . $companies->count() . " companies to process.");

        $processed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($companies as $company) {
            try {
                // Check if auto-generation is enabled for this company
                company($company);
                
                $autoEnabled = setting('monthly_summary.auto_generation.enabled', false);
                $scheduledDay = setting('monthly_summary.auto_generation.day_of_month', 1);
                
                if (!$autoEnabled && !$force) {
                    $this->line("Skipping company {$company->name} - auto-generation disabled");
                    $skipped++;
                    continue;
                }

                // Check if we should generate today (if not forced)
                if (!$force && Carbon::now()->day != $scheduledDay) {
                    $this->line("Skipping company {$company->name} - not scheduled for today");
                    $skipped++;
                    continue;
                }

                // Check if summary already exists (if not forced)
                $cacheKey = "monthly_summary_{$company->id}_{$year}_{$month}";
                if (!$force && cache()->has($cacheKey)) {
                    $this->line("Skipping company {$company->name} - summary already exists");
                    $skipped++;
                    continue;
                }

                // Get users to notify
                $userIds = setting('monthly_summary.auto_generation.users', []);
                $users = null;
                
                if (!empty($userIds)) {
                    $users = $company->users()->whereIn('id', $userIds)->get();
                }

                // Dispatch the job
                GenerateMonthlySummary::dispatch($company, $month, $year, $users);
                
                $this->info("✓ Queued monthly summary for company: {$company->name}");
                $processed++;
                
            } catch (\Exception $e) {
                $this->error("✗ Error processing company {$company->name}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->info("\n--- Summary ---");
        $this->info("Processed: {$processed}");
        $this->info("Skipped: {$skipped}");
        $this->info("Errors: {$errors}");

        return $errors > 0 ? 1 : 0;
    }
}