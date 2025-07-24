<?php

namespace App\Http\Controllers\Banking;

use App\Abstracts\Http\Controller;
use App\Http\Requests\Banking\PlaidLinkRequest;
use App\Http\Requests\Banking\PlaidImportRequest;
use App\Jobs\Banking\ImportPlaidTransactions;
use App\Services\PlaidBankingService;
use App\Services\OpenAiCategorizationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class PlaidController extends Controller
{
    protected $plaidService;
    protected $openAiService;

    public function __construct()
    {
        $this->plaidService = new PlaidBankingService();
        $this->openAiService = new OpenAiCategorizationService();
    }

    /**
     * Create a link token for Plaid Link initialization
     */
    public function createLinkToken(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            $products = $request->get('products', ['transactions']);

            $response = $this->plaidService->createLinkToken($userId, $products);

            return response()->json([
                'success' => true,
                'data' => $response
            ]);

        } catch (Exception $e) {
            Log::error('Failed to create Plaid link token', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create link token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exchange public token for access token
     */
    public function exchangeToken(PlaidLinkRequest $request): JsonResponse
    {
        try {
            $publicToken = $request->get('public_token');
            $response = $this->plaidService->exchangePublicToken($publicToken);

            // Store the access token securely (you might want to encrypt this)
            // For now, we'll return it to be stored on the frontend
            // In production, you should store this server-side with proper encryption

            return response()->json([
                'success' => true,
                'data' => [
                    'access_token' => $response['access_token'],
                    'item_id' => $response['item_id']
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to exchange Plaid token', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to exchange token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get connected accounts
     */
    public function getAccounts(Request $request): JsonResponse
    {
        try {
            $accessToken = $request->get('access_token');
            
            if (!$accessToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access token is required'
                ], 400);
            }

            $response = $this->plaidService->getAccounts($accessToken);

            return response()->json([
                'success' => true,
                'data' => $response['accounts']
            ]);

        } catch (Exception $e) {
            Log::error('Failed to fetch Plaid accounts', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch accounts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import transactions (async)
     */
    public function importTransactions(PlaidImportRequest $request): JsonResponse
    {
        try {
            $accessToken = $request->get('access_token');
            $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : null;
            $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date')) : null;
            $accountIds = $request->get('account_ids', []);

            // Dispatch job for async processing
            ImportPlaidTransactions::dispatch(
                $accessToken,
                $startDate,
                $endDate,
                $accountIds,
                auth()->id(),
                company_id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Transaction import started. You will be notified when complete.',
                'job_id' => 'plaid_import_' . auth()->id() . '_' . company_id()
            ]);

        } catch (Exception $e) {
            Log::error('Failed to start Plaid transaction import', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start import: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import transactions synchronously (for smaller batches)
     */
    public function importTransactionsSync(PlaidImportRequest $request): JsonResponse
    {
        try {
            $accessToken = $request->get('access_token');
            $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : null;
            $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date')) : null;
            $accountIds = $request->get('account_ids', []);

            $results = $this->plaidService->importTransactions(
                $accessToken,
                $startDate,
                $endDate,
                $accountIds
            );

            return response()->json([
                'success' => true,
                'data' => $results
            ]);

        } catch (Exception $e) {
            Log::error('Failed to import Plaid transactions', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get import status and results
     */
    public function getImportStatus(Request $request): JsonResponse
    {
        try {
            $cacheKey = "plaid_import_results_" . auth()->id() . "_" . company_id();
            $results = cache()->get($cacheKey);

            if (!$results) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'status' => 'pending',
                        'message' => 'Import is still in progress or no recent imports found'
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $results
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get import status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync account balances
     */
    public function syncBalances(Request $request): JsonResponse
    {
        try {
            $accessToken = $request->get('access_token');
            
            if (!$accessToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access token is required'
                ], 400);
            }

            $results = $this->plaidService->syncAccountBalances($accessToken);

            return response()->json([
                'success' => true,
                'data' => $results
            ]);

        } catch (Exception $e) {
            Log::error('Failed to sync account balances', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync balances: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test Plaid credentials
     */
    public function testCredentials(): JsonResponse
    {
        try {
            $isValid = $this->plaidService->validateCredentials();

            return response()->json([
                'success' => true,
                'data' => [
                    'valid' => $isValid,
                    'message' => $isValid ? 'Credentials are valid' : 'Invalid credentials'
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate credentials: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test OpenAI credentials
     */
    public function testOpenAiCredentials(): JsonResponse
    {
        try {
            $isValid = $this->openAiService->validateCredentials();

            return response()->json([
                'success' => true,
                'data' => [
                    'valid' => $isValid,
                    'message' => $isValid ? 'OpenAI credentials are valid' : 'Invalid OpenAI credentials'
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate OpenAI credentials: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get categorization statistics
     */
    public function getCategorizationStats(): JsonResponse
    {
        try {
            $stats = $this->openAiService->getCategorizationStats();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get categorization stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear categorization cache
     */
    public function clearCategorizationCache(): JsonResponse
    {
        try {
            $this->openAiService->clearCategorizationCache();

            return response()->json([
                'success' => true,
                'message' => 'Categorization cache cleared successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get category suggestions from OpenAI
     */
    public function getCategorySuggestions(Request $request): JsonResponse
    {
        try {
            $type = $request->get('type', 'expense');
            $limit = $request->get('limit', 10);

            $suggestions = $this->openAiService->suggestCategories($type, $limit);

            return response()->json([
                'success' => true,
                'data' => $suggestions
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get category suggestions: ' . $e->getMessage()
            ], 500);
        }
    }
}