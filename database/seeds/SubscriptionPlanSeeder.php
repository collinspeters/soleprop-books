<?php

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $plans = [
            [
                'name' => 'Basic Plan',
                'slug' => 'basic',
                'description' => 'Perfect for small businesses just getting started',
                'price' => 29.99,
                'billing_period' => 'monthly',
                'trial_period_days' => 14,
                'features' => [
                    'Up to 10 invoices per month',
                    'Basic reporting',
                    'Email support',
                    '1 user account',
                ],
                'stripe_price_id' => null, // Set this to your actual Stripe price ID
                'stripe_product_id' => null, // Set this to your actual Stripe product ID
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Professional Plan',
                'slug' => 'professional',
                'description' => 'Great for growing businesses with more needs',
                'price' => 59.99,
                'billing_period' => 'monthly',
                'trial_period_days' => 14,
                'features' => [
                    'Unlimited invoices',
                    'Advanced reporting',
                    'Priority email support',
                    'Up to 5 user accounts',
                    'Custom branding',
                    'API access',
                ],
                'stripe_price_id' => null, // Set this to your actual Stripe price ID
                'stripe_product_id' => null, // Set this to your actual Stripe product ID
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise Plan',
                'slug' => 'enterprise',
                'description' => 'For large organizations with complex requirements',
                'price' => 99.99,
                'billing_period' => 'monthly',
                'trial_period_days' => 14,
                'features' => [
                    'Everything in Professional',
                    'Unlimited users',
                    'Phone support',
                    'Custom integrations',
                    'Dedicated account manager',
                    'Advanced security features',
                ],
                'stripe_price_id' => null, // Set this to your actual Stripe price ID
                'stripe_product_id' => null, // Set this to your actual Stripe product ID
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Basic Annual',
                'slug' => 'basic-annual',
                'description' => 'Basic plan billed annually (save 20%)',
                'price' => 287.99, // $29.99 * 12 * 0.8
                'billing_period' => 'yearly',
                'trial_period_days' => 14,
                'features' => [
                    'Up to 10 invoices per month',
                    'Basic reporting',
                    'Email support',
                    '1 user account',
                    '20% discount vs monthly',
                ],
                'stripe_price_id' => null, // Set this to your actual Stripe price ID
                'stripe_product_id' => null, // Set this to your actual Stripe product ID
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Professional Annual',
                'slug' => 'professional-annual',
                'description' => 'Professional plan billed annually (save 20%)',
                'price' => 575.99, // $59.99 * 12 * 0.8
                'billing_period' => 'yearly',
                'trial_period_days' => 14,
                'features' => [
                    'Unlimited invoices',
                    'Advanced reporting',
                    'Priority email support',
                    'Up to 5 user accounts',
                    'Custom branding',
                    'API access',
                    '20% discount vs monthly',
                ],
                'stripe_price_id' => null, // Set this to your actual Stripe price ID
                'stripe_product_id' => null, // Set this to your actual Stripe product ID
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create($plan);
        }
    }
}
