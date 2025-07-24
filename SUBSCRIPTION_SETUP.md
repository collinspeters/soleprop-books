# Subscription System Setup Guide

This document provides instructions for setting up and configuring the subscription system with Stripe integration.

## Overview

The subscription system includes:
- 14-day free trial for new users
- Stripe integration for payment processing
- Multiple subscription plans (Basic, Professional, Enterprise)
- Automatic feature restriction after trial expiration
- Webhook handling for payment status updates
- User-friendly subscription management interface

## Installation Steps

### 1. Database Setup

Run the migrations to create the necessary database tables:

```bash
php artisan migrate
```

### 2. Seed Subscription Plans

Run the seeder to create sample subscription plans:

```bash
php artisan db:seed --class=SubscriptionPlanSeeder
```

### 3. Environment Configuration

Add the following environment variables to your `.env` file:

```env
# Stripe Configuration
STRIPE_KEY=pk_test_your_stripe_publishable_key
STRIPE_SECRET=sk_test_your_stripe_secret_key
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret
```

### 4. Stripe Setup

1. **Create a Stripe Account**: Sign up at https://stripe.com
2. **Get API Keys**: 
   - Go to Developers > API keys in your Stripe dashboard
   - Copy your publishable key (pk_test_...) and secret key (sk_test_...)
3. **Create Products and Prices**:
   - Go to Products in your Stripe dashboard
   - Create products for each subscription plan
   - Create recurring prices for each product
   - Update the `stripe_price_id` and `stripe_product_id` in the `subscription_plans` table
4. **Set up Webhooks**:
   - Go to Developers > Webhooks in your Stripe dashboard
   - Add endpoint: `https://yourdomain.com/webhooks/stripe`
   - Select events: `customer.subscription.*`, `invoice.payment_succeeded`, `invoice.payment_failed`
   - Copy the webhook signing secret

### 5. Update Subscription Plans

After creating products in Stripe, update your subscription plans with the Stripe IDs:

```sql
UPDATE subscription_plans SET 
    stripe_price_id = 'price_1234567890',
    stripe_product_id = 'prod_1234567890'
WHERE slug = 'basic';
```

## Features

### Free Trial System

- New users automatically get a 14-day free trial
- Trial status is tracked in the `users` table
- Trial expiration automatically restricts access to premium features

### Subscription Management

- Users can view available plans at `/admin/subscriptions`
- Checkout process with Stripe Elements integration
- Subscription details and management interface
- Cancel/resume subscription functionality

### Feature Access Control

Use the `CheckSubscriptionAccess` middleware to protect premium features:

```php
Route::group(['middleware' => ['auth', 'subscription']], function () {
    // Premium features routes
});
```

Or check programmatically:

```php
if (auth()->user()->canAccessPremiumFeatures()) {
    // Show premium feature
}
```

### Webhook Integration

The system automatically handles Stripe webhooks for:
- Subscription creation/updates
- Payment success/failure
- Subscription cancellation

## Usage Examples

### Check User Trial Status

```php
$user = auth()->user();

if ($user->onTrial()) {
    echo "Trial expires in " . $user->remaining_trial_days . " days";
}
```

### Check Subscription Status

```php
$user = auth()->user();

if ($user->hasActiveSubscription()) {
    $subscription = $user->subscription;
    echo "Subscribed to: " . $subscription->subscriptionPlan->name;
}
```

### Create Custom Subscription Plans

```php
SubscriptionPlan::create([
    'name' => 'Custom Plan',
    'slug' => 'custom',
    'description' => 'A custom subscription plan',
    'price' => 49.99,
    'billing_period' => 'monthly',
    'trial_period_days' => 14,
    'features' => [
        'Feature 1',
        'Feature 2',
        'Feature 3',
    ],
    'stripe_price_id' => 'price_stripe_id',
    'stripe_product_id' => 'prod_stripe_id',
    'is_active' => true,
    'sort_order' => 1,
]);
```

## Customization

### Modify Trial Period

To change the default trial period, update the `StartUserTrial` listener:

```php
// In app/Listeners/StartUserTrial.php
$this->subscriptionService->startTrialForNewUser($user, 30); // 30 days instead of 14
```

### Add Custom Features

Add feature restrictions throughout your application:

```php
@if(auth()->user()->canAccessPremiumFeatures())
    <!-- Premium feature content -->
@else
    <div class="upgrade-prompt">
        <p>Upgrade to access this feature</p>
        <a href="{{ route('subscriptions.index') }}">View Plans</a>
    </div>
@endif
```

### Custom Subscription Logic

Extend the `SubscriptionService` class to add custom subscription logic:

```php
class CustomSubscriptionService extends SubscriptionService
{
    public function customMethod()
    {
        // Custom logic here
    }
}
```

## Testing

### Test Mode

Use Stripe's test mode for development:
- Use test API keys (pk_test_... and sk_test_...)
- Use test card numbers: 4242424242424242

### Webhook Testing

Use Stripe CLI for local webhook testing:

```bash
stripe listen --forward-to localhost:8000/webhooks/stripe
```

## Security Considerations

1. **Environment Variables**: Never commit Stripe keys to version control
2. **Webhook Verification**: The system verifies webhook signatures
3. **User Authorization**: Controllers check user ownership of subscriptions
4. **HTTPS**: Always use HTTPS in production for Stripe integration

## Troubleshooting

### Common Issues

1. **Webhook Failures**: Check webhook endpoint secret and URL
2. **Payment Failures**: Verify Stripe keys and test with valid card numbers
3. **Trial Not Starting**: Check if the `StartUserTrial` listener is registered
4. **Feature Access**: Ensure middleware is applied to protected routes

### Logs

Check Laravel logs for subscription-related errors:

```bash
tail -f storage/logs/laravel.log
```

## Support

For issues related to:
- Stripe integration: Check Stripe documentation
- Laravel framework: Check Laravel documentation
- Application-specific issues: Review the code and logs

## License

This subscription system is part of the Akaunting application and follows the same license terms.