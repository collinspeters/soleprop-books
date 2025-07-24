<?php

namespace App\Services;

use App\Jobs\Banking\CreateTransaction;
use App\Models\Banking\Account;
use App\Models\Banking\Transaction;
use App\Models\Setting\Category;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Exception;

class PlaidBankingService
{
    protected $client;
    protected $clientId;
    protected $secret;
    protected $environment;
    protected $baseUrl;
    protected $openAiService;

    public function __construct()
    {
        $this->client = new Client();
        $this->clientId = config('services.plaid.client_id');
        $this->secret = config('services.plaid.secret');
        $this->environment = config('services.plaid.environment', 'sandbox');
        
        // Set base URL based on environment
        $this->baseUrl = match($this->environment) {
            'production' => 'https://production.plaid.com',
            'development' => 'https://development.plaid.com',
            default => 'https://sandbox.plaid.com'
        };

        $this->openAiService = new OpenAiCategorizationService();
    }

    /**
     * Create a link token for Plaid Link initialization
     */
    public function createLinkToken(int $userId, array $products = ['transactions']): array
    {
        try {
            $response = $this->client->post($this->baseUrl . '/link/token/create', [
                'json' => [
                    'client_id' => $this->clientId,
                    'secret' => $this->secret,
                    'client_name' => config('app.name'),
                    'country_codes' => ['US', 'CA', 'GB'],
                    'language' => 'en',
                    'user' => [
                        'client_user_id' => (string) $userId
                    ],
                    'products' => $products,
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Plaid link token creation failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            throw new Exception('Failed to create Plaid link token: ' . $e->getMessage());
        }
    }

    /**
     * Exchange public token for access token
     */
    public function exchangePublicToken(string $publicToken): array
    {
        try {
            $response = $this->client->post($this->baseUrl . '/link/token/exchange', [
                'json' => [
                    'client_id' => $this->clientId,
                    'secret' => $this->secret,
                    'public_token' => $publicToken,
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Plaid token exchange failed', [
                'error' => $e->getMessage(),
                'public_token' => $publicToken
            ]);
            throw new Exception('Failed to exchange public token: ' . $e->getMessage());
        }
    }

    /**
     * Get account information from Plaid
     */
    public function getAccounts(string $accessToken): array
    {
        try {
            $response = $this->client->post($this->baseUrl . '/accounts/get', [
                'json' => [
                    'client_id' => $this->clientId,
                    'secret' => $this->secret,
                    'access_token' => $accessToken,
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Plaid accounts fetch failed', [
                'error' => $e->getMessage(),
                'access_token' => substr($accessToken, 0, 10) . '...'
            ]);
            throw new Exception('Failed to fetch accounts: ' . $e->getMessage());
        }
    }

    /**
     * Get transactions from Plaid
     */
    public function getTransactions(string $accessToken, Carbon $startDate, Carbon $endDate, array $accountIds = []): array
    {
        try {
            $requestData = [
                'client_id' => $this->clientId,
                'secret' => $this->secret,
                'access_token' => $accessToken,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'count' => 500,
                'offset' => 0,
            ];

            if (!empty($accountIds)) {
                $requestData['account_ids'] = $accountIds;
            }

            $response = $this->client->post($this->baseUrl . '/transactions/get', [
                'json' => $requestData
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error('Plaid transactions fetch failed', [
                'error' => $e->getMessage(),
                'access_token' => substr($accessToken, 0, 10) . '...',
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d')
            ]);
            throw new Exception('Failed to fetch transactions: ' . $e->getMessage());
        }
    }

    /**
     * Import transactions from Plaid and create them in the system
     */
    public function importTransactions(string $accessToken, Carbon $startDate = null, Carbon $endDate = null, array $accountIds = []): array
    {
        $startDate = $startDate ?? Carbon::now()->subDays(30);
        $endDate = $endDate ?? Carbon::now();

        try {
            // Get accounts first to map Plaid accounts to local accounts
            $accountsResponse = $this->getAccounts($accessToken);
            $plaidAccounts = $accountsResponse['accounts'];

            // Get transactions
            $transactionsResponse = $this->getTransactions($accessToken, $startDate, $endDate, $accountIds);
            $transactions = $transactionsResponse['transactions'];

            $importResults = [
                'imported' => 0,
                'duplicates' => 0,
                'errors' => 0,
                'categorized' => 0,
                'details' => []
            ];

            foreach ($transactions as $plaidTransaction) {
                try {
                    $result = $this->processTransaction($plaidTransaction, $plaidAccounts);
                    
                    if ($result['status'] === 'imported') {
                        $importResults['imported']++;
                        if ($result['categorized']) {
                            $importResults['categorized']++;
                        }
                    } elseif ($result['status'] === 'duplicate') {
                        $importResults['duplicates']++;
                    } else {
                        $importResults['errors']++;
                    }

                    $importResults['details'][] = $result;

                } catch (Exception $e) {
                    $importResults['errors']++;
                    $importResults['details'][] = [
                        'transaction_id' => $plaidTransaction['transaction_id'],
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                    
                    Log::error('Transaction import failed', [
                        'plaid_transaction_id' => $plaidTransaction['transaction_id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return $importResults;

        } catch (Exception $e) {
            Log::error('Plaid transaction import failed', [
                'error' => $e->getMessage(),
                'access_token' => substr($accessToken, 0, 10) . '...'
            ]);
            throw $e;
        }
    }

    /**
     * Process a single transaction from Plaid
     */
    protected function processTransaction(array $plaidTransaction, array $plaidAccounts): array
    {
        // Check for duplicate by Plaid transaction ID
        $existingTransaction = Transaction::where('reference', $plaidTransaction['transaction_id'])->first();
        
        if ($existingTransaction) {
            return [
                'transaction_id' => $plaidTransaction['transaction_id'],
                'status' => 'duplicate',
                'message' => 'Transaction already exists',
                'local_transaction_id' => $existingTransaction->id
            ];
        }

        // Find corresponding local account
        $plaidAccount = collect($plaidAccounts)->firstWhere('account_id', $plaidTransaction['account_id']);
        $localAccount = $this->findOrCreateLocalAccount($plaidAccount);

        if (!$localAccount) {
            throw new Exception('Could not find or create local account for Plaid account: ' . $plaidTransaction['account_id']);
        }

        // Determine transaction type (income vs expense)
        $amount = abs($plaidTransaction['amount']);
        $type = $plaidTransaction['amount'] > 0 ? Transaction::EXPENSE_TYPE : Transaction::INCOME_TYPE;

        // Auto-categorize using OpenAI
        $category = $this->categorizeTransaction($plaidTransaction);

        // Prepare transaction data
        $transactionData = [
            'company_id' => company_id(),
            'type' => $type,
            'number' => $this->generateTransactionNumber($type),
            'account_id' => $localAccount->id,
            'paid_at' => Carbon::parse($plaidTransaction['date'])->format('Y-m-d H:i:s'),
            'amount' => $amount,
            'currency_code' => $localAccount->currency_code,
            'currency_rate' => 1,
            'description' => $this->cleanDescription($plaidTransaction),
            'category_id' => $category ? $category->id : null,
            'payment_method' => $this->mapPaymentMethod($plaidTransaction),
            'reference' => $plaidTransaction['transaction_id'],
            'created_from' => 'plaid-import',
            'created_by' => auth()->id() ?? 1,
        ];

        // Create transaction using the existing job
        $request = new Request($transactionData);
        $createTransactionJob = new CreateTransaction($request);
        $transaction = $createTransactionJob->handle();

        return [
            'transaction_id' => $plaidTransaction['transaction_id'],
            'status' => 'imported',
            'message' => 'Transaction imported successfully',
            'local_transaction_id' => $transaction->id,
            'categorized' => $category !== null,
            'category_name' => $category ? $category->name : null
        ];
    }

    /**
     * Find or create a local account based on Plaid account data
     */
    protected function findOrCreateLocalAccount(array $plaidAccount): ?Account
    {
        // Try to find existing account by Plaid account ID stored in reference field
        $existingAccount = Account::where('reference', $plaidAccount['account_id'])->first();
        
        if ($existingAccount) {
            return $existingAccount;
        }

        // Create new account
        try {
            $accountData = [
                'company_id' => company_id(),
                'name' => $plaidAccount['name'],
                'number' => $plaidAccount['mask'] ?? '',
                'currency_code' => 'USD', // Default to USD, can be configured
                'opening_balance' => $plaidAccount['balances']['current'] ?? 0,
                'bank_name' => $plaidAccount['official_name'] ?? $plaidAccount['name'],
                'bank_phone' => '',
                'bank_address' => '',
                'enabled' => 1,
                'reference' => $plaidAccount['account_id'],
                'created_from' => 'plaid-import',
                'created_by' => auth()->id() ?? 1,
            ];

            return Account::create($accountData);
        } catch (Exception $e) {
            Log::error('Failed to create account from Plaid data', [
                'plaid_account' => $plaidAccount,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Auto-categorize transaction using OpenAI
     */
    protected function categorizeTransaction(array $plaidTransaction): ?Category
    {
        try {
            return $this->openAiService->categorizeTransaction($plaidTransaction);
        } catch (Exception $e) {
            Log::warning('Auto-categorization failed', [
                'transaction_id' => $plaidTransaction['transaction_id'],
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Clean and format transaction description
     */
    protected function cleanDescription(array $plaidTransaction): string
    {
        $description = $plaidTransaction['name'] ?? '';
        
        // Add merchant info if available
        if (!empty($plaidTransaction['merchant_name'])) {
            $description = $plaidTransaction['merchant_name'];
        }

        // Add category info if available
        if (!empty($plaidTransaction['category']) && is_array($plaidTransaction['category'])) {
            $category = implode(' > ', $plaidTransaction['category']);
            $description .= ' (' . $category . ')';
        }

        return trim($description);
    }

    /**
     * Map Plaid payment method to local payment method
     */
    protected function mapPaymentMethod(array $plaidTransaction): string
    {
        $accountType = $plaidTransaction['account_id'] ?? '';
        
        // Map based on transaction type or account type
        return match(true) {
            str_contains(strtolower($accountType), 'credit') => 'credit-card',
            str_contains(strtolower($accountType), 'checking') => 'bank-transfer',
            str_contains(strtolower($accountType), 'savings') => 'bank-transfer',
            default => 'other'
        };
    }

    /**
     * Generate transaction number
     */
    protected function generateTransactionNumber(string $type): string
    {
        $prefix = $type === Transaction::INCOME_TYPE ? 'INC' : 'EXP';
        $lastTransaction = Transaction::where('type', $type)
            ->where('number', 'like', $prefix . '%')
            ->orderBy('number', 'desc')
            ->first();

        if ($lastTransaction) {
            $lastNumber = (int) str_replace($prefix, '', $lastTransaction->number);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Sync account balances with Plaid
     */
    public function syncAccountBalances(string $accessToken): array
    {
        try {
            $accountsResponse = $this->getAccounts($accessToken);
            $plaidAccounts = $accountsResponse['accounts'];
            
            $syncResults = [];

            foreach ($plaidAccounts as $plaidAccount) {
                $localAccount = Account::where('reference', $plaidAccount['account_id'])->first();
                
                if ($localAccount) {
                    $currentBalance = $plaidAccount['balances']['current'] ?? 0;
                    $localAccount->update(['opening_balance' => $currentBalance]);
                    
                    $syncResults[] = [
                        'account_id' => $localAccount->id,
                        'name' => $localAccount->name,
                        'old_balance' => $localAccount->opening_balance,
                        'new_balance' => $currentBalance,
                        'status' => 'updated'
                    ];
                }
            }

            return $syncResults;
        } catch (Exception $e) {
            Log::error('Account balance sync failed', [
                'error' => $e->getMessage(),
                'access_token' => substr($accessToken, 0, 10) . '...'
            ]);
            throw $e;
        }
    }

    /**
     * Validate Plaid credentials
     */
    public function validateCredentials(): bool
    {
        try {
            // Try to create a link token as a credential test
            $response = $this->client->post($this->baseUrl . '/link/token/create', [
                'json' => [
                    'client_id' => $this->clientId,
                    'secret' => $this->secret,
                    'client_name' => config('app.name'),
                    'country_codes' => ['US'],
                    'language' => 'en',
                    'user' => [
                        'client_user_id' => 'test_user'
                    ],
                    'products' => ['transactions'],
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            return isset($result['link_token']);
        } catch (RequestException $e) {
            Log::error('Plaid credentials validation failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}