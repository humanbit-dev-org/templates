@php
    $queryString = http_build_query(request()->except("page"));
    $createUrl = url($crud->route . '/create' . (!empty($queryString) ? "?$queryString" : ''));
@endphp

@if ($crud->hasAccess('create'))
    <a href="{{ $createUrl }}" class="btn btn-primary" bp-button="create" data-style="zoom-in">
        <i class="la la-plus"></i> <span>{{ trans('backpack::crud.add') }} {{ $crud->entity_name }}</span>
    </a>
@endif