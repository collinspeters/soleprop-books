# Plaid Banking Integration for Akaunting

This integration allows you to connect bank accounts via Plaid and automatically import transactions into Akaunting with AI-powered categorization using OpenAI.

## Features

- 🏦 **Bank Account Connection**: Connect to 11,000+ financial institutions via Plaid
- 💸 **Automatic Transaction Import**: Import transactions directly into the expenses table
- 🤖 **AI Categorization**: Auto-categorize transactions using OpenAI GPT
- 🔄 **Duplicate Detection**: Prevent duplicate transaction imports
- ⚡ **Async Processing**: Handle large transaction imports in the background
- 📊 **Balance Sync**: Keep account balances synchronized with bank data
- 🔍 **Comprehensive Logging**: Full audit trail of all import activities

## Setup Instructions

### 1. Plaid Configuration

1. Sign up for a Plaid account at [https://dashboard.plaid.com/signup](https://dashboard.plaid.com/signup)
2. Get your API credentials from the Plaid Dashboard
3. Add the following to your `.env` file:

```env
PLAID_CLIENT_ID=your_plaid_client_id
PLAID_SECRET=your_plaid_secret
PLAID_ENVIRONMENT=sandbox  # or development/production
```

### 2. OpenAI Configuration (Optional)

For AI-powered transaction categorization:

1. Get an OpenAI API key from [https://platform.openai.com/api-keys](https://platform.openai.com/api-keys)
2. Add to your `.env` file:

```env
OPENAI_API_KEY=your_openai_api_key
OPENAI_MODEL=gpt-3.5-turbo  # or gpt-4 for better accuracy
```

### 3. Install Dependencies

The integration uses GuzzleHTTP which should already be included in Laravel. If not:

```bash
composer require guzzlehttp/guzzle
```

### 4. Test the Integration

Run the test command to verify your configuration:

```bash
php artisan plaid:test
```

To test only OpenAI:

```bash
php artisan plaid:test --openai
```

## API Usage

### 1. Create Link Token

Create a link token for Plaid Link initialization:

```bash
POST /api/plaid/link-token
```

**Response:**
```json
{
    "success": true,
    "data": {
        "link_token": "link-sandbox-xxxxxxxx",
        "expiration": "2024-01-01T12:00:00Z"
    }
}
```

### 2. Exchange Public Token

After user completes Plaid Link flow:

```bash
POST /api/plaid/exchange-token
Content-Type: application/json

{
    "public_token": "public-sandbox-xxxxxxxx"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "access_token": "access-sandbox-xxxxxxxx",
        "item_id": "item-xxxxxxxx"
    }
}
```

### 3. Get Connected Accounts

```bash
POST /api/plaid/accounts
Content-Type: application/json

{
    "access_token": "access-sandbox-xxxxxxxx"
}
```

### 4. Import Transactions (Async)

For large imports, use the async endpoint:

```bash
POST /api/plaid/import
Content-Type: application/json

{
    "access_token": "access-sandbox-xxxxxxxx",
    "start_date": "2024-01-01",
    "end_date": "2024-01-31",
    "account_ids": ["account_id_1", "account_id_2"]  // optional
}
```

### 5. Check Import Status

```bash
GET /api/plaid/import-status
```

**Response:**
```json
{
    "success": true,
    "data": {
        "imported": 45,
        "duplicates": 3,
        "errors": 0,
        "categorized": 42,
        "details": [...]
    }
}
```

### 6. Import Transactions (Sync)

For smaller imports (< 100 transactions):

```bash
POST /api/plaid/import-sync
Content-Type: application/json

{
    "access_token": "access-sandbox-xxxxxxxx",
    "start_date": "2024-01-01",
    "end_date": "2024-01-07"
}
```

## Service Classes

### PlaidBankingService

Main service class for Plaid integration:

```php
use App\Services\PlaidBankingService;

$plaidService = new PlaidBankingService();

// Import transactions
$results = $plaidService->importTransactions(
    $accessToken,
    Carbon::now()->subDays(30),
    Carbon::now()
);

// Sync account balances
$balanceResults = $plaidService->syncAccountBalances($accessToken);

// Validate credentials
$isValid = $plaidService->validateCredentials();
```

### OpenAiCategorizationService

Service for AI-powered categorization:

```php
use App\Services\OpenAiCategorizationService;

$openAiService = new OpenAiCategorizationService();

// Categorize a single transaction
$category = $openAiService->categorizeTransaction($plaidTransaction);

// Get category suggestions
$suggestions = $openAiService->suggestCategories('expense', 10);

// Get categorization statistics
$stats = $openAiService->getCategorizationStats();
```

## Job Processing

### Async Transaction Import

The `ImportPlaidTransactions` job handles large imports:

```php
use App\Jobs\Banking\ImportPlaidTransactions;

// Dispatch job
ImportPlaidTransactions::dispatch(
    $accessToken,
    $startDate,
    $endDate,
    $accountIds,
    $userId,
    $companyId
);
```

### Queue Configuration

Make sure your queue is configured and running:

```bash
# Start queue worker
php artisan queue:work

# Or use supervisor for production
```

## Transaction Processing

### Duplicate Detection

Transactions are checked for duplicates using the Plaid transaction ID stored in the `reference` field.

### Account Creation

If a Plaid account doesn't exist locally, it will be automatically created with:
- Account name from Plaid
- Account mask as number
- Current balance as opening balance
- Plaid account ID as reference

### Transaction Mapping

Plaid transactions are mapped to local transactions as follows:

| Plaid Field | Local Field | Notes |
|-------------|-------------|-------|
| `transaction_id` | `reference` | Used for duplicate detection |
| `name` / `merchant_name` | `description` | Cleaned and formatted |
| `amount` | `amount` | Converted to positive value |
| `date` | `paid_at` | Transaction date |
| `account_id` | `account_id` | Mapped to local account |
| Amount sign | `type` | Positive = expense, Negative = income |

### Categorization Logic

1. **Cache Check**: Check if similar transaction was categorized before
2. **OpenAI Analysis**: Send transaction details to OpenAI for categorization
3. **Category Mapping**: Map OpenAI response to local categories
4. **Cache Store**: Store result for future similar transactions

## Troubleshooting

### Common Issues

1. **Invalid Credentials**
   - Verify `PLAID_CLIENT_ID` and `PLAID_SECRET` in `.env`
   - Check environment setting (`sandbox`, `development`, `production`)

2. **OpenAI Categorization Failing**
   - Verify `OPENAI_API_KEY` in `.env`
   - Check API quota and billing
   - Transactions will still import without categorization

3. **Duplicate Transactions**
   - System prevents duplicates using Plaid transaction ID
   - If you see duplicates, check the `reference` field in transactions table

4. **Job Not Processing**
   - Make sure queue worker is running: `php artisan queue:work`
   - Check failed jobs: `php artisan queue:failed`

### Debugging

Enable detailed logging by setting:

```env
LOG_LEVEL=debug
```

Check logs in `storage/logs/laravel.log` for detailed error information.

### Testing with Sandbox

Plaid sandbox provides test credentials:

- Username: `user_good`
- Password: `pass_good`

Use these in the Plaid Link flow for testing.

## Security Considerations

1. **Access Token Storage**: Store access tokens securely, preferably encrypted
2. **API Rate Limits**: Respect Plaid and OpenAI rate limits
3. **Error Handling**: Don't expose sensitive API details in error messages
4. **Webhook Security**: Implement webhook signature verification (not included)

## Production Deployment

1. **Environment**: Change `PLAID_ENVIRONMENT` to `production`
2. **Webhooks**: Implement Plaid webhooks for real-time updates
3. **Monitoring**: Set up monitoring for failed jobs and API errors
4. **Backup**: Regular backup of transaction data
5. **Rate Limiting**: Implement API rate limiting for endpoints

## API Endpoints Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/plaid/link-token` | Create link token |
| POST | `/api/plaid/exchange-token` | Exchange public token |
| POST | `/api/plaid/accounts` | Get connected accounts |
| POST | `/api/plaid/import` | Import transactions (async) |
| POST | `/api/plaid/import-sync` | Import transactions (sync) |
| GET | `/api/plaid/import-status` | Get import status |
| POST | `/api/plaid/sync-balances` | Sync account balances |
| GET | `/api/plaid/test-credentials` | Test Plaid credentials |
| GET | `/api/plaid/test-openai` | Test OpenAI credentials |
| GET | `/api/plaid/categorization-stats` | Get categorization stats |
| DELETE | `/api/plaid/clear-cache` | Clear categorization cache |
| GET | `/api/plaid/category-suggestions` | Get AI category suggestions |

## Support

For issues related to:
- **Plaid API**: Check [Plaid Documentation](https://plaid.com/docs/)
- **OpenAI API**: Check [OpenAI Documentation](https://platform.openai.com/docs/)
- **Integration Issues**: Check the application logs and error messages

## License

This integration follows the same license as the main Akaunting application.