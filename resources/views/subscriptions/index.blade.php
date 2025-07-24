<x-layouts.admin>
    <x-slot name="title">
        {{ __('Subscription Plans') }}
    </x-slot>

    <x-slot name="favorite"
        title="{{ __('Subscription Plans') }}"
        icon="credit_card"
        route="subscriptions.index"
    ></x-slot>

    <x-slot name="content">
        <div class="container mx-auto px-4 py-8">
            @if (session('success'))
                <x-alert type="success" message="{{ session('success') }}" />
            @endif

            @if (session('error'))
                <x-alert type="error" message="{{ session('error') }}" />
            @endif

            <!-- Current Subscription Status -->
            @if ($user->onTrial())
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-blue-800">
                                {{ __('Free Trial Active') }}
                            </h3>
                            <p class="text-blue-600">
                                {{ __('You have :days days remaining in your free trial.', ['days' => $user->remaining_trial_days]) }}
                            </p>
                        </div>
                    </div>
                </div>
            @elseif ($currentSubscription && $currentSubscription->active())
                <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-8">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-lg font-medium text-green-800">
                                    {{ __('Active Subscription') }}
                                </h3>
                                <p class="text-green-600">
                                    {{ __('You are subscribed to :plan', ['plan' => $currentSubscription->subscriptionPlan->name]) }}
                                </p>
                                <p class="text-sm text-green-500">
                                    {{ __('Next billing: :date', ['date' => $currentSubscription->current_period_end->format('M j, Y')]) }}
                                </p>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <a href="{{ route('subscriptions.show', $currentSubscription) }}" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                {{ __('Manage') }}
                            </a>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-8">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-red-800">
                                {{ __('Trial Expired') }}
                            </h3>
                            <p class="text-red-600">
                                {{ __('Your free trial has ended. Please choose a plan to continue using premium features.') }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Subscription Plans -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach ($plans as $plan)
                    <div class="bg-white rounded-lg shadow-lg overflow-hidden {{ $currentSubscription && $currentSubscription->subscription_plan_id === $plan->id ? 'ring-2 ring-blue-500' : '' }}">
                        <div class="px-6 py-8">
                            <h3 class="text-2xl font-bold text-gray-900">{{ $plan->name }}</h3>
                            <p class="mt-4 text-gray-600">{{ $plan->description }}</p>
                            <div class="mt-8">
                                <span class="text-4xl font-bold text-gray-900">{{ $plan->formatted_price }}</span>
                                <span class="text-gray-600">/ {{ $plan->billing_period_display }}</span>
                            </div>
                            
                            @if ($plan->trial_period_days > 0)
                                <p class="mt-2 text-sm text-green-600">
                                    {{ __(':days day free trial', ['days' => $plan->trial_period_days]) }}
                                </p>
                            @endif
                        </div>
                        
                        <div class="px-6 py-4 bg-gray-50">
                            <ul class="space-y-2">
                                @foreach ($plan->features as $feature)
                                    <li class="flex items-center">
                                        <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span class="text-gray-700">{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        
                        <div class="px-6 py-6">
                            @if ($currentSubscription && $currentSubscription->subscription_plan_id === $plan->id)
                                <button class="w-full bg-gray-300 text-gray-500 py-3 px-4 rounded-md font-medium cursor-not-allowed" disabled>
                                    {{ __('Current Plan') }}
                                </button>
                            @elseif ($user->hasActiveSubscription())
                                <button class="w-full bg-gray-300 text-gray-500 py-3 px-4 rounded-md font-medium cursor-not-allowed" disabled>
                                    {{ __('Already Subscribed') }}
                                </button>
                            @else
                                <a href="{{ route('subscriptions.checkout', $plan) }}" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 px-4 rounded-md font-medium text-center block">
                                    {{ __('Choose Plan') }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-slot>
</x-layouts.admin>