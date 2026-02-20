@php
    // Filtra i parametri per rimuovere quelli che terminano con "_path" e il campo "id"
    $filteredParams = collect(request()->except("page"))->filter(function ($value, $key) {
        return !str_ends_with($key, "_path") && $key !== "id";
    })->all();
    
    $queryString = http_build_query($filteredParams);
    $createUrl = url($crud->route . '/create' . (!empty($queryString) ? "?$queryString" : ''));
    
    // Controlla se ci sono filtri attivi
    $hasActiveFilters = collect(request()->except(["page", "persistent-table"]))->filter(function ($value, $key) {
        return $value !== null && $value !== '';
    })->isNotEmpty();
@endphp

@if ($crud->hasAccess('create'))
    <a href="{{ $createUrl }}" class="btn btn-primary {{ $hasActiveFilters ? 'has-active-filters' : '' }}" bp-button="create" data-style="zoom-in" data-active-filters="{{ $hasActiveFilters ? 'true' : 'false' }}">
        <i class="la la-plus"></i> <span>{{ trans('backpack::crud.add') }} <span class="text-capitalize">{{ $crud->entity_name }}</span></span>
    </a>
@endif