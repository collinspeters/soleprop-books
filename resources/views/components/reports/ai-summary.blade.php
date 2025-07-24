@if(setting('ai.reports.enabled', false) && !empty(config('services.openai.api_key')))
<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6" id="ai-summary-section">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <span class="material-icons text-blue-600">psychology</span>
                <h3 class="text-lg font-semibold text-gray-900">{{ trans('reports.ai.title') }}</h3>
            </div>
            <button type="button" 
                    class="text-gray-400 hover:text-gray-600 transition-colors duration-200"
                    onclick="toggleAiSummary()"
                    id="ai-toggle-btn">
                <span class="material-icons">expand_more</span>
            </button>
        </div>
    </div>
    
    <div class="px-6 py-4 hidden" id="ai-summary-content">
        <div class="space-y-4">
            <!-- Question Input -->
            <div>
                <label for="ai-question" class="block text-sm font-medium text-gray-700 mb-2">
                    {{ trans('reports.ai.ask_question') }}
                </label>
                <textarea 
                    id="ai-question" 
                    name="question"
                    rows="3" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                    placeholder="{{ trans('reports.ai.placeholder') }}"></textarea>
            </div>
            
            <!-- Generate Button -->
            <div class="flex justify-between items-center">
                <button type="button" 
                        onclick="generateAiSummary()"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                        id="generate-summary-btn">
                    <span class="material-icons mr-2 hidden" id="loading-icon">hourglass_empty</span>
                    <span id="button-text">{{ trans('reports.ai.generate_summary') }}</span>
                </button>
                <small class="text-gray-500">
                    {{ trans('reports.ai.powered_by') }}
                </small>
            </div>
            
            <!-- Summary Display -->
            <div id="ai-summary-result" class="hidden">
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4 border border-blue-200">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0">
                            <span class="material-icons text-blue-600">insights</span>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-semibold text-blue-900 mb-2">{{ trans('reports.ai.analysis') }}</h4>
                            <div id="summary-content" class="text-sm text-gray-700 whitespace-pre-line"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Error Display -->
            <div id="ai-summary-error" class="hidden">
                <div class="bg-red-50 rounded-lg p-4 border border-red-200">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0">
                            <span class="material-icons text-red-600">error</span>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-semibold text-red-900 mb-1">{{ trans('general.error') }}</h4>
                            <div id="error-content" class="text-sm text-red-700"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const translations = {
    generating: '{{ trans('reports.ai.generating') }}',
    generateSummary: '{{ trans('reports.ai.generate_summary') }}',
    errorConnection: '{{ trans('reports.ai.error_connection') }}'
};

function toggleAiSummary() {
    const content = document.getElementById('ai-summary-content');
    const toggleBtn = document.getElementById('ai-toggle-btn');
    const icon = toggleBtn.querySelector('.material-icons');
    
    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        icon.textContent = 'expand_less';
    } else {
        content.classList.add('hidden');
        icon.textContent = 'expand_more';
    }
}

function generateAiSummary() {
    const question = document.getElementById('ai-question').value;
    const generateBtn = document.getElementById('generate-summary-btn');
    const buttonText = document.getElementById('button-text');
    const loadingIcon = document.getElementById('loading-icon');
    const resultDiv = document.getElementById('ai-summary-result');
    const errorDiv = document.getElementById('ai-summary-error');
    const summaryContent = document.getElementById('summary-content');
    const errorContent = document.getElementById('error-content');
    
    // Reset displays
    resultDiv.classList.add('hidden');
    errorDiv.classList.add('hidden');
    
    // Show loading state
    generateBtn.disabled = true;
    buttonText.textContent = translations.generating;
    loadingIcon.classList.remove('hidden');
    
    // Make API request
    fetch(`{{ route('reports.ai-summary', $class->model->id) }}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            question: question
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            summaryContent.textContent = data.summary;
            resultDiv.classList.remove('hidden');
        } else {
            errorContent.textContent = data.error || 'An unexpected error occurred.';
            errorDiv.classList.remove('hidden');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        errorContent.textContent = translations.errorConnection;
        errorDiv.classList.remove('hidden');
    })
    .finally(() => {
        // Reset loading state
        generateBtn.disabled = false;
        buttonText.textContent = translations.generateSummary;
        loadingIcon.classList.add('hidden');
    });
}
</script>
@endif