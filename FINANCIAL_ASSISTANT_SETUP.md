# Financial Assistant Setup Guide

This guide explains how to set up and use the AI-powered Financial Assistant in Akaunting.

## Features

The Financial Assistant provides intelligent insights about your financial data, including:

- **Expense Analysis**: "What were my biggest expenses last month?"
- **Income & Expense Summaries**: "Can you give me a summary of income and expenses for Q1?"
- **Transaction Review**: "Are there any uncategorized transactions I should review?"
- **Cash Flow Analysis**: "What's my current cash flow situation?"
- **Spending Trends**: "Show me my spending trends for the past 6 months"
- **Category Analysis**: "Which categories am I overspending in?"

## Setup Instructions

### 1. OpenAI API Key (Optional)

For full AI capabilities, you'll need an OpenAI API key:

1. Visit [OpenAI API](https://platform.openai.com/api-keys)
2. Create an account and generate an API key
3. Add the following to your `.env` file:

```env
OPENAI_API_KEY=your_openai_api_key_here
```

**Note**: The assistant will work without an OpenAI API key using intelligent mock responses based on your actual financial data.

### 2. Build Assets

After adding the financial assistant components, rebuild your assets:

```bash
npm run dev
# or for production
npm run production
```

### 3. Clear Cache

Clear Laravel's cache to ensure the new routes are loaded:

```bash
php artisan cache:clear
php artisan route:clear
php artisan config:clear
```

## Usage

### Accessing the Assistant

1. **Toggle Button**: Click the blue chat icon on the right side of your screen
2. **Keyboard Shortcut**: Press `Ctrl + /` to toggle the assistant
3. **Quick Questions**: Use the pre-defined questions for common inquiries

### Sample Questions

#### Expense Analysis
- "What were my biggest expenses last month?"
- "Show me my top spending categories"
- "Which expenses increased compared to last month?"

#### Income Analysis
- "How much income did I generate this quarter?"
- "Compare my income from last month to this month"
- "What are my main income sources?"

#### Cash Flow
- "What's my current cash flow situation?"
- "Am I spending more than I'm earning?"
- "Show me my net profit for the last 3 months"

#### Transaction Management
- "Are there any uncategorized transactions?"
- "Show me recent large transactions"
- "Which transactions need my attention?"

#### Trends and Insights
- "Show me my spending trends for the past 6 months"
- "How does this month compare to last month?"
- "What patterns do you see in my finances?"

## Technical Details

### Components Added

1. **Vue Component**: `AkauntingFinancialAssistant.vue`
   - Collapsible sidebar interface
   - Real-time chat functionality
   - Quick question buttons
   - Loading states and error handling

2. **Laravel Controller**: `FinancialAssistantController.php`
   - Processes financial data queries
   - Integrates with OpenAI API
   - Provides intelligent mock responses
   - Analyzes transaction patterns

3. **API Route**: `/api/financial-assistant`
   - Handles POST requests with user questions
   - Returns AI-generated responses
   - Includes financial context

### Data Analysis

The assistant analyzes:
- **Current month** income, expenses, and net
- **Previous month** comparisons
- **Quarterly** summaries (last 3 months)
- **Top expense categories** by amount
- **Uncategorized transactions** requiring attention
- **Account balances** and currency information
- **Recent transaction patterns**

### Security Features

- Company-specific data isolation
- Input validation and sanitization
- Rate limiting on API requests
- Secure OpenAI API integration
- Error handling and fallback responses

## Customization

### Adding New Question Types

To add new question patterns, modify the `generateMockResponse()` method in `FinancialAssistantController.php`:

```php
if (strpos($message, 'your_keyword') !== false) {
    // Your custom logic here
    return "Your custom response";
}
```

### Styling Changes

The assistant uses Tailwind CSS classes. Modify the styles in `AkauntingFinancialAssistant.vue`:

```vue
<style scoped>
/* Your custom styles here */
</style>
```

### OpenAI Prompt Customization

Modify the `buildSystemPrompt()` method to customize how the AI interprets your financial data:

```php
private function buildSystemPrompt($context)
{
    $prompt = "Your custom system prompt...";
    // Add your custom instructions
    return $prompt;
}
```

## Troubleshooting

### Common Issues

1. **Component not showing**: 
   - Ensure assets are built: `npm run dev`
   - Clear browser cache
   - Check console for JavaScript errors

2. **API errors**:
   - Verify the route is registered in `routes/api.php`
   - Check Laravel logs: `tail -f storage/logs/laravel.log`
   - Ensure proper authentication

3. **OpenAI API issues**:
   - Verify API key in `.env` file
   - Check OpenAI account usage limits
   - Review API response in network tab

4. **No financial data**:
   - Ensure transactions exist in the database
   - Check company_id is correctly passed
   - Verify transaction relationships (categories, accounts)

### Performance Optimization

For large datasets:
- Implement caching for frequent queries
- Add database indexes on transaction dates
- Consider pagination for transaction lists
- Use database query optimization

## Support

For issues or feature requests:
1. Check the Laravel and Vue.js logs
2. Verify all dependencies are installed
3. Ensure proper database migrations are run
4. Review the OpenAI API documentation for latest features

The Financial Assistant enhances your Akaunting experience by providing intelligent insights into your financial data, helping you make better business decisions.