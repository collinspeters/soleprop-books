<?php

namespace App\Services;

use App\Models\Auth\User;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Subscription;
use Stripe\PaymentMethod;
use Carbon\Carbon;

class SubscriptionService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create or get Stripe customer for user.
     */
    public function createOrGetCustomer(User $user): Customer
    {
        if ($user->stripe_customer_id) {
            try {
                return Customer::retrieve($user->stripe_customer_id);
            } catch (\Exception $e) {
                // Customer doesn't exist, create new one
            }
        }

        $customer = Customer::create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => $user->id,
            ],
        ]);

        $user->update(['stripe_customer_id' => $customer->id]);

        return $customer;
    }

    /**
     * Create a subscription for a user.
     */
    public function createSubscription(User $user, SubscriptionPlan $plan, string $paymentMethodId): UserSubscription
    {
        $customer = $this->createOrGetCustomer($user);

        // Attach payment method to customer
        $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
        $paymentMethod->attach(['customer' => $customer->id]);

        // Set as default payment method
        Customer::update($customer->id, [
            'invoice_settings' => [
                'default_payment_method' => $paymentMethodId,
            ],
        ]);

        // Create subscription in Stripe
        $subscription = Subscription::create([
            'customer' => $customer->id,
            'items' => [
                ['price' => $plan->stripe_price_id],
            ],
            'trial_period_days' => $plan->trial_period_days,
            'metadata' => [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ],
        ]);

        // End user's current trial if on trial
        if ($user->onTrial()) {
            $user->endTrial();
        }

        // Create local subscription record
        return UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'stripe_subscription_id' => $subscription->id,
            'stripe_customer_id' => $customer->id,
            'status' => $subscription->status,
            'trial_ends_at' => $subscription->trial_end ? Carbon::createFromTimestamp($subscription->trial_end) : null,
            'current_period_start' => Carbon::createFromTimestamp($subscription->current_period_start),
            'current_period_end' => Carbon::createFromTimestamp($subscription->current_period_end),
        ]);
    }

    /**
     * Cancel a subscription.
     */
    public function cancelSubscription(UserSubscription $subscription): void
    {
        if ($subscription->stripe_subscription_id) {
            $stripeSubscription = Subscription::retrieve($subscription->stripe_subscription_id);
            $stripeSubscription->cancel();
        }

        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'ends_at' => $subscription->current_period_end,
        ]);
    }

    /**
     * Resume a canceled subscription.
     */
    public function resumeSubscription(UserSubscription $subscription): void
    {
        if ($subscription->stripe_subscription_id && $subscription->status === 'canceled') {
            // This would require recreating the subscription in Stripe
            // For now, we'll just update the local status
            $subscription->update([
                'status' => 'active',
                'canceled_at' => null,
                'ends_at' => null,
            ]);
        }
    }

    /**
     * Update subscription from Stripe webhook.
     */
    public function updateSubscriptionFromWebhook(array $stripeSubscription): void
    {
        $subscription = UserSubscription::where('stripe_subscription_id', $stripeSubscription['id'])->first();

        if (!$subscription) {
            return;
        }

        $subscription->update([
            'status' => $stripeSubscription['status'],
            'current_period_start' => Carbon::createFromTimestamp($stripeSubscription['current_period_start']),
            'current_period_end' => Carbon::createFromTimestamp($stripeSubscription['current_period_end']),
            'trial_ends_at' => isset($stripeSubscription['trial_end']) 
                ? Carbon::createFromTimestamp($stripeSubscription['trial_end']) 
                : null,
        ]);

        // Update canceled status
        if ($stripeSubscription['status'] === 'canceled') {
            $subscription->update([
                'canceled_at' => now(),
                'ends_at' => Carbon::createFromTimestamp($stripeSubscription['current_period_end']),
            ]);
        }
    }

    /**
     * Check if user can access premium features.
     */
    public function canAccessPremiumFeatures(User $user): bool
    {
        return $user->canAccessPremiumFeatures();
    }

    /**
     * Start trial for new user.
     */
    public function startTrialForNewUser(User $user, int $days = 14): void
    {
        $user->startTrial($days);
    }

    /**
     * Get subscription plans for display.
     */
    public function getAvailablePlans()
    {
        return SubscriptionPlan::active()->ordered()->get();
    }
}