# Monthly Financial Summary Feature

This feature automatically generates and displays monthly financial summaries with AI-powered insights, allowing users to receive them via email or view them in the dashboard.

## Features

- **Automated Monthly Reports**: Generates comprehensive monthly summaries of income, expenses, and net profit
- **AI-Powered Insights**: Uses OpenAI's GPT to provide intelligent analysis and recommendations
- **Email Notifications**: Send monthly summaries to selected users via email
- **Dashboard Widget**: View monthly summaries directly in the dashboard
- **Scheduled Generation**: Automatically generate summaries on a configurable schedule
- **Multi-Company Support**: Works with multiple companies in the system

## Installation & Setup

### 1. Environment Configuration

Add your OpenAI API key to your `.env` file:

```env
OPENAI_API_KEY=your_openai_api_key_here
```

If you don't have an OpenAI API key, the system will fall back to default explanations.

### 2. Queue Configuration

Ensure your queue system is running as the feature uses queued jobs:

```bash
php artisan queue:work
```

### 3. Scheduler Setup

Add the Laravel scheduler to your cron tab for automatic generation:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Usage

### Dashboard Widget

1. Go to your dashboard
2. The Monthly Summary widget will automatically display the previous month's data
3. Use the dropdown to view different months
4. Click "View Details" for the full report
5. Click "Email" to send the summary to your email

### Full Report View

1. Navigate to **Reports > Monthly Summary** from the admin menu
2. Select the desired month from the dropdown
3. View detailed financial data with AI analysis
4. Use the "Send via Email" button to notify team members

### Email Notifications

1. In the report view, click "Send via Email"
2. Select which users should receive the notification
3. The system will queue and send detailed email reports with AI insights

### Automatic Generation

1. Go to the Monthly Summary report
2. Configure automatic generation settings:
   - Enable/disable automatic generation
   - Set the day of the month to generate (1-28)
   - Select users to notify automatically

## Console Commands

### Manual Generation

Generate monthly summaries manually:

```bash
# Generate for all companies (previous month)
php artisan summaries:generate-monthly

# Generate for specific company
php artisan summaries:generate-monthly --company=1

# Generate for specific month/year
php artisan summaries:generate-monthly --month=12 --year=2023

# Force generation (overwrite existing)
php artisan summaries:generate-monthly --force
```

## Technical Architecture

### Key Components

1. **MonthlySummary Report** (`app/Reports/MonthlySummary.php`)
   - Extends the existing Report abstract class
   - Calculates income, expenses, and profit for a specific month
   - Supports both cash and accrual accounting methods

2. **AiExplanationService** (`app/Services/AiExplanationService.php`)
   - Integrates with OpenAI's GPT API
   - Generates intelligent financial analysis
   - Falls back to default explanations if API unavailable

3. **GenerateMonthlySummary Job** (`app/Jobs/Common/GenerateMonthlySummary.php`)
   - Queued job for generating summaries
   - Handles AI explanation generation
   - Sends email notifications to users

4. **MonthlySummaryController** (`app/Http/Controllers/Common/MonthlySummaryController.php`)
   - Handles web requests for viewing and sending summaries
   - Provides API endpoints for dashboard integration

5. **MonthlySummaryWidget** (`app/Widgets/MonthlySummaryWidget.php`)
   - Dashboard widget for displaying summaries
   - Cached for performance

6. **MonthlySummary Notification** (`app/Notifications/Common/MonthlySummary.php`)
   - Email and database notifications
   - Rich HTML email templates with AI insights

### Routes

- `GET /reports/monthly-summary/{year?}/{month?}` - View monthly summary
- `POST /reports/monthly-summary/send-email` - Send email notifications
- `GET /reports/monthly-summary/dashboard-data` - API for dashboard widget
- `POST /reports/monthly-summary/schedule` - Configure automatic generation

### Database Storage

The feature uses Laravel's cache system to store generated summaries:
- Cache key format: `monthly_summary_{company_id}_{year}_{month}`
- Cache duration: 6 hours for dashboard, 12 months for storage
- No additional database tables required

## Customization

### AI Prompts

Modify the AI prompts in `AiExplanationService::buildPrompt()` to customize the analysis style and focus areas.

### Email Templates

The email template is defined in `MonthlySummary::toMail()`. Customize the layout and content as needed.

### Dashboard Widget

Modify the widget views in `resources/views/widgets/monthly-summary/` to change the appearance and functionality.

### Report Calculations

Extend the `MonthlySummary` report class to add additional financial metrics or modify calculation logic.

## Performance Considerations

- Summaries are cached to reduce database queries
- AI API calls are made asynchronously via queued jobs
- Large companies should consider running queue workers on separate servers
- Email sending is queued to prevent blocking the web interface

## Troubleshooting

### Common Issues

1. **AI explanations not working**: Check your OpenAI API key in the `.env` file
2. **Emails not sending**: Ensure queue workers are running and email configuration is correct
3. **Widget not showing**: Check user permissions for reading reports
4. **Scheduler not running**: Verify cron job is set up correctly

### Logs

Check Laravel logs for detailed error information:
- Monthly summary generation errors are logged with context
- AI service errors are logged separately
- Queue job failures are reported via notifications

## Security

- All routes require appropriate permissions (`read-reports`)
- AI API calls include error handling and fallbacks
- Email notifications respect user permissions and company boundaries
- Cached data is scoped by company to prevent data leakage

## Future Enhancements

Potential improvements for future versions:
- Support for quarterly and yearly summaries
- Integration with additional AI providers
- Advanced filtering and comparison features
- Export to PDF functionality
- Custom email templates per company
- Integration with business intelligence tools