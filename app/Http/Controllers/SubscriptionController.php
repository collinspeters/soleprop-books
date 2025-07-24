<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    protected $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
        $this->middleware('auth');
    }

    /**
     * Show subscription plans.
     */
    public function index()
    {
        $plans = $this->subscriptionService->getAvailablePlans();
        $user = Auth::user();
        $currentSubscription = $user->subscription;

        return view('subscriptions.index', compact('plans', 'user', 'currentSubscription'));
    }

    /**
     * Show subscription checkout form.
     */
    public function checkout(SubscriptionPlan $plan)
    {
        $user = Auth::user();

        // Check if user already has an active subscription
        if ($user->hasActiveSubscription()) {
            return redirect()->route('subscriptions.index')
                ->with('error', 'You already have an active subscription.');
        }

        return view('subscriptions.checkout', compact('plan', 'user'));
    }

    /**
     * Process subscription creation.
     */
    public function subscribe(Request $request, SubscriptionPlan $plan)
    {
        $request->validate([
            'payment_method' => 'required|string',
        ]);

        try {
            $user = Auth::user();
            
            $subscription = $this->subscriptionService->createSubscription(
                $user,
                $plan,
                $request->payment_method
            );

            return redirect()->route('subscriptions.index')
                ->with('success', 'Subscription created successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create subscription: ' . $e->getMessage());
        }
    }

    /**
     * Cancel subscription.
     */
    public function cancel(UserSubscription $subscription)
    {
        $user = Auth::user();

        // Check if user owns this subscription
        if ($subscription->user_id !== $user->id) {
            abort(403);
        }

        try {
            $this->subscriptionService->cancelSubscription($subscription);

            return redirect()->route('subscriptions.index')
                ->with('success', 'Subscription canceled successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to cancel subscription: ' . $e->getMessage());
        }
    }

    /**
     * Resume subscription.
     */
    public function resume(UserSubscription $subscription)
    {
        $user = Auth::user();

        // Check if user owns this subscription
        if ($subscription->user_id !== $user->id) {
            abort(403);
        }

        try {
            $this->subscriptionService->resumeSubscription($subscription);

            return redirect()->route('subscriptions.index')
                ->with('success', 'Subscription resumed successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to resume subscription: ' . $e->getMessage());
        }
    }

    /**
     * Show subscription details.
     */
    public function show(UserSubscription $subscription)
    {
        $user = Auth::user();

        // Check if user owns this subscription
        if ($subscription->user_id !== $user->id) {
            abort(403);
        }

        return view('subscriptions.show', compact('subscription'));
    }
}
