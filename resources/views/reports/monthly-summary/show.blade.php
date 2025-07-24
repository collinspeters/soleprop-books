@extends('layouts.admin')

@section('title', trans('Monthly Financial Summary'))

@section('content')
<div class="card">
    <div class="card-header border-bottom-0" :class="[{'bg-gradient-primary': bulk_action.show}]">
        <div class="row align-items-center" x-data="{ bulk_action: { show: false } }">
            <div class="col-12">
                <div class="d-flex align-items-center justify-content-between">
                    <h1 class="mb-0">{{ trans('Monthly Financial Summary') }}</h1>
                    
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                            {{ $summaryData['month_name'] }}
                        </button>
                        <div class="dropdown-menu">
                            @foreach($availableMonths as $availableMonth)
                                <a class="dropdown-item" href="{{ route('reports.monthly-summary.show', [$availableMonth['year'], $availableMonth['month']]) }}">
                                    {{ $availableMonth['name'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">{{ trans('Total Income') }}</h5>
                                <h3 class="mb-0">{{ $summaryData['total_income'] }}</h3>
                            </div>
                            <div>
                                <i class="fa fa-arrow-up fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">{{ trans('Total Expenses') }}</h5>
                                <h3 class="mb-0">{{ $summaryData['total_expense'] }}</h3>
                            </div>
                            <div>
                                <i class="fa fa-arrow-down fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card {{ str_contains($summaryData['net_profit'], '-') ? 'bg-warning' : 'bg-info' }} text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">{{ trans('Net Profit') }}</h5>
                                <h3 class="mb-0">{{ $summaryData['net_profit'] }}</h3>
                            </div>
                            <div>
                                <i class="fa {{ str_contains($summaryData['net_profit'], '-') ? 'fa-minus' : 'fa-plus' }} fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Explanation Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fa fa-brain mr-2"></i>
                            {{ trans('AI Financial Analysis') }}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <p class="mb-0">{{ $aiExplanation }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions Section -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ trans('Actions') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <button type="button" class="btn btn-primary btn-block" data-toggle="modal" data-target="#emailModal">
                                    <i class="fa fa-envelope mr-2"></i>
                                    {{ trans('Send via Email') }}
                                </button>
                            </div>
                            <div class="col-md-6">
                                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-block">
                                    <i class="fa fa-tachometer-alt mr-2"></i>
                                    {{ trans('Back to Dashboard') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Email Modal -->
<div class="modal fade" id="emailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="emailForm" action="{{ route('reports.monthly-summary.send-email') }}" method="POST">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="year" value="{{ $year }}">
                
                <div class="modal-header">
                    <h5 class="modal-title">{{ trans('Send Monthly Summary via Email') }}</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="users">{{ trans('Send to Users') }}</label>
                        <select name="users[]" id="users" class="form-control" multiple>
                            @foreach(company()->users as $user)
                                <option value="{{ $user->id }}" {{ $user->id == user()->id ? 'selected' : '' }}>
                                    {{ $user->name }} ({{ $user->email }})
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">
                            {{ trans('Select users who will receive the monthly summary email. Current user is selected by default.') }}
                        </small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ trans('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-paper-plane mr-2"></i>
                        {{ trans('Send Email') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts_start')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle email form submission
    document.getElementById('emailForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i>Sending...';
        
        fetch(this.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                const alert = document.createElement('div');
                alert.className = 'alert alert-success alert-dismissible fade show';
                alert.innerHTML = `
                    ${data.message}
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                `;
                document.querySelector('.card-body').insertBefore(alert, document.querySelector('.card-body').firstChild);
                
                // Close modal
                $('#emailModal').modal('hide');
            } else {
                throw new Error(data.message || 'An error occurred');
            }
        })
        .catch(error => {
            // Show error message
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show';
            alert.innerHTML = `
                ${error.message}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            `;
            document.querySelector('.modal-body').insertBefore(alert, document.querySelector('.modal-body').firstChild);
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });
});
</script>
@endpush