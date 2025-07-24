# Receipt Upload Feature

This feature adds comprehensive receipt upload functionality to the accounting system with OCR processing, AI categorization, and automatic transaction matching.

## Features

### 📁 Receipt Upload
- Support for image files (JPG, PNG, GIF) and PDF documents
- File validation and size limits (configurable, default 10MB)
- Secure file storage using Laravel's mediable package

### 🔍 OCR Processing
- Automatic text extraction from receipt images and PDFs
- Integration with OCR.space API (with fallback mock data)
- Extracts key information: vendor name, amount, date, tax, line items

### 🤖 AI Categorization
- Automatic expense categorization using OpenAI GPT models
- Fallback to rule-based categorization system
- Confidence scoring for AI predictions

### 🔗 Transaction Matching
- Automatic matching to existing transactions based on:
  - Exact amount match
  - Vendor name similarity (fuzzy matching)
  - Date within ±5 days tolerance
- Creates new transactions when no match is found
- Handles receipt-to-transaction linking and unlinking

### ⚡ Processing Options
- Asynchronous processing using Laravel queues (recommended)
- Synchronous processing for immediate results
- Retry mechanism for failed processing

## API Endpoints

### Receipt Management
```
GET    /api/receipts              # List receipts with filtering
POST   /api/receipts              # Upload new receipt
GET    /api/receipts/{id}         # Get receipt details
PUT    /api/receipts/{id}         # Update receipt
DELETE /api/receipts/{id}         # Delete receipt
```

### Processing Operations
```
POST   /api/receipts/{id}/retry       # Retry failed processing
POST   /api/receipts/{id}/reprocess   # Reprocess receipt
GET    /api/receipts/{id}/download    # Download receipt file
```

### Transaction Matching
```
POST   /api/receipts/{id}/match     # Manually match to transaction
POST   /api/receipts/{id}/unmatch   # Unmatch from transaction
```

### Statistics
```
GET    /api/receipts/stats          # Get processing statistics
```

## Database Schema

### Receipts Table
```sql
CREATE TABLE receipts (
    id BIGINT PRIMARY KEY,
    company_id INT NOT NULL,
    transaction_id INT NULL,
    vendor_name VARCHAR(255) NULL,
    amount DECIMAL(15,4) NULL,
    currency_code VARCHAR(3) NULL,
    receipt_date DATE NULL,
    category_id INT NULL,
    description TEXT NULL,
    status ENUM('pending', 'processing', 'processed', 'matched', 'failed'),
    ocr_data JSON NULL,
    ai_category_confidence DECIMAL(5,4) NULL,
    created_from VARCHAR(100) DEFAULT 'receipt_upload',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL
);
```

## Configuration

### Environment Variables
Copy settings from `.env.receipt.example` to your `.env` file:

```env
# Processing
RECEIPT_ASYNC_PROCESSING=true
RECEIPT_MAX_SIZE=10240

# OCR Service
OCR_API_KEY=your_ocr_api_key_here

# AI Categorization
OPENAI_API_KEY=your_openai_api_key_here
OPENAI_MODEL=gpt-3.5-turbo

# Queue (for async processing)
QUEUE_CONNECTION=database
```

### Service Setup

1. **OCR Service**: Get a free API key from [OCR.space](https://ocr.space/ocrapi)
2. **AI Service**: Get an API key from [OpenAI](https://platform.openai.com/)
3. **Queue Workers**: Run `php artisan queue:work --queue=receipts` for async processing

## Installation

1. **Run Migration**:
   ```bash
   php artisan migrate
   ```

2. **Configure Services**:
   - Add API keys to `.env` file
   - Configure queue connection if using async processing

3. **Start Queue Workers** (for async processing):
   ```bash
   php artisan queue:work --queue=receipts
   ```

4. **Schedule Unmatched Receipt Processing** (optional):
   ```bash
   # Add to crontab or Laravel scheduler
   php artisan receipts:process-unmatched
   ```

## Usage Examples

### Upload Receipt
```bash
curl -X POST /api/receipts \
  -H "Authorization: Bearer {token}" \
  -F "receipt=@receipt.jpg" \
  -F "description=Lunch meeting expense"
```

### Get Processing Statistics
```bash
curl -X GET /api/receipts/stats?period=month \
  -H "Authorization: Bearer {token}"
```

## Processing Flow

1. **Upload**: Receipt file is uploaded and stored
2. **OCR**: Text is extracted from the receipt
3. **AI Categorization**: Expense is automatically categorized
4. **Transaction Matching**: System attempts to match with existing transactions
5. **Result**: Receipt is either matched to existing transaction or new transaction is created

## Matching Logic

### Automatic Matching Criteria
- **Amount**: Exact match required
- **Vendor**: Case-insensitive, trimmed comparison
- **Date**: Within ±5 days of receipt date

### Fuzzy Matching
When exact matching fails, the system uses fuzzy matching with scoring:
- Amount similarity (40% weight)
- Date proximity (30% weight)
- Vendor/description similarity (30% weight)

Minimum match score: 70% (configurable)

## Monitoring

### Console Commands
```bash
# Process unmatched receipts
php artisan receipts:process-unmatched

# View processing statistics
php artisan receipts:process-unmatched --dry-run
```

### Log Files
- OCR processing: `storage/logs/laravel.log`
- AI categorization: `storage/logs/laravel.log`
- Transaction matching: `storage/logs/laravel.log`

## Troubleshooting

### Common Issues

1. **OCR Processing Fails**: Check OCR API key and internet connection
2. **AI Categorization Fails**: Verify OpenAI API key and model availability
3. **Files Not Uploading**: Check file size limits and storage permissions
4. **Async Processing Stuck**: Ensure queue workers are running

### Debug Mode
Enable debug logging by setting `APP_DEBUG=true` in `.env` file.

## Security Considerations

- Files are stored securely using Laravel's storage system
- API endpoints require proper authentication
- File type validation prevents malicious uploads
- Size limits prevent abuse

## Performance

- Async processing recommended for production
- OCR processing typically takes 2-5 seconds per receipt
- AI categorization adds 1-3 seconds per receipt
- File storage is optimized for quick retrieval