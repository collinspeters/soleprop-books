<?php

namespace App\Services\Receipt;

use App\Models\Banking\Receipt;
use App\Models\Setting\Category;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiCategorizationService
{
    protected $apiUrl;
    protected $apiKey;
    protected $model;

    public function __construct()
    {
        $this->apiUrl = config('services.openai.url', 'https://api.openai.com/v1/chat/completions');
        $this->apiKey = config('services.openai.key');
        $this->model = config('services.openai.model', 'gpt-3.5-turbo');
    }

    /**
     * Categorize receipt using AI
     */
    public function categorizeReceipt(Receipt $receipt): ?Category
    {
        try {
            // Get available categories for the company
            $categories = Category::where('company_id', $receipt->company_id)
                ->where('type', 'expense')
                ->where('enabled', 1)
                ->get();

            if ($categories->isEmpty()) {
                return null;
            }

            // Prepare data for AI analysis
            $receiptData = $this->prepareReceiptData($receipt);
            $categoryOptions = $this->prepareCategoryOptions($categories);

            // Get AI prediction
            $prediction = $this->getAiPrediction($receiptData, $categoryOptions);

            if ($prediction) {
                // Find the predicted category
                $predictedCategory = $categories->firstWhere('name', $prediction['category']);
                
                if ($predictedCategory) {
                    // Update receipt with AI confidence
                    $receipt->update([
                        'category_id' => $predictedCategory->id,
                        'ai_category_confidence' => $prediction['confidence'] ?? 0.8,
                    ]);

                    return $predictedCategory;
                }
            }

            // Fallback to rule-based categorization
            return $this->ruleBasedCategorization($receipt, $categories);

        } catch (\Exception $e) {
            Log::error('AI categorization failed', [
                'receipt_id' => $receipt->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback to rule-based categorization
            return $this->ruleBasedCategorization($receipt, $categories->all());
        }
    }

    /**
     * Prepare receipt data for AI analysis
     */
    protected function prepareReceiptData(Receipt $receipt): array
    {
        $ocrData = $receipt->ocr_data ?? [];
        $extractedData = $ocrData['extracted_data'] ?? [];

        return [
            'vendor_name' => $receipt->vendor_name,
            'amount' => $receipt->amount,
            'description' => $receipt->description,
            'items' => $extractedData['items'] ?? [],
            'raw_text' => $ocrData['raw_text'] ?? '',
        ];
    }

    /**
     * Prepare category options for AI
     */
    protected function prepareCategoryOptions($categories): array
    {
        return $categories->map(function ($category) {
            return [
                'name' => $category->name,
                'description' => $category->name, // Could add more context if available
            ];
        })->toArray();
    }

    /**
     * Get AI prediction for category
     */
    protected function getAiPrediction(array $receiptData, array $categoryOptions): ?array
    {
        if (!$this->apiKey) {
            return null; // No AI service configured
        }

        try {
            $prompt = $this->buildPrompt($receiptData, $categoryOptions);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert expense categorization assistant. Analyze receipt data and categorize expenses accurately.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 150,
                'temperature' => 0.3,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $content = $result['choices'][0]['message']['content'] ?? '';
                
                return $this->parseAiResponse($content, $categoryOptions);
            }

        } catch (\Exception $e) {
            Log::error('AI API call failed', [
                'error' => $e->getMessage(),
                'receipt_data' => $receiptData,
            ]);
        }

        return null;
    }

    /**
     * Build prompt for AI categorization
     */
    protected function buildPrompt(array $receiptData, array $categoryOptions): string
    {
        $vendor = $receiptData['vendor_name'] ?? 'Unknown';
        $amount = $receiptData['amount'] ?? 0;
        $items = $receiptData['items'] ?? [];
        $rawText = $receiptData['raw_text'] ?? '';

        $categoryNames = array_column($categoryOptions, 'name');
        $categoriesString = implode(', ', $categoryNames);

        $itemsString = '';
        if (!empty($items)) {
            $itemsString = "\nItems purchased:\n";
            foreach ($items as $item) {
                $itemsString .= "- {$item['description']}: \${$item['amount']}\n";
            }
        }

        return "Please categorize this expense receipt:

Vendor: {$vendor}
Amount: \${$amount}
{$itemsString}

Raw receipt text (first 500 chars):
" . substr($rawText, 0, 500) . "

Available categories: {$categoriesString}

Please respond with just the category name that best fits this expense. Choose only from the available categories listed above.";
    }

    /**
     * Parse AI response to extract category and confidence
     */
    protected function parseAiResponse(string $content, array $categoryOptions): ?array
    {
        $content = trim($content);
        $categoryNames = array_column($categoryOptions, 'name');

        // Look for exact match first
        foreach ($categoryNames as $categoryName) {
            if (stripos($content, $categoryName) !== false) {
                return [
                    'category' => $categoryName,
                    'confidence' => 0.8, // Default confidence for AI prediction
                ];
            }
        }

        // Look for partial matches
        foreach ($categoryNames as $categoryName) {
            $words = explode(' ', strtolower($categoryName));
            foreach ($words as $word) {
                if (strlen($word) > 3 && stripos(strtolower($content), $word) !== false) {
                    return [
                        'category' => $categoryName,
                        'confidence' => 0.6, // Lower confidence for partial match
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Fallback rule-based categorization
     */
    protected function ruleBasedCategorization(Receipt $receipt, $categories): ?Category
    {
        $vendor = strtolower($receipt->vendor_name ?? '');
        $description = strtolower($receipt->description ?? '');
        $ocrData = $receipt->ocr_data ?? [];
        $rawText = strtolower($ocrData['raw_text'] ?? '');

        // Define categorization rules
        $rules = [
            'Office Supplies' => ['office', 'supplies', 'staples', 'depot', 'paper', 'pen'],
            'Travel' => ['hotel', 'airline', 'uber', 'taxi', 'gas', 'fuel', 'parking'],
            'Meals & Entertainment' => ['restaurant', 'cafe', 'coffee', 'lunch', 'dinner', 'food'],
            'Utilities' => ['electric', 'gas', 'water', 'internet', 'phone', 'utility'],
            'Software' => ['software', 'subscription', 'saas', 'license', 'microsoft', 'adobe'],
            'Marketing' => ['advertising', 'marketing', 'facebook', 'google ads', 'promotion'],
            'Professional Services' => ['consulting', 'legal', 'accounting', 'professional'],
            'Equipment' => ['equipment', 'computer', 'laptop', 'hardware', 'machinery'],
        ];

        $searchText = $vendor . ' ' . $description . ' ' . $rawText;

        foreach ($rules as $categoryName => $keywords) {
            // Find category by name
            $category = $categories->firstWhere('name', $categoryName);
            if (!$category) {
                continue;
            }

            // Check if any keywords match
            foreach ($keywords as $keyword) {
                if (strpos($searchText, $keyword) !== false) {
                    $receipt->update([
                        'category_id' => $category->id,
                        'ai_category_confidence' => 0.5, // Lower confidence for rule-based
                    ]);
                    
                    return $category;
                }
            }
        }

        // Default to first expense category if no rules match
        $defaultCategory = $categories->first();
        if ($defaultCategory) {
            $receipt->update([
                'category_id' => $defaultCategory->id,
                'ai_category_confidence' => 0.3, // Low confidence for default
            ]);
        }

        return $defaultCategory;
    }
}