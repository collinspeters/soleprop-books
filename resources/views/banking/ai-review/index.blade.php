<x-layouts.admin>
    <x-slot name="title">
        {{ trans('transactions.ai_review_title') }}
    </x-slot>

    <x-slot name="favorite"
        title="{{ trans('transactions.ai_review_title') }}"
        icon="psychology"
        route="ai-review.index"
    ></x-slot>

    <x-slot name="buttons">
        <x-link href="{{ route('transactions.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-900">
            <x-slot name="icon">
                arrow_back
            </x-slot>
            {{ trans('general.back_to_transactions') }}
        </x-link>
    </x-slot>

    <x-slot name="content">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <x-icon name="psychology" class="w-6 h-6" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">{{ trans('transactions.total_ai_suggestions') }}</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_ai_suggestions'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <x-icon name="warning" class="w-6 h-6" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">{{ trans('transactions.low_confidence') }}</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $stats['low_confidence'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                        <x-icon name="pending" class="w-6 h-6" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">{{ trans('transactions.unreviewed') }}</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $stats['unreviewed'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <x-icon name="check_circle" class="w-6 h-6" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">{{ trans('transactions.reviewed') }}</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $stats['reviewed'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">{{ trans('general.filters') }}</h3>
            </div>
            <div class="p-6">
                <form method="GET" action="{{ route('ai-review.index') }}" class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label for="filter" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ trans('transactions.filter_type') }}
                        </label>
                        <select name="filter" id="filter" class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="low_confidence" {{ $filter === 'low_confidence' ? 'selected' : '' }}>
                                {{ trans('transactions.low_confidence') }}
                            </option>
                            <option value="unreviewed" {{ $filter === 'unreviewed' ? 'selected' : '' }}>
                                {{ trans('transactions.unreviewed') }}
                            </option>
                            <option value="reviewed" {{ $filter === 'reviewed' ? 'selected' : '' }}>
                                {{ trans('transactions.reviewed') }}
                            </option>
                            <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>
                                {{ trans('transactions.all_ai_suggestions') }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label for="confidence_threshold" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ trans('transactions.confidence_threshold') }}
                        </label>
                        <input type="number" name="confidence_threshold" id="confidence_threshold" 
                               value="{{ $confidenceThreshold }}" step="0.1" min="0" max="1"
                               class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md">
                        {{ trans('general.filter') }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">{{ trans('general.bulk_actions') }}</h3>
            </div>
            <div class="p-6">
                <div class="flex gap-4">
                    <button type="button" id="bulk-approve-all" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md">
                        {{ trans('transactions.approve_all_selected') }}
                    </button>
                    <button type="button" id="bulk-mark-reviewed" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md">
                        {{ trans('transactions.mark_as_reviewed') }}
                    </button>
                    <button type="button" id="select-all" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                        {{ trans('general.select_all') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">
                    {{ trans('transactions.ai_suggestions_requiring_review') }}
                    <span class="text-sm text-gray-500">({{ $transactions->total() }} {{ trans('general.total') }})</span>
                </h3>
            </div>
            
            @if($transactions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" id="select-all-checkbox" class="rounded border-gray-300">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ trans('general.date') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ trans('general.description') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ trans('general.amount') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ trans('transactions.current_category') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ trans('transactions.ai_suggested_category') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ trans('transactions.confidence') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ trans('general.status') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ trans('general.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($transactions as $transaction)
                                <tr id="transaction-row-{{ $transaction->id }}" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" name="transaction_ids[]" value="{{ $transaction->id }}" 
                                               class="transaction-checkbox rounded border-gray-300">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $transaction->paid_at->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div class="max-w-xs truncate" title="{{ $transaction->description }}">
                                            {{ $transaction->description ?: trans('general.na') }}
                                        </div>
                                        @if($transaction->contact)
                                            <div class="text-xs text-gray-500">{{ $transaction->contact->name }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="font-medium {{ $transaction->isIncome() ? 'text-green-600' : 'text-red-600' }}">
                                            {{ money($transaction->amount, $transaction->currency_code)->format() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            {{ $transaction->category->name }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $transaction->aiSuggestedCategory->name }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if($transaction->ai_confidence)
                                            <div class="flex items-center">
                                                <div class="flex-1 bg-gray-200 rounded-full h-2 mr-2">
                                                    <div class="h-2 rounded-full {{ $transaction->ai_confidence >= 0.8 ? 'bg-green-500' : ($transaction->ai_confidence >= 0.6 ? 'bg-yellow-500' : 'bg-red-500') }}" 
                                                         style="width: {{ $transaction->ai_confidence * 100 }}%"></div>
                                                </div>
                                                <span class="text-xs font-medium">{{ number_format($transaction->ai_confidence * 100, 1) }}%</span>
                                            </div>
                                        @else
                                            {{ trans('general.na') }}
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($transaction->ai_reviewed)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <x-icon name="check_circle" class="w-3 h-3 mr-1" />
                                                {{ trans('transactions.reviewed') }}
                                            </span>
                                            @if($transaction->aiReviewedBy)
                                                <div class="text-xs text-gray-500 mt-1">
                                                    {{ trans('general.by') }} {{ $transaction->aiReviewedBy->name }}
                                                </div>
                                            @endif
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <x-icon name="pending" class="w-3 h-3 mr-1" />
                                                {{ trans('transactions.pending_review') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        @if(!$transaction->ai_reviewed)
                                            <div class="flex gap-2">
                                                <button type="button" 
                                                        class="approve-suggestion bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-xs"
                                                        data-transaction-id="{{ $transaction->id }}"
                                                        data-category-id="{{ $transaction->ai_suggested_category_id }}">
                                                    {{ trans('transactions.approve') }}
                                                </button>
                                                <button type="button" 
                                                        class="override-suggestion bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs"
                                                        data-transaction-id="{{ $transaction->id }}">
                                                    {{ trans('transactions.override') }}
                                                </button>
                                            </div>
                                        @else
                                            <span class="text-gray-400 text-xs">{{ trans('transactions.already_reviewed') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $transactions->appends(request()->query())->links() }}
                </div>
            @else
                <div class="p-6 text-center">
                    <x-icon name="psychology" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 mb-2">{{ trans('transactions.no_ai_suggestions') }}</h3>
                    <p class="text-gray-500">{{ trans('transactions.no_ai_suggestions_description') }}</p>
                </div>
            @endif
        </div>
    </x-slot>

    <!-- Category Override Modal -->
    <div id="category-override-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">{{ trans('transactions.override_category') }}</h3>
            </div>
            <div class="p-6">
                <form id="category-override-form">
                    <input type="hidden" id="override-transaction-id" name="transaction_id">
                    <div class="mb-4">
                        <label for="override-category-id" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ trans('transactions.select_category') }}
                        </label>
                        <select id="override-category-id" name="category_id" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }} ({{ ucfirst($category->type) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" id="cancel-override" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md">
                            {{ trans('general.cancel') }}
                        </button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                            {{ trans('transactions.override_category') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <x-slot name="scripts">
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Select all functionality
                const selectAllCheckbox = document.getElementById('select-all-checkbox');
                const transactionCheckboxes = document.querySelectorAll('.transaction-checkbox');
                
                selectAllCheckbox.addEventListener('change', function() {
                    transactionCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });

                document.getElementById('select-all').addEventListener('click', function() {
                    transactionCheckboxes.forEach(checkbox => {
                        checkbox.checked = true;
                    });
                    selectAllCheckbox.checked = true;
                });

                // Approve suggestion buttons
                document.querySelectorAll('.approve-suggestion').forEach(button => {
                    button.addEventListener('click', function() {
                        const transactionId = this.dataset.transactionId;
                        const categoryId = this.dataset.categoryId;
                        
                        updateTransaction(transactionId, categoryId, 'approve');
                    });
                });

                // Override suggestion buttons
                document.querySelectorAll('.override-suggestion').forEach(button => {
                    button.addEventListener('click', function() {
                        const transactionId = this.dataset.transactionId;
                        document.getElementById('override-transaction-id').value = transactionId;
                        document.getElementById('category-override-modal').classList.remove('hidden');
                        document.getElementById('category-override-modal').classList.add('flex');
                    });
                });

                // Modal controls
                document.getElementById('cancel-override').addEventListener('click', function() {
                    closeModal();
                });

                document.getElementById('category-override-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const transactionId = document.getElementById('override-transaction-id').value;
                    const categoryId = document.getElementById('override-category-id').value;
                    
                    updateTransaction(transactionId, categoryId, 'override');
                    closeModal();
                });

                // Bulk actions
                document.getElementById('bulk-approve-all').addEventListener('click', function() {
                    const selectedIds = getSelectedTransactionIds();
                    if (selectedIds.length === 0) {
                        alert('{{ trans('transactions.please_select_transactions') }}');
                        return;
                    }
                    bulkUpdate(selectedIds, 'approve_all');
                });

                document.getElementById('bulk-mark-reviewed').addEventListener('click', function() {
                    const selectedIds = getSelectedTransactionIds();
                    if (selectedIds.length === 0) {
                        alert('{{ trans('transactions.please_select_transactions') }}');
                        return;
                    }
                    bulkUpdate(selectedIds, 'mark_reviewed');
                });

                function closeModal() {
                    document.getElementById('category-override-modal').classList.add('hidden');
                    document.getElementById('category-override-modal').classList.remove('flex');
                }

                function getSelectedTransactionIds() {
                    const selectedCheckboxes = document.querySelectorAll('.transaction-checkbox:checked');
                    return Array.from(selectedCheckboxes).map(checkbox => checkbox.value);
                }

                function updateTransaction(transactionId, categoryId, action) {
                    fetch(`{{ route('ai-review.update', '') }}/${transactionId}`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            category_id: categoryId,
                            action: action
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update the row to show reviewed status
                            const row = document.getElementById(`transaction-row-${transactionId}`);
                            if (row) {
                                location.reload(); // Simple reload for now
                            }
                        } else {
                            alert('{{ trans('general.error') }}');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('{{ trans('general.error') }}');
                    });
                }

                function bulkUpdate(transactionIds, action) {
                    fetch('{{ route('ai-review.bulk-update') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            transaction_ids: transactionIds,
                            action: action
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('{{ trans('general.error') }}');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('{{ trans('general.error') }}');
                    });
                }
            });
        </script>
    </x-slot>
</x-layouts.admin>