@php
    // Get active filters from request, excluding pagination and system parameters
    $activeFilters = collect(request()->except([
        'page', 
        'persistent-table', 
        '_token',
        'draw',
        'columns', 
        'order',
        'start',
        'length',
        'search'
    ]))->filter(function ($value, $key) {
        return $value !== null && $value !== '';
    });
    
    // Build query string with active filters
    $queryString = $activeFilters->isNotEmpty() ? '?' . http_build_query($activeFilters->all()) : '';
    
    // Check if there are active filters for the popover
    $hasActiveFilters = $activeFilters->isNotEmpty();
@endphp

@if($hasActiveFilters)
<span
    data-toggle="tooltip"
    data-bs-toggle="tooltip"
    data-placement="top"
    data-bs-placement="top"
    title="{{ trans('backpack::crud.warning_export_with_filters') }}">
    <a href="{{ url($crud->route.'/export-csv' . $queryString) }}" 
       class="btn btn-primary csv-export">
        <i class="la la-file-csv me-1"></i> {{ trans('backpack::crud.export_csv') }}
    </a>
</span>
@else
<a href="{{ url($crud->route.'/export-csv' . $queryString) }}" 
   class="btn btn-primary csv-export">
    <i class="la la-file-csv me-1"></i> {{ trans('backpack::crud.export_csv') }}
</a>
@endif