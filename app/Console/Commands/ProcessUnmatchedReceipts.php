<?php

namespace App\Console\Commands;

use App\Services\Receipt\ReceiptProcessingService;
use Illuminate\Console\Command;

class ProcessUnmatchedReceipts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'receipts:process-unmatched 
                            {--dry-run : Show what would be processed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process unmatched receipts and attempt to match them to transactions';

    /**
     * Execute the console command.
     */
    public function handle(ReceiptProcessingService $receiptProcessingService): int
    {
        $this->info('Processing unmatched receipts...');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        try {
            if (!$this->option('dry-run')) {
                $receiptProcessingService->processUnmatchedReceipts();
            }

            $this->info('✅ Unmatched receipts processing completed successfully');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to process unmatched receipts: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
