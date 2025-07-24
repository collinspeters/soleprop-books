@if ($attachment)
    <x-show.accordion type="attachment">
        <x-slot name="head">
            <x-show.accordion.head
                title="{{ trans_choice('general.receipts', 2) }} & {{ trans_choice('general.attachments', 2) }}"
                description="{{ trans('transactions.attachments') }}"
            />
        </x-slot>

        <x-slot name="body">
            <div class="space-y-4">
                @foreach ($attachment as $file)
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <x-media.file :file="$file" />
                    </div>
                @endforeach
            </div>
        </x-slot>
    </x-show.accordion>
@endif
