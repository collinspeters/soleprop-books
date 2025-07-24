<x-layouts.admin>
    <x-slot name="title">
        {{ __('Checkout - :plan', ['plan' => $plan->name]) }}
    </x-slot>

    <x-slot name="content">
        <div class="container mx-auto px-4 py-8 max-w-2xl">
            @if (session('error'))
                <x-alert type="error" message="{{ session('error') }}" />
            @endif

            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <!-- Plan Summary -->
                <div class="px-6 py-8 bg-gray-50 border-b">
                    <h2 class="text-2xl font-bold text-gray-900">{{ __('Complete Your Subscription') }}</h2>
                    <div class="mt-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">{{ $plan->name }}</h3>
                                <p class="text-gray-600">{{ $plan->description }}</p>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-gray-900">{{ $plan->formatted_price }}</div>
                                <div class="text-gray-600">per {{ $plan->billing_period }}</div>
                            </div>
                        </div>
                        
                        @if ($plan->trial_period_days > 0)
                            <div class="mt-4 p-3 bg-green-100 rounded-md">
                                <p class="text-green-800 text-sm">
                                    <strong>{{ __('Free Trial:') }}</strong> {{ __('You will not be charged for :days days. Cancel anytime during the trial.', ['days' => $plan->trial_period_days]) }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Payment Form -->
                <div class="px-6 py-8">
                    <form id="payment-form" action="{{ route('subscriptions.subscribe', $plan) }}" method="POST">
                        @csrf
                        
                        <div class="mb-6">
                            <label for="card-element" class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('Payment Information') }}
                            </label>
                            <div id="card-element" class="p-3 border border-gray-300 rounded-md">
                                <!-- Stripe Elements will create form elements here -->
                            </div>
                            <div id="card-errors" role="alert" class="text-red-600 text-sm mt-2"></div>
                        </div>

                        <div class="mb-6">
                            <div class="flex items-center">
                                <input id="terms" name="terms" type="checkbox" required class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                                <label for="terms" class="ml-2 text-sm text-gray-700">
                                    {{ __('I agree to the') }} 
                                    <a href="#" class="text-blue-600 hover:text-blue-500">{{ __('Terms of Service') }}</a> 
                                    {{ __('and') }} 
                                    <a href="#" class="text-blue-600 hover:text-blue-500">{{ __('Privacy Policy') }}</a>
                                </label>
                            </div>
                        </div>

                        <div class="flex space-x-4">
                            <a href="{{ route('subscriptions.index') }}" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 py-3 px-4 rounded-md font-medium text-center">
                                {{ __('Cancel') }}
                            </a>
                            <button type="submit" id="submit-button" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 px-4 rounded-md font-medium">
                                <span id="button-text">
                                    @if ($plan->trial_period_days > 0)
                                        {{ __('Start Free Trial') }}
                                    @else
                                        {{ __('Subscribe Now') }}
                                    @endif
                                </span>
                                <span id="spinner" class="hidden">
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ __('Processing...') }}
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </x-slot>

    <x-slot name="scripts">
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            const stripe = Stripe('{{ config("services.stripe.key") }}');
            const elements = stripe.elements();

            // Create card element
            const cardElement = elements.create('card', {
                style: {
                    base: {
                        fontSize: '16px',
                        color: '#424770',
                        '::placeholder': {
                            color: '#aab7c4',
                        },
                    },
                },
            });

            cardElement.mount('#card-element');

            // Handle form submission
            const form = document.getElementById('payment-form');
            const submitButton = document.getElementById('submit-button');
            const buttonText = document.getElementById('button-text');
            const spinner = document.getElementById('spinner');

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                
                submitButton.disabled = true;
                buttonText.classList.add('hidden');
                spinner.classList.remove('hidden');

                const { paymentMethod, error } = await stripe.createPaymentMethod({
                    type: 'card',
                    card: cardElement,
                });

                if (error) {
                    // Show error to customer
                    document.getElementById('card-errors').textContent = error.message;
                    submitButton.disabled = false;
                    buttonText.classList.remove('hidden');
                    spinner.classList.add('hidden');
                } else {
                    // Add payment method to form and submit
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'payment_method';
                    hiddenInput.value = paymentMethod.id;
                    form.appendChild(hiddenInput);
                    
                    form.submit();
                }
            });

            // Handle real-time validation errors from the card Element
            cardElement.on('change', ({error}) => {
                const displayError = document.getElementById('card-errors');
                if (error) {
                    displayError.textContent = error.message;
                } else {
                    displayError.textContent = '';
                }
            });
        </script>
    </x-slot>
</x-layouts.admin>