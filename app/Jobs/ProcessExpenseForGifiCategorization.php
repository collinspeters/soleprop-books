<?php

namespace App\Jobs;

use App\Models\Banking\Transaction;
use App\Models\Setting\Category;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessExpenseForGifiCategorization implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Transaction $transaction;

    /**
     * Create a new job instance.
     */
    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Only process expense transactions
            if (!$this->isExpenseTransaction()) {
                Log::info("Skipping non-expense transaction ID: {$this->transaction->id}");
                return;
            }

            // Get GIFI category suggestion from OpenAI
            $gifiSuggestion = $this->getGifiCategorySuggestion();
            
            if (!$gifiSuggestion) {
                Log::warning("No GIFI suggestion received for transaction ID: {$this->transaction->id}");
                return;
            }

            // Find or create category with GIFI code
            $category = $this->findOrCreateGifiCategory($gifiSuggestion);

            // Update transaction with the suggested category and AI data
            $this->updateTransactionCategory($category, $gifiSuggestion);

            Log::info("Successfully processed expense transaction ID: {$this->transaction->id} with GIFI code: {$gifiSuggestion['gifi_code']}");

        } catch (Exception $e) {
            Log::error("Error processing expense for GIFI categorization: " . $e->getMessage(), [
                'transaction_id' => $this->transaction->id,
                'error' => $e->getTraceAsString()
            ]);
            
            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Check if the transaction is an expense type
     */
    private function isExpenseTransaction(): bool
    {
        return in_array($this->transaction->type, [
            Transaction::EXPENSE_TYPE,
            Transaction::EXPENSE_TRANSFER_TYPE,
            Transaction::EXPENSE_SPLIT_TYPE,
            Transaction::EXPENSE_RECURRING_TYPE
        ]);
    }

    /**
     * Get GIFI category suggestion from OpenAI
     */
    private function getGifiCategorySuggestion(): ?array
    {
        $prompt = $this->buildGifiPrompt();

        try {
            $response = OpenAI::completions()->create([
                'model' => 'gpt-3.5-turbo-instruct',
                'prompt' => $prompt,
                'max_tokens' => 150,
                'temperature' => 0.3,
            ]);

            $content = trim($response->choices[0]->text ?? '');
            return $this->parseGifiResponse($content);

        } catch (Exception $e) {
            Log::error("OpenAI API error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Build the prompt for OpenAI GIFI categorization
     */
    private function buildGifiPrompt(): string
    {
        $description = $this->transaction->description ?? 'No description';
        $amount = $this->transaction->amount ?? 0;
        $contactName = $this->transaction->contact->name ?? 'Unknown';

        return "Analyze this business expense and suggest the most appropriate GIFI (General Index of Financial Information) category code and name.

GIFI is the Canadian tax classification system with codes like:
- 8000-8299: Cost of goods sold
- 8300-8499: Advertising and promotion
- 8500-8699: Bad debts
- 8700-8899: Interest and bank charges
- 9000-9199: Professional fees
- 9200-9399: Management and administration fees
- 9400-9599: Rent
- 9600-9799: Repairs and maintenance
- 9800-9999: Travel and entertainment

Expense Details:
- Description: {$description}
- Amount: \${$amount}
- Vendor/Contact: {$contactName}

Respond in this exact format:
GIFI_CODE: [4-digit code]
CATEGORY_NAME: [descriptive category name]
CONFIDENCE: [High/Medium/Low]

Example:
GIFI_CODE: 9000
CATEGORY_NAME: Professional fees
CONFIDENCE: High";
    }

    /**
     * Parse the OpenAI response to extract GIFI information
     */
    private function parseGifiResponse(string $content): ?array
    {
        $lines = explode("\n", $content);
        $result = [];

        foreach ($lines as $line) {
            $line = trim($line);
            
            if (strpos($line, 'GIFI_CODE:') === 0) {
                $result['gifi_code'] = trim(str_replace('GIFI_CODE:', '', $line));
            } elseif (strpos($line, 'CATEGORY_NAME:') === 0) {
                $result['category_name'] = trim(str_replace('CATEGORY_NAME:', '', $line));
            } elseif (strpos($line, 'CONFIDENCE:') === 0) {
                $result['confidence'] = trim(str_replace('CONFIDENCE:', '', $line));
            }
        }

        // Validate required fields
        if (empty($result['gifi_code']) || empty($result['category_name'])) {
            Log::warning("Invalid GIFI response format", ['content' => $content]);
            return null;
        }

        // Validate GIFI code format (should be 4 digits)
        if (!preg_match('/^\d{4}$/', $result['gifi_code'])) {
            Log::warning("Invalid GIFI code format: {$result['gifi_code']}");
            return null;
        }

        return $result;
    }

    /**
     * Find existing category with GIFI code or create a new one
     */
    private function findOrCreateGifiCategory(array $gifiSuggestion): Category
    {
        // First, try to find existing category with this GIFI code
        $category = Category::where('gifi_code', $gifiSuggestion['gifi_code'])
                           ->where('company_id', $this->transaction->company_id)
                           ->first();

        if ($category) {
            return $category;
        }

        // Create new category with GIFI code
        return Category::create([
            'company_id' => $this->transaction->company_id,
            'name' => $gifiSuggestion['category_name'],
            'gifi_code' => $gifiSuggestion['gifi_code'],
            'type' => 'expense',
            'enabled' => 1,
            'created_from' => 'gifi_ai_categorization',
            'created_by' => $this->transaction->created_by ?? 1,
        ]);
    }

    /**
     * Update transaction with the suggested category and AI data
     */
    private function updateTransactionCategory(Category $category, array $gifiSuggestion): void
    {
        $this->transaction->update([
            'category_id' => $category->id,
            'ai_category' => $gifiSuggestion['category_name'],
            'ai_confidence' => $this->convertConfidenceToNumeric($gifiSuggestion['confidence'] ?? 'Medium'),
            'ai_explanation' => $this->generateAiExplanation($gifiSuggestion),
        ]);
    }

    /**
     * Convert confidence level to numeric value
     */
    private function convertConfidenceToNumeric(string $confidence): float
    {
        return match (strtolower(trim($confidence))) {
            'high' => 0.9,
            'medium' => 0.7,
            'low' => 0.5,
            default => 0.7,
        };
    }

    /**
     * Generate explanation for AI categorization decision
     */
    private function generateAiExplanation(array $gifiSuggestion): string
    {
        $explanation = "AI suggested GIFI category: {$gifiSuggestion['gifi_code']} - {$gifiSuggestion['category_name']}";
        $explanation .= " with {$gifiSuggestion['confidence']} confidence";
        $explanation .= " based on expense description: '{$this->transaction->description}'";
        $explanation .= " and amount: \${$this->transaction->amount}";
        
        return $explanation;
    }

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(30);
    }
}
