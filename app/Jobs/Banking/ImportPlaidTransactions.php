<?php

namespace App\Jobs\Banking;

use App\Abstracts\Job;
use App\Services\PlaidBankingService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ImportPlaidTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $accessToken;
    protected $startDate;
    protected $endDate;
    protected $accountIds;
    protected $userId;
    protected $companyId;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $accessToken,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        array $accountIds = [],
        ?int $userId = null,
        ?int $companyId = null
    ) {
        $this->accessToken = $accessToken;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->accountIds = $accountIds;
        $this->userId = $userId ?? auth()->id();
        $this->companyId = $companyId ?? company_id();
    }

    /**
     * Execute the job.
     */
    public function handle(): array
    {
        Log::info('Starting Plaid transaction import job', [
            'user_id' => $this->userId,
            'company_id' => $this->companyId,
            'start_date' => $this->startDate?->format('Y-m-d'),
            'end_date' => $this->endDate?->format('Y-m-d'),
            'account_ids' => $this->accountIds
        ]);

        try {
            // Set the company context for the job
            if ($this->companyId) {
                session(['company_id' => $this->companyId]);
            }

            $plaidService = new PlaidBankingService();
            
            $results = $plaidService->importTransactions(
                $this->accessToken,
                $this->startDate,
                $this->endDate,
                $this->accountIds
            );

            Log::info('Plaid transaction import completed', [
                'user_id' => $this->userId,
                'company_id' => $this->companyId,
                'results' => [
                    'imported' => $results['imported'],
                    'duplicates' => $results['duplicates'],
                    'errors' => $results['errors'],
                    'categorized' => $results['categorized']
                ]
            ]);

            // Store results in cache for the user to retrieve
            $cacheKey = "plaid_import_results_{$this->userId}_{$this->companyId}";
            cache()->put($cacheKey, $results, now()->addHours(24));

            // Send notification to user if configured
            $this->notifyUser($results);

            return $results;

        } catch (Exception $e) {
            Log::error('Plaid transaction import job failed', [
                'user_id' => $this->userId,
                'company_id' => $this->companyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Store error in cache
            $cacheKey = "plaid_import_results_{$this->userId}_{$this->companyId}";
            cache()->put($cacheKey, [
                'status' => 'error',
                'message' => $e->getMessage(),
                'imported' => 0,
                'duplicates' => 0,
                'errors' => 0,
                'categorized' => 0
            ], now()->addHours(24));

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Plaid transaction import job failed permanently', [
            'user_id' => $this->userId,
            'company_id' => $this->companyId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Store failure status in cache
        $cacheKey = "plaid_import_results_{$this->userId}_{$this->companyId}";
        cache()->put($cacheKey, [
            'status' => 'failed',
            'message' => 'Import failed after multiple attempts: ' . $exception->getMessage(),
            'imported' => 0,
            'duplicates' => 0,
            'errors' => 0,
            'categorized' => 0
        ], now()->addHours(24));
    }

    /**
     * Notify user about import results
     */
    protected function notifyUser(array $results): void
    {
        try {
            // You can implement notification logic here
            // For example, send email, push notification, or store in notifications table
            
            Log::info('Plaid import notification sent', [
                'user_id' => $this->userId,
                'results' => $results
            ]);

        } catch (Exception $e) {
            Log::warning('Failed to send import notification', [
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'plaid-import',
            "user:{$this->userId}",
            "company:{$this->companyId}"
        ];
    }
}