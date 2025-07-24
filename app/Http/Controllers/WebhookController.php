<?php

namespace App\Http\Controllers;

use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class WebhookController extends Controller
{
    protected $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Handle Stripe webhooks.
     */
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            Log::error('Invalid payload in webhook', ['error' => $e->getMessage()]);
            return response('Invalid payload', 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Invalid signature in webhook', ['error' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        // Handle the event
        switch ($event->type) {
            case 'customer.subscription.created':
                $this->handleSubscriptionCreated($event->data->object);
                break;

            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event->data->object);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event->data->object);
                break;

            case 'invoice.payment_succeeded':
                $this->handlePaymentSucceeded($event->data->object);
                break;

            case 'invoice.payment_failed':
                $this->handlePaymentFailed($event->data->object);
                break;

            default:
                Log::info('Unhandled webhook event', ['type' => $event->type]);
        }

        return response('Webhook handled', 200);
    }

    /**
     * Handle subscription created event.
     */
    protected function handleSubscriptionCreated($subscription)
    {
        Log::info('Subscription created', ['subscription_id' => $subscription->id]);
        $this->subscriptionService->updateSubscriptionFromWebhook($subscription->toArray());
    }

    /**
     * Handle subscription updated event.
     */
    protected function handleSubscriptionUpdated($subscription)
    {
        Log::info('Subscription updated', ['subscription_id' => $subscription->id]);
        $this->subscriptionService->updateSubscriptionFromWebhook($subscription->toArray());
    }

    /**
     * Handle subscription deleted event.
     */
    protected function handleSubscriptionDeleted($subscription)
    {
        Log::info('Subscription deleted', ['subscription_id' => $subscription->id]);
        $this->subscriptionService->updateSubscriptionFromWebhook($subscription->toArray());
    }

    /**
     * Handle payment succeeded event.
     */
    protected function handlePaymentSucceeded($invoice)
    {
        Log::info('Payment succeeded', ['invoice_id' => $invoice->id]);
        
        if ($invoice->subscription) {
            // Update subscription status if needed
            $this->subscriptionService->updateSubscriptionFromWebhook([
                'id' => $invoice->subscription,
                'status' => 'active',
                'current_period_start' => $invoice->period_start,
                'current_period_end' => $invoice->period_end,
            ]);
        }
    }

    /**
     * Handle payment failed event.
     */
    protected function handlePaymentFailed($invoice)
    {
        Log::info('Payment failed', ['invoice_id' => $invoice->id]);
        
        if ($invoice->subscription) {
            // Update subscription status to past_due
            $this->subscriptionService->updateSubscriptionFromWebhook([
                'id' => $invoice->subscription,
                'status' => 'past_due',
                'current_period_start' => $invoice->period_start,
                'current_period_end' => $invoice->period_end,
            ]);
        }
    }
}
