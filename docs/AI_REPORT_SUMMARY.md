# AI Report Summary Feature

This feature allows users to ask AI to summarize their financial reports in plain language, making it easier to understand complex financial data and get actionable insights.

## Features

- **Automatic Report Analysis**: AI analyzes report data including totals, net profit, and trends
- **Custom Questions**: Users can ask specific questions about their reports
- **Plain Language Explanations**: Complex financial data is explained in simple, business-friendly language
- **Multi-language Support**: Uses Laravel's translation system for internationalization
- **Configurable**: Can be enabled/disabled per company and supports different AI providers

## Setup

### 1. Environment Configuration

Add your OpenAI API key to your `.env` file:

```env
OPENAI_API_KEY=your_openai_api_key_here
```

### 2. Database Migration

Run the migration to add AI settings:

```bash
php artisan migrate
```

### 3. Enable AI Features

Update the settings in your database or through the admin panel:

```sql
UPDATE settings SET value = 'true' WHERE key = 'ai.reports.enabled';
```

Or programmatically:

```php
setting(['ai.reports.enabled' => true]);
setting(['ai.reports.provider' => 'openai']);
setting(['ai.reports.model' => 'gpt-3.5-turbo']);
```

## Usage

### For Users

1. Navigate to any report (P&L, Tax Summary, Income Summary, etc.)
2. Look for the "AI Report Analysis" section below the report filters
3. Click to expand the AI section
4. Optionally enter a specific question about the report
5. Click "Generate AI Summary" to get insights

### Example Questions Users Can Ask

- "What are the main trends in my expenses?"
- "How can I improve profitability?"
- "What should I focus on this quarter?"
- "Are there any concerning patterns in my finances?"
- "What are my biggest opportunities for growth?"

## Technical Details

### Supported Reports

The AI feature works with all report types:
- Profit & Loss Reports
- Tax Summary Reports
- Income Summary Reports
- Expense Summary Reports
- Income vs Expense Reports
- Discount Summary Reports

### AI Analysis Includes

1. **Financial Performance Overview**: General assessment of the numbers
2. **Key Insights and Trends**: Important patterns and changes
3. **Areas of Concern or Opportunity**: Issues to address and opportunities to pursue
4. **Actionable Recommendations**: Specific steps the business can take

### Data Extraction

The AI service extracts the following data from reports:
- Report name and type
- Accounting period and basis
- Financial totals by category
- Net profit calculations (for P&L reports)
- Row-level data for detailed analysis

### Security and Privacy

- API calls are made server-side to protect API keys
- User data is only sent to the AI service temporarily for analysis
- No financial data is stored by the AI provider
- Access is controlled by user permissions and company settings

## Configuration Options

### Settings

- `ai.reports.enabled`: Enable/disable AI features (boolean)
- `ai.reports.provider`: AI provider to use (currently 'openai')
- `ai.reports.model`: AI model to use (e.g., 'gpt-3.5-turbo', 'gpt-4')

### Customization

You can customize the AI prompts by modifying the `buildPrompt()` method in `App\Services\ReportAiService`.

### Adding New AI Providers

To add support for other AI providers:

1. Update the `ReportAiService` constructor to handle different providers
2. Modify the API request format in the `summarizeReport()` method
3. Add provider-specific configuration options

## Troubleshooting

### Common Issues

1. **"AI service is not configured"**
   - Check that `OPENAI_API_KEY` is set in your `.env` file
   - Verify that `ai.reports.enabled` is set to `true` in settings

2. **"Failed to connect to AI service"**
   - Check your internet connection
   - Verify the OpenAI API key is valid and has sufficient credits
   - Check server logs for detailed error messages

3. **AI summary section not visible**
   - Ensure AI is enabled in settings
   - Check that the OpenAI API key is configured
   - Verify user has permission to view reports

### Logs

AI-related errors are logged to the Laravel log files. Check `storage/logs/laravel.log` for detailed error information.

## Future Enhancements

Potential improvements for future versions:
- Support for more AI providers (Claude, Gemini, etc.)
- Report comparison analysis
- Automated report insights via email
- Custom AI prompt templates
- Historical trend analysis
- Predictive analytics and forecasting