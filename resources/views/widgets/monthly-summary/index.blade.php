<div class="card">
    <div class="card-header">
        <div class="d-flex align-items-center justify-content-between">
            <h5 class="mb-0">
                <i class="fa fa-calendar-alt mr-2"></i>
                {{ trans('Monthly Financial Summary') }}
            </h5>
            
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                    {{ $summaryData['month_name'] }}
                </button>
                <div class="dropdown-menu">
                    @foreach($availableMonths as $availableMonth)
                        <a class="dropdown-item" href="?month={{ $availableMonth['month'] }}&year={{ $availableMonth['year'] }}">
                            {{ $availableMonth['name'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <!-- Summary Numbers -->
        <div class="row mb-3">
            <div class="col-4 text-center">
                <div class="text-success">
                    <i class="fa fa-arrow-up"></i>
                    <strong>{{ trans('Income') }}</strong>
                </div>
                <h6 class="mb-0">{{ $summaryData['total_income'] }}</h6>
            </div>
            <div class="col-4 text-center">
                <div class="text-danger">
                    <i class="fa fa-arrow-down"></i>
                    <strong>{{ trans('Expenses') }}</strong>
                </div>
                <h6 class="mb-0">{{ $summaryData['total_expense'] }}</h6>
            </div>
            <div class="col-4 text-center">
                <div class="{{ str_contains($summaryData['net_profit'], '-') ? 'text-warning' : 'text-info' }}">
                    <i class="fa {{ str_contains($summaryData['net_profit'], '-') ? 'fa-minus' : 'fa-plus' }}"></i>
                    <strong>{{ trans('Profit') }}</strong>
                </div>
                <h6 class="mb-0">{{ $summaryData['net_profit'] }}</h6>
            </div>
        </div>
        
        <!-- AI Explanation -->
        <div class="border-top pt-3">
            <div class="d-flex align-items-start">
                <i class="fa fa-brain text-primary mr-2 mt-1"></i>
                <div class="flex-grow-1">
                    <small class="text-muted d-block mb-1">{{ trans('AI Analysis') }}</small>
                    <p class="small mb-0">{{ Str::limit($aiExplanation, 150) }}</p>
                </div>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="border-top pt-3 mt-3">
            <div class="row">
                <div class="col-6">
                    <a href="{{ route('reports.monthly-summary.show', [$year, $month]) }}" class="btn btn-sm btn-primary btn-block">
                        <i class="fa fa-eye mr-1"></i>
                        {{ trans('View Details') }}
                    </a>
                </div>
                <div class="col-6">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-block" onclick="sendMonthlySummaryEmail()">
                        <i class="fa fa-envelope mr-1"></i>
                        {{ trans('Email') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function sendMonthlySummaryEmail() {
    if (confirm('{{ trans("Send monthly summary to your email?") }}')) {
        fetch('{{ route("reports.monthly-summary.send-email") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                month: {{ $month }},
                year: {{ $year }}
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('{{ trans("Monthly summary email sent successfully!") }}');
            } else {
                alert('{{ trans("Failed to send email. Please try again.") }}');
            }
        })
        .catch(error => {
            alert('{{ trans("An error occurred. Please try again.") }}');
        });
    }
}
</script>