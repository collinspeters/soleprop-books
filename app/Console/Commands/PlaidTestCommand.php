<?php

namespace App\Console\Commands;

use App\Services\PlaidBankingService;
use App\Services\OpenAiCategorizationService;
use Illuminate\Console\Command;

class PlaidTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plaid:test {--openai : Test OpenAI integration only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Plaid and OpenAI integrations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Testing Plaid and OpenAI Integrations...');
        $this->newLine();

        if ($this->option('openai')) {
            return $this->testOpenAI();
        }

        // Test Plaid
        $plaidResult = $this->testPlaid();
        
        // Test OpenAI
        $openAiResult = $this->testOpenAI();

        $this->newLine();
        if ($plaidResult && $openAiResult) {
            $this->info('✅ All integrations are working correctly!');
            return Command::SUCCESS;
        } else {
            $this->error('❌ Some integrations failed. Check the output above for details.');
            return Command::FAILURE;
        }
    }

    /**
     * Test Plaid integration
     */
    protected function testPlaid(): bool
    {
        $this->info('🔧 Testing Plaid Integration...');

        try {
            $plaidService = new PlaidBankingService();
            
            // Test credentials
            $isValid = $plaidService->validateCredentials();
            
            if ($isValid) {
                $this->info('✅ Plaid credentials are valid');
                
                // Test link token creation
                $linkToken = $plaidService->createLinkToken(1);
                if (isset($linkToken['link_token'])) {
                    $this->info('✅ Link token creation successful');
                } else {
                    $this->error('❌ Link token creation failed');
                    return false;
                }
                
                return true;
            } else {
                $this->error('❌ Plaid credentials are invalid');
                $this->comment('Make sure PLAID_CLIENT_ID and PLAID_SECRET are set in your .env file');
                return false;
            }
        } catch (\Exception $e) {
            $this->error('❌ Plaid integration test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test OpenAI integration
     */
    protected function testOpenAI(): bool
    {
        $this->info('🤖 Testing OpenAI Integration...');

        try {
            $openAiService = new OpenAiCategorizationService();
            
            // Test credentials
            $isValid = $openAiService->validateCredentials();
            
            if ($isValid) {
                $this->info('✅ OpenAI credentials are valid');
                
                // Test category suggestions
                $suggestions = $openAiService->suggestCategories('expense', 5);
                if (!empty($suggestions)) {
                    $this->info('✅ Category suggestions working');
                    $this->comment('Sample suggestions: ' . implode(', ', array_slice($suggestions, 0, 3)));
                } else {
                    $this->comment('⚠️  Category suggestions returned empty (using defaults)');
                }
                
                return true;
            } else {
                $this->error('❌ OpenAI credentials are invalid or not configured');
                $this->comment('Make sure OPENAI_API_KEY is set in your .env file');
                $this->comment('OpenAI integration is optional - transactions will be imported without auto-categorization');
                return false;
            }
        } catch (\Exception $e) {
            $this->error('❌ OpenAI integration test failed: ' . $e->getMessage());
            $this->comment('OpenAI integration is optional - transactions will be imported without auto-categorization');
            return false;
        }
    }
}