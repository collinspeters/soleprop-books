<div class="d-flex align-items-center justify-content-between">
    <h5 class="mb-0">
        <i class="fa fa-calendar-alt mr-2"></i>
        {{ trans('Monthly Financial Summary') }}
    </h5>
    
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown">
            {{ $summaryData['month_name'] ?? 'Select Month' }}
        </button>
        <div class="dropdown-menu">
            @if(isset($availableMonths))
                @foreach($availableMonths as $availableMonth)
                    <a class="dropdown-item" href="?month={{ $availableMonth['month'] }}&year={{ $availableMonth['year'] }}">
                        {{ $availableMonth['name'] }}
                    </a>
                @endforeach
            @endif
        </div>
    </div>
</div>