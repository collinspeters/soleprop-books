<x-layouts.admin>
    <x-slot name="title">
        {{ __('Subscription Details') }}
    </x-slot>

    <x-slot name="favorite"
        title="{{ __('Subscription Details') }}"
        icon="credit_card"
        route="subscriptions.show"
    ></x-slot>

    <x-slot name="buttons">
        <a href="{{ route('subscriptions.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
            {{ __('Back to Plans') }}
        </a>
    </x-slot>

    <x-slot name="content">
        <div class="container mx-auto px-4 py-8 max-w-4xl">
            @if (session('success'))
                <x-alert type="success" message="{{ session('success') }}" />
            @endif

            @if (session('error'))
                <x-alert type="error" message="{{ session('error') }}" />
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Subscription Overview -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Subscription Overview') }}</h3>
                    </div>
                    <div class="px-6 py-6">
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Plan') }}</dt>
                                <dd class="mt-1 text-lg font-semibold text-gray-900">{{ $subscription->subscriptionPlan->name }}</dd>
                            </div>
                            
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Status') }}</dt>
                                <dd class="mt-1">
                                    @if ($subscription->active())
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {{ __('Active') }}
                                        </span>
                                    @elseif ($subscription->onTrial())
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ __('Trial') }}
                                        </span>
                                    @elseif ($subscription->canceled())
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            {{ __('Canceled') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            {{ ucfirst($subscription->status) }}
                                        </span>
                                    @endif
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Price') }}</dt>
                                <dd class="mt-1 text-lg text-gray-900">
                                    {{ $subscription->subscriptionPlan->formatted_price }} / {{ $subscription->subscriptionPlan->billing_period_display }}
                                </dd>
                            </div>

                            @if ($subscription->onTrial())
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ __('Trial Ends') }}</dt>
                                    <dd class="mt-1 text-gray-900">
                                        {{ $subscription->trial_ends_at->format('M j, Y') }}
                                        <span class="text-sm text-gray-500">
                                            ({{ $subscription->remaining_trial_days }} {{ __('days remaining') }})
                                        </span>
                                    </dd>
                                </div>
                            @endif

                            @if ($subscription->current_period_start && $subscription->current_period_end)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ __('Current Period') }}</dt>
                                    <dd class="mt-1 text-gray-900">
                                        {{ $subscription->current_period_start->format('M j, Y') }} - {{ $subscription->current_period_end->format('M j, Y') }}
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ __('Next Billing Date') }}</dt>
                                    <dd class="mt-1 text-gray-900">{{ $subscription->current_period_end->format('M j, Y') }}</dd>
                                </div>
                            @endif

                            @if ($subscription->canceled_at)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ __('Canceled On') }}</dt>
                                    <dd class="mt-1 text-gray-900">{{ $subscription->canceled_at->format('M j, Y') }}</dd>
                                </div>
                            @endif

                            @if ($subscription->ends_at)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ __('Ends On') }}</dt>
                                    <dd class="mt-1 text-gray-900">{{ $subscription->ends_at->format('M j, Y') }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>

                <!-- Plan Features -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Plan Features') }}</h3>
                    </div>
                    <div class="px-6 py-6">
                        <ul class="space-y-3">
                            @foreach ($subscription->subscriptionPlan->features as $feature)
                                <li class="flex items-center">
                                    <svg class="h-5 w-5 text-green-500 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="text-gray-700">{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <!-- Subscription Actions -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden lg:col-span-2">
                    <div class="px-6 py-4 bg-gray-50 border-b">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Manage Subscription') }}</h3>
                    </div>
                    <div class="px-6 py-6">
                        <div class="flex flex-wrap gap-4">
                            @if ($subscription->active() && !$subscription->canceled())
                                <form action="{{ route('subscriptions.cancel', $subscription) }}" method="POST" 
                                      onsubmit="return confirm('{{ __('Are you sure you want to cancel your subscription? You will continue to have access until the end of your current billing period.') }}')">
                                    @csrf
                                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                        {{ __('Cancel Subscription') }}
                                    </button>
                                </form>
                            @endif

                            @if ($subscription->canceled() && !$subscription->ended())
                                <form action="{{ route('subscriptions.resume', $subscription) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                        {{ __('Resume Subscription') }}
                                    </button>
                                </form>
                            @endif

                            <a href="#" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                {{ __('Update Payment Method') }}
                            </a>

                            <a href="#" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                {{ __('Download Invoices') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>
</x-layouts.admin>