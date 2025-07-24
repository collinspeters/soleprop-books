<?php

namespace App\Notifications\Common;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;

class MonthlySummary extends Notification implements ShouldQueue
{
    use Queueable;

    protected $summaryData;
    protected $aiExplanation;

    /**
     * Create a notification instance.
     */
    public function __construct($summaryData, $aiExplanation)
    {
        $this->summaryData = $summaryData;
        $this->aiExplanation = $aiExplanation;

        $this->onQueue('notifications');
    }

    /**
     * Get the notification's channels.
     *
     * @param  mixed  $notifiable
     * @return array|string
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        $dashboard_url = route('dashboard', ['company_id' => company_id()]);
        $monthly_summary_url = route('reports.monthly-summary.show', [
            'company_id' => company_id(),
            'month' => $this->summaryData['month'],
            'year' => $this->summaryData['year']
        ]);

        $subject = 'Monthly Financial Summary - ' . $this->summaryData['month_name'];

        return (new MailMessage)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->subject($subject)
            ->greeting('Monthly Financial Summary')
            ->line('Here is your financial summary for ' . $this->summaryData['month_name'] . ':')
            ->line(new HtmlString('<br>'))
            ->line(new HtmlString('<strong>Total Income:</strong> ' . $this->summaryData['total_income']))
            ->line(new HtmlString('<strong>Total Expenses:</strong> ' . $this->summaryData['total_expense']))
            ->line(new HtmlString('<strong>Net Profit:</strong> ' . $this->summaryData['net_profit']))
            ->line(new HtmlString('<br>'))
            ->line(new HtmlString('<strong>AI Analysis:</strong>'))
            ->line(new HtmlString('<em>' . nl2br(e($this->aiExplanation)) . '</em>'))
            ->line(new HtmlString('<br>'))
            ->action('View Detailed Report', $monthly_summary_url)
            ->action('Go to Dashboard', $dashboard_url)
            ->line('Thank you for using our financial management system!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'title' => 'Monthly Financial Summary - ' . $this->summaryData['month_name'],
            'description' => 'Your monthly financial summary is ready with AI insights.',
            'summary_data' => $this->summaryData,
            'ai_explanation' => $this->aiExplanation,
            'type' => 'monthly_summary',
        ];
    }
}