# GIFI Categorization with OpenAI

This feature automatically categorizes expense transactions using OpenAI to suggest GIFI (General Index of Financial Information) compliant categories.

## Overview

When a new expense transaction is created, the system automatically:
1. Sends the expense description and amount to OpenAI
2. Receives a suggested GIFI-compliant category code and name
3. Creates or finds the appropriate category with the GIFI code
4. Updates the transaction with the suggested category

## Files Created/Modified

### New Files
- `app/Jobs/ProcessExpenseForGifiCategorization.php` - Main job that processes expenses
- `database/migrations/2025_07_24_123803_add_gifi_code_to_categories_table.php` - Adds GIFI code field
- `database/migrations/2025_07_24_133841_add_ai_fields_to_transactions_table.php` - Adds AI audit trail fields
- `config/openai.php` - OpenAI configuration
- `.env.example.openai` - Environment configuration example

### Modified Files
- `app/Models/Setting/Category.php` - Added `gifi_code` to fillable attributes
- `app/Models/Banking/Transaction.php` - Added AI fields (`ai_category`, `ai_confidence`, `ai_explanation`) to fillable and casts
- `app/Observers/Transaction.php` - Added `created` method to dispatch job for new expenses
- `app/Jobs/ProcessExpenseForGifiCategorization.php` - Updated to store AI audit trail data

## Setup Instructions

### 1. Install Dependencies
The OpenAI PHP client is already installed via Composer:
```bash
composer require openai-php/client
```

### 2. Environment Configuration
Add these variables to your `.env` file:
```env
OPENAI_API_KEY=sk-your-openai-api-key-here
OPENAI_ORGANIZATION=org-your-organization-id  # Optional
OPENAI_REQUEST_TIMEOUT=30                      # Optional
QUEUE_CONNECTION=redis                         # Recommended for production
```

### 3. Run Migration
```bash
php artisan migrate
```

### 4. Configure Queue (Recommended)
For production, use Redis or another queue driver:
```bash
php artisan queue:work
```

## How It Works

### Job Processing
The `ProcessExpenseForGifiCategorization` job:
- Only processes expense-type transactions
- Sends expense details to OpenAI with a structured prompt
- Parses the response to extract GIFI code and category name
- Validates the GIFI code format (4-digit number)
- Creates or finds existing categories with the same GIFI code
- Updates the transaction with the appropriate category

### GIFI Code Examples
The system recognizes standard Canadian GIFI codes:
- 8000-8299: Cost of goods sold
- 8300-8499: Advertising and promotion
- 8500-8699: Bad debts
- 8700-8899: Interest and bank charges
- 9000-9199: Professional fees
- 9200-9399: Management and administration fees
- 9400-9599: Rent
- 9600-9799: Repairs and maintenance
- 9800-9999: Travel and entertainment

### Error Handling
- Automatic retry mechanism (3 attempts with exponential backoff)
- Comprehensive logging for debugging
- Graceful handling of OpenAI API errors
- Validation of GIFI code format and response structure

## Usage

### Automatic Processing
Once configured, the system automatically processes new expense transactions:

1. Create a new expense transaction
2. The system dispatches the job automatically
3. The job processes in the background
4. The transaction is updated with the suggested category

### Manual Testing
You can manually dispatch the job for testing:

```php
use App\Jobs\ProcessExpenseForGifiCategorization;
use App\Models\Banking\Transaction;

$transaction = Transaction::find(1); // Replace with actual expense transaction ID
ProcessExpenseForGifiCategorization::dispatch($transaction);
```

## Database Schema

### Categories Table Addition
The migration adds a `gifi_code` field to the categories table:
```sql
ALTER TABLE categories ADD COLUMN gifi_code VARCHAR(10) NULL AFTER name;
ALTER TABLE categories ADD INDEX idx_gifi_code (gifi_code);
```

### Transactions Table Addition
The migration adds AI audit trail fields to the transactions table:
```sql
ALTER TABLE transactions ADD COLUMN ai_category VARCHAR(255) NULL AFTER category_id;
ALTER TABLE transactions ADD COLUMN ai_confidence FLOAT(8,4) NULL AFTER ai_category;
ALTER TABLE transactions ADD COLUMN ai_explanation TEXT NULL AFTER ai_confidence;
ALTER TABLE transactions ADD INDEX idx_ai_category (ai_category);
ALTER TABLE transactions ADD INDEX idx_ai_confidence (ai_confidence);
```

## AI Audit Trail Fields

The system stores comprehensive audit trail information:

- **`ai_category`** (string): The AI-suggested category name
- **`ai_confidence`** (float): Confidence level converted to numeric (0.9=High, 0.7=Medium, 0.5=Low)
- **`ai_explanation`** (text): Detailed explanation including GIFI code, confidence, description, and amount

This audit trail allows you to:
- Track AI decision-making for compliance purposes
- Identify patterns in categorization accuracy
- Review and override AI suggestions when needed
- Analyze confidence levels to improve the system

## Monitoring and Logs

### Log Entries
The system logs important events:
- Successful categorizations
- OpenAI API errors
- Invalid responses
- Skipped non-expense transactions

### Queue Monitoring
Monitor job processing:
```bash
php artisan queue:work --verbose
```

### Failed Jobs
Check failed jobs:
```bash
php artisan queue:failed
php artisan queue:retry all  # Retry failed jobs
```

## Customization

### Modify GIFI Prompt
Edit the `buildGifiPrompt()` method in `ProcessExpenseForGifiCategorization.php` to customize the OpenAI prompt.

### Change OpenAI Model
Update the model in the `getGifiCategorySuggestion()` method:
```php
'model' => 'gpt-4',  // or 'gpt-3.5-turbo-instruct'
```

### Adjust Retry Logic
Modify retry settings in the job:
```php
public $tries = 5;                           // Number of attempts
public $backoff = [60, 300, 900, 1800];     // Backoff intervals
```

## Security Considerations

1. **API Key Security**: Store OpenAI API key securely in environment variables
2. **Rate Limiting**: OpenAI has rate limits - monitor usage
3. **Data Privacy**: Expense descriptions are sent to OpenAI - ensure compliance
4. **Queue Security**: Use secure queue connections in production

## Troubleshooting

### Common Issues

1. **"could not find driver" error**: Install required PHP extensions (mysql/pgsql)
2. **OpenAI API errors**: Check API key and rate limits
3. **Jobs not processing**: Ensure queue worker is running
4. **Invalid GIFI codes**: Check OpenAI response format and validation

### Debug Mode
Enable verbose logging by setting log level to debug in `config/logging.php`.

## Performance Considerations

- Jobs run asynchronously to avoid blocking transaction creation
- Caches categories by GIFI code to avoid duplicates
- Uses exponential backoff for failed requests
- Configurable timeout settings

## Future Enhancements

- Batch processing for multiple transactions
- Machine learning model training on categorization patterns
- Integration with accounting software APIs
- Custom GIFI code mappings per company
- Confidence scoring and manual review workflow