<div class="divide-y">
    <div class="flex items-center justify-between py-4 -mb-4">
        <div class="flex items-center">
            @if ($file->aggregate_type == 'image')
                <div class="avatar-attachment cursor-pointer" onclick="openPreviewModal('{{ route('uploads.get', $file->id) }}', '{{ $file->basename }}', '{{ $file->aggregate_type }}')">
                    <img src="{{ route('uploads.get', $file->id) }}" alt="{{ $file->basename }}" class="avatar-img h-full rounded object-cover hover:opacity-80 transition-opacity">
                </div>
            @else
                <div class="avatar-attachment cursor-pointer" onclick="openPreviewModal('{{ route('uploads.get', $file->id) }}', '{{ $file->basename }}', '{{ $file->aggregate_type }}')">
                    <span class="material-icons text-base hover:text-blue-600 transition-colors">{{ $file->aggregate_type == 'pdf' ? 'picture_as_pdf' : 'attach_file' }}</span>
                </div>
            @endif  

            <div class="flex flex-col text-gray-500 ltr:ml-3 rtl:mr-3 gap-y-1">
                <span class="w-64 text-sm truncate cursor-pointer hover:text-blue-600 transition-colors" onclick="openPreviewModal('{{ route('uploads.get', $file->id) }}', '{{ $file->basename }}', '{{ $file->aggregate_type }}')">
                    {{ $file->basename }}
                </span>

                <span class="text-xs mb-0">
                    {{ ! is_int($file->size) ? '0 B' : $file->readableSize() }}
                </span>
            </div>
        </div>  

        <div class="flex flex-row lg:flex-col gap-x-1">
            <!-- Preview button -->
            <x-link href="javascript:void(0);" onclick="openPreviewModal('{{ route('uploads.get', $file->id) }}', '{{ $file->basename }}', '{{ $file->aggregate_type }}')" type="button" class="group" override="class" title="{{ trans('general.preview') }}">
                <span class="material-icons text-base text-gray-300 px-1.5 py-1 rounded-lg group-hover:bg-gray-100 group-hover:text-blue-600">visibility</span>
            </x-link>

            @can('delete-common-uploads')
                <x-link href="javascript:void();" id="remove-{{ $column_name }}" @click="onDeleteFile('{{ $file->id }}', '{{ route('uploads.destroy', $file->id) }}', '{{ trans('general.title.delete', ['type' => $column_name]) }}', '{{ trans('general.delete_confirm', ['name' => $file->basename, 'type' => $column_name]) }} ', '{{ trans('general.cancel') }}', '{{ trans('general.delete') }}')" type="button" class="group" override="class" title="{{ trans('general.delete') }}">
                    <span class="material-icons-outlined text-base text-gray-300 px-1.5 py-1 rounded-lg group-hover:bg-gray-100 group-hover:text-red-600">delete</span>
                </x-link>

                @if ($options)
                    <input type="hidden" name="page_{{ $file->id}}" id="file-page-{{ $file->id}}" value="{{ $options['page'] }}" />
                    <input type="hidden" name="key_{{ $file->id}}" id="file-key-{{ $file->id}}" value="{{ $options['key'] }}" />
                    <input type="hidden" name="value_{{ $file->id}}" id="file-value-{{ $file->id}}" value="{{ $file->id }}" />
                @endif  
            @endcan

            <x-link href="{{ route('uploads.download', $file->id) }}" type="button" class="group" override="class" title="{{ trans('general.download') }}">
                <span class="material-icons text-base text-gray-300 px-1.5 py-1 rounded-lg group-hover:bg-gray-100 group-hover:text-green-600">download</span>
            </x-link>
        </div>
    </div>
</div>

<!-- Receipt Preview Modal -->
@once
<div id="receiptPreviewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-900" id="modalTitle">{{ trans('general.preview') }}</h3>
            <button type="button" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center" onclick="closePreviewModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        
        <div class="mb-4">
            <div id="previewContent" class="text-center">
                <!-- Content will be loaded here -->
            </div>
        </div>
        
        <div class="flex justify-end space-x-2">
            <button type="button" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded" onclick="closePreviewModal()">
                {{ trans('general.close') }}
            </button>
            <a id="downloadLink" href="#" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded" download>
                {{ trans('general.download') }}
            </a>
        </div>
    </div>
</div>
@endonce

@once
<script>
function openPreviewModal(fileUrl, fileName, fileType) {
    const modal = document.getElementById('receiptPreviewModal');
    const modalTitle = document.getElementById('modalTitle');
    const previewContent = document.getElementById('previewContent');
    const downloadLink = document.getElementById('downloadLink');
    
    modalTitle.textContent = fileName;
    downloadLink.href = fileUrl + '/download';
    
    // Clear previous content
    previewContent.innerHTML = '';
    
    if (fileType === 'image') {
        previewContent.innerHTML = `
            <img src="${fileUrl}" alt="${fileName}" class="max-w-full max-h-96 mx-auto rounded shadow-lg" 
                 onerror="this.parentElement.innerHTML='<div class=\\'text-center py-8\\'><span class=\\'material-icons text-6xl text-gray-400 mb-4\\'>broken_image</span><p class=\\'text-lg text-gray-600\\'>${fileName}</p><p class=\\'text-sm text-gray-500 mt-2\\'>Image could not be loaded</p></div>';">
        `;
    } else if (fileType === 'pdf') {
        const inlineUrl = fileUrl + '/inline';
        previewContent.innerHTML = `
            <iframe src="${inlineUrl}" class="w-full h-96 border rounded" frameborder="0" 
                    onerror="this.parentElement.innerHTML='<div class=\\'text-center py-8\\'><span class=\\'material-icons text-6xl text-gray-400 mb-4\\'>error</span><p class=\\'text-lg text-gray-600\\'>${fileName}</p><p class=\\'text-sm text-gray-500 mt-2\\'>PDF could not be loaded</p></div>';"></iframe>
            <p class="mt-2 text-sm text-gray-600">PDF preview for ${fileName}</p>
        `;
    } else {
        previewContent.innerHTML = `
            <div class="text-center py-8">
                <span class="material-icons text-6xl text-gray-400 mb-4">attach_file</span>
                <p class="text-lg text-gray-600">${fileName}</p>
                <p class="text-sm text-gray-500 mt-2">{{ trans('general.preview_not_available') }}</p>
            </div>
        `;
    }
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closePreviewModal() {
    const modal = document.getElementById('receiptPreviewModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('receiptPreviewModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closePreviewModal();
            }
        });
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('receiptPreviewModal');
        if (modal && !modal.classList.contains('hidden')) {
            closePreviewModal();
        }
    }
});
</script>
@endonce
