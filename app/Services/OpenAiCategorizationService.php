<?php

namespace App\Services;

use App\Models\Setting\Category;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class OpenAiCategorizationService
{
    protected $client;
    protected $apiKey;
    protected $model;
    protected $baseUrl;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-3.5-turbo');
        $this->baseUrl = 'https://api.openai.com/v1';
    }

    /**
     * Categorize a transaction using OpenAI
     */
    public function categorizeTransaction(array $plaidTransaction): ?Category
    {
        if (empty($this->apiKey)) {
            Log::warning('OpenAI API key not configured, skipping auto-categorization');
            return null;
        }

        // Create cache key for this transaction pattern
        $cacheKey = $this->generateCacheKey($plaidTransaction);
        
        // Check cache first
        $cachedCategoryId = Cache::get($cacheKey);
        if ($cachedCategoryId) {
            return Category::find($cachedCategoryId);
        }

        try {
            // Get available categories for the transaction type
            $transactionType = $plaidTransaction['amount'] > 0 ? 'expense' : 'income';
            $availableCategories = $this->getAvailableCategories($transactionType);

            if ($availableCategories->isEmpty()) {
                Log::warning('No categories available for transaction type: ' . $transactionType);
                return null;
            }

            // Prepare the prompt
            $prompt = $this->buildCategorizationPrompt($plaidTransaction, $availableCategories);

            // Call OpenAI API
            $response = $this->callOpenAI($prompt);
            
            if (!$response) {
                return null;
            }

            // Parse the response and find the category
            $selectedCategory = $this->parseCategorizationResponse($response, $availableCategories);

            // Cache the result for similar transactions
            if ($selectedCategory) {
                Cache::put($cacheKey, $selectedCategory->id, now()->addDays(30));
            }

            return $selectedCategory;

        } catch (Exception $e) {
            Log::error('OpenAI categorization failed', [
                'transaction_id' => $plaidTransaction['transaction_id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Generate cache key for transaction pattern
     */
    protected function generateCacheKey(array $plaidTransaction): string
    {
        $merchant = $plaidTransaction['merchant_name'] ?? $plaidTransaction['name'] ?? '';
        $categories = is_array($plaidTransaction['category']) ? implode(',', $plaidTransaction['category']) : '';
        
        return 'openai_category_' . md5(strtolower($merchant . '|' . $categories));
    }

    /**
     * Get available categories for the transaction type
     */
    protected function getAvailableCategories(string $type): \Illuminate\Database\Eloquent\Collection
    {
        return Category::where('type', $type)
            ->where('enabled', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);
    }

    /**
     * Build the categorization prompt for OpenAI
     */
    protected function buildCategorizationPrompt(array $plaidTransaction, $availableCategories): string
    {
        $transactionName = $plaidTransaction['name'] ?? 'Unknown';
        $merchantName = $plaidTransaction['merchant_name'] ?? '';
        $amount = abs($plaidTransaction['amount']);
        $plaidCategories = is_array($plaidTransaction['category']) ? implode(', ', $plaidTransaction['category']) : '';
        
        $categoriesList = $availableCategories->map(function ($category) {
            return "- {$category->name} (ID: {$category->id})";
        })->implode("\n");

        $prompt = "You are a financial transaction categorization assistant. Your task is to categorize the following transaction into one of the available categories.

Transaction Details:
- Name: {$transactionName}
- Merchant: {$merchantName}
- Amount: \${$amount}
- Plaid Categories: {$plaidCategories}

Available Categories:
{$categoriesList}

Instructions:
1. Analyze the transaction details carefully
2. Choose the MOST APPROPRIATE category from the list above
3. Consider the merchant name, transaction description, and amount
4. Respond with ONLY the category ID number (nothing else)
5. If no category seems appropriate, respond with 'NONE'

Your response (category ID only):";

        return $prompt;
    }

    /**
     * Call OpenAI API
     */
    protected function callOpenAI(string $prompt): ?string
    {
        try {
            $response = $this->client->post($this->baseUrl . '/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => 50,
                    'temperature' => 0.1, // Low temperature for consistent categorization
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            
            if (isset($result['choices'][0]['message']['content'])) {
                return trim($result['choices'][0]['message']['content']);
            }

            Log::warning('Unexpected OpenAI API response structure', ['response' => $result]);
            return null;

        } catch (RequestException $e) {
            Log::error('OpenAI API request failed', [
                'error' => $e->getMessage(),
                'status_code' => $e->getCode()
            ]);
            return null;
        }
    }

    /**
     * Parse OpenAI response and find the corresponding category
     */
    protected function parseCategorizationResponse(string $response, $availableCategories): ?Category
    {
        $response = trim($response);
        
        // Handle 'NONE' response
        if (strtoupper($response) === 'NONE') {
            return null;
        }

        // Try to extract category ID
        if (is_numeric($response)) {
            $categoryId = (int) $response;
            return $availableCategories->firstWhere('id', $categoryId);
        }

        // Fallback: try to match by name if ID parsing fails
        $response = strtolower($response);
        return $availableCategories->first(function ($category) use ($response) {
            return str_contains($response, strtolower($category->name));
        });
    }

    /**
     * Batch categorize multiple transactions
     */
    public function batchCategorizeTransactions(array $plaidTransactions): array
    {
        $results = [];
        
        foreach ($plaidTransactions as $transaction) {
            $category = $this->categorizeTransaction($transaction);
            $results[$transaction['transaction_id']] = [
                'category' => $category,
                'category_id' => $category?->id,
                'category_name' => $category?->name,
            ];
            
            // Add small delay to respect API rate limits
            usleep(100000); // 0.1 second delay
        }

        return $results;
    }

    /**
     * Create suggested categories based on common transaction patterns
     */
    public function suggestCategories(string $transactionType = 'expense', int $limit = 10): array
    {
        if (empty($this->apiKey)) {
            return $this->getDefaultCategorySuggestions($transactionType);
        }

        try {
            $prompt = $this->buildCategorySuggestionPrompt($transactionType, $limit);
            $response = $this->callOpenAI($prompt);
            
            if (!$response) {
                return $this->getDefaultCategorySuggestions($transactionType);
            }

            return $this->parseCategorySuggestions($response);

        } catch (Exception $e) {
            Log::error('Category suggestion failed', [
                'error' => $e->getMessage(),
                'transaction_type' => $transactionType
            ]);
            return $this->getDefaultCategorySuggestions($transactionType);
        }
    }

    /**
     * Build prompt for category suggestions
     */
    protected function buildCategorySuggestionPrompt(string $transactionType, int $limit): string
    {
        $typeDescription = $transactionType === 'expense' ? 'business expenses' : 'business income sources';
        
        return "Generate {$limit} common and useful category names for {$typeDescription} in a small to medium business.

Requirements:
- Each category should be specific enough to be useful but broad enough to cover multiple transactions
- Categories should be practical for accounting and tax purposes
- Respond with one category name per line
- No numbering or bullet points
- Keep names concise (2-4 words maximum)

Category names:";
    }

    /**
     * Parse category suggestions from OpenAI response
     */
    protected function parseCategorySuggestions(string $response): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $response)));
        $suggestions = [];

        foreach ($lines as $line) {
            // Clean up the line (remove any numbering, bullets, etc.)
            $categoryName = preg_replace('/^[\d\.\-\*\s]+/', '', $line);
            $categoryName = trim($categoryName);
            
            if (!empty($categoryName) && strlen($categoryName) <= 50) {
                $suggestions[] = $categoryName;
            }
        }

        return array_slice($suggestions, 0, 10); // Limit to 10 suggestions
    }

    /**
     * Get default category suggestions when OpenAI is not available
     */
    protected function getDefaultCategorySuggestions(string $transactionType): array
    {
        if ($transactionType === 'expense') {
            return [
                'Office Supplies',
                'Travel & Transportation',
                'Meals & Entertainment',
                'Software & Subscriptions',
                'Professional Services',
                'Marketing & Advertising',
                'Utilities',
                'Rent & Facilities',
                'Equipment & Hardware',
                'Insurance'
            ];
        } else {
            return [
                'Product Sales',
                'Service Revenue',
                'Consulting Income',
                'Investment Income',
                'Rental Income',
                'Royalties',
                'Interest Income',
                'Other Income',
                'Refunds & Credits',
                'Government Grants'
            ];
        }
    }

    /**
     * Validate OpenAI credentials
     */
    public function validateCredentials(): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        try {
            $response = $this->client->post($this->baseUrl . '/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => 'Test message'
                        ]
                    ],
                    'max_tokens' => 5,
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            return isset($result['choices'][0]['message']['content']);

        } catch (RequestException $e) {
            Log::error('OpenAI credentials validation failed', [
                'error' => $e->getMessage(),
                'status_code' => $e->getCode()
            ]);
            return false;
        }
    }

    /**
     * Clear categorization cache
     */
    public function clearCategorizationCache(): void
    {
        $keys = Cache::get('openai_category_keys', []);
        
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        
        Cache::forget('openai_category_keys');
    }

    /**
     * Get categorization statistics
     */
    public function getCategorizationStats(): array
    {
        $totalTransactions = \DB::table('transactions')
            ->where('created_from', 'plaid-import')
            ->count();

        $categorizedTransactions = \DB::table('transactions')
            ->where('created_from', 'plaid-import')
            ->whereNotNull('category_id')
            ->count();

        $categorizationRate = $totalTransactions > 0 
            ? round(($categorizedTransactions / $totalTransactions) * 100, 2) 
            : 0;

        return [
            'total_imported_transactions' => $totalTransactions,
            'categorized_transactions' => $categorizedTransactions,
            'categorization_rate' => $categorizationRate,
            'cache_hits' => Cache::get('openai_categorization_cache_hits', 0),
            'api_calls' => Cache::get('openai_categorization_api_calls', 0),
        ];
    }
}