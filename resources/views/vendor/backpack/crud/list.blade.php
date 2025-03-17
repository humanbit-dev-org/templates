@extends(backpack_view('blank'))

@php
$defaultBreadcrumbs = [
trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
$crud->entity_name_plural => url($crud->route),
trans('backpack::crud.list') => false,
];

// if breadcrumbs aren't defined in the CrudController, use the default breadcrumbs
$breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;
@endphp

@section('header')
<section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
  <h1 class="text-capitalize mb-0" bp-section="page-heading">{!! $crud->getHeading() ?? $crud->entity_name_plural !!}</h1>
  <p class="ms-2 ml-2 mb-0" id="datatable_info_stack" bp-section="page-subheading">{!! $crud->getSubheading() ?? '' !!}</p>
</section>
@endsection

@section('content')
{{-- Default box --}}
<div class="row" bp-section="crud-operation-list">

  {{-- THE ACTUAL CONTENT --}}
  <div class="{{ $crud->getListContentClass() }}">

    <div class="row mb-2 align-items-center">

      @if($crud->getOperationSetting('searchableTable'))
      <div class="col-sm-3">
        <div id="datatable_search_stack" class="mt-sm-0 mt-2 d-print-none">
          <div class="input-icon">
            <span class="input-icon-addon">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"></path>
                <path d="M21 21l-6 -6"></path>
              </svg>
            </span>
            <input type="search" class="form-control" placeholder="{{ trans('backpack::crud.search') }}..." />
          </div>
        </div>
      </div>
      @endif
      <div class="col-sm-3">
        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false" aria-controls="filterPanel">
          <i class="la la-filter me-1"></i> {{ trans('backpack::crud.filters') }}
        </button>
      </div>
      <div class="col-sm-9 text-end">
        @if ( $crud->buttons()->where('stack', 'top')->count() || $crud->exportButtons())
        <div class="d-print-none {{ $crud->hasAccess('create')?'with-border':'' }}">
          @include('crud::inc.button_stack', ['stack' => 'top'])

        </div>
        @endif
      </div>
    </div>

    {{-- Backpack List Filters --}}
    @if ($crud->filtersEnabled())
    @include('crud::inc.filters_navbar')
    @endif



    @php
      // Check if any filters are applied (excluding pagination)
      $hasFilters = collect(request()->except(['page']))->filter(function ($value) {
          return $value !== null && $value !== "";
      })->isNotEmpty();
    @endphp

    <!-- Filter Panel (Collapsed by Default) -->
    <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="filterPanel">
      <form class="row" action="" method="GET" class="mt-3">
        @php
          // Get all columns excluding pagination and other non-filterable fields
          $columns = collect($crud->columns())->filter(function ($column) {
            return isset($column['name'])
              && !in_array($column['name'], ['id', 'created_at', 'updated_at', 'order'])
              && (!isset($column['relation_type']) || $column['relation_type'] !== 'HasMany')
              && (strpos($column['name'], '_path') === false)
              && (strpos($column['name'], '_layout') === false);
          });
        @endphp

        @foreach ($columns as $column)
          @php
            // Determine the label for the column
            $label = isset($column['label']) ? $column['label'] : $column['name'];
          @endphp

          @if (str_ends_with($column['name'], '_id'))
            <!-- Dropdown for relation fields -->
            <div class="col-3 mb-3">
              <label for="{{ $column['name'] }}" class="form-label">{{ $label }}</label>
              <select name="{{ $column['name'] }}" class="form-control" id="{{ $column['name'] }}">
                <option value="">Select {{ $label }}</option>
                @php
                  $relatedModelName = Str::studly(str_replace('_id', '', $column['name']));
                  $relatedModel = "App\Models\\" . $relatedModelName;
                  $options = [];
                  
                  if (class_exists($relatedModel)) {
                    $collection = call_user_func([$relatedModel, 'all']);
                    $options = $collection->mapWithKeys(function ($item) {
                      return [
                        $item->id => match (true) {
                          isset($item->email) => $item->email,
                          isset($item->surname, $item->name) => $item->name . ' ' . $item->surname,
                          isset($item->author_surname, $item->author_name) => $item->author_name . ' ' . $item->author_surname,
                          isset($item->title) => $item->title,
                          isset($item->name) => $item->name,
                          default => $item->id,
                        }
                      ];
                    })->toArray();
                  }
                @endphp
                @foreach ($options as $value => $optionLabel)
                  <option value="{{ $value }}" {{ request()->get($column['name']) == $value ? 'selected' : '' }}>
                    {{ $optionLabel }}
                  </option>
                @endforeach
              </select>
            </div>

          @elseif ($column['type'] == 'switch')
            <!-- Boolean Dropdown: Empty, Yes (1), No (0) -->
            <div class="col-3 mb-3">
              <label for="{{ $column['name'] }}" class="form-label">{{ $label }}</label>
              <select name="{{ $column['name'] }}" class="form-control" id="{{ $column['name'] }}">
                <option value="">-- Select --</option>
                <option value="1" {{ request()->get($column['name']) == '1' ? 'selected' : '' }}>Yes</option>
                <option value="0" {{ request()->get($column['name']) == '0' ? 'selected' : '' }}>No</option>
              </select>
            </div>
            
          @elseif ($column['name'] === 'page')
            <!-- Special handling for 'page' column -->
            <div class="col-3 mb-3">
              <label for="page_filter" class="form-label">Page</label>
              <select name="page_filter" class="form-control" id="page_filter">
                <option value="">Select Page</option>
                <option value="home" {{ request()->get('page_filter') == 'home' ? 'selected' : '' }}>Homepage</option>
                <option value="riflessioni-su-milano" {{ request()->get('page_filter') == 'riflessioni-su-milano' ? 'selected' : '' }}>Riflessioni su Milano</option>
              </select>
            </div>
            
          @else
            <!-- Text input with jQuery Autocomplete -->
            <div class="col-3 mb-3 position-relative">
              <label for="{{ $column['name'] }}" class="form-label">{{ $label }}</label>
              <input autocomplete="off" type="text" name="{{ $column['name'] }}" 
                value="{{ request()->get($column['name']) }}" 
                class="form-control autocomplete-input" 
                data-column="{{ $column['name'] }}"
                data-table="{{ $crud->model->getTable() }}"
                id="{{ $column['name'] }}">
              <!-- Autocomplete dropdown -->
              <div class="autocomplete-suggestions" id="autocomplete-{{ $column['name'] }}"></div>
            </div>
          @endif
        @endforeach

        <div class="d-flex justify-content-end mb-3">
            <button type="submit" class="btn btn-primary">{{ trans('backpack::crud.search') }}</button>
        </div>
      </form>
    </div>


  
  




    <div class="{{ backpack_theme_config('classes.tableWrapper') }}">
      <table
        id="crudTable"
        class="{{ backpack_theme_config('classes.table') ?? 'table table-striped table-hover nowrap rounded card-table table-vcenter card d-table shadow-xs border-xs' }}"
        data-responsive-table="{{ (int) $crud->getOperationSetting('responsiveTable') }}"
        data-has-details-row="{{ (int) $crud->getOperationSetting('detailsRow') }}"
        data-has-bulk-actions="{{ (int) $crud->getOperationSetting('bulkActions') }}"
        data-has-line-buttons-as-dropdown="{{ (int) $crud->getOperationSetting('lineButtonsAsDropdown') }}"
        data-line-buttons-as-dropdown-minimum="{{ (int) $crud->getOperationSetting('lineButtonsAsDropdownMinimum') }}"
        data-line-buttons-as-dropdown-show-before-dropdown="{{ (int) $crud->getOperationSetting('lineButtonsAsDropdownShowBefore') }}"
        cellspacing="0">
        <thead>
          <tr>
            {{-- Table columns --}}
            @foreach ($crud->columns() as $column)
            @php
            $exportOnlyColumn = $column['exportOnlyColumn'] ?? false;
            $visibleInTable = $column['visibleInTable'] ?? ($exportOnlyColumn ? false : true);
            $visibleInModal = $column['visibleInModal'] ?? ($exportOnlyColumn ? false : true);
            $visibleInExport = $column['visibleInExport'] ?? true;
            $forceExport = $column['forceExport'] ?? (isset($column['exportOnlyColumn']) ? true : false);
            @endphp
            <th
              data-orderable="{{ var_export($column['orderable'], true) }}"
              data-priority="{{ $column['priority'] }}"
              data-column-name="{{ $column['name'] }}"
              {{--
                    data-visible-in-table => if developer forced column to be in the table with 'visibleInTable => true'
                    data-visible => regular visibility of the column
                    data-can-be-visible-in-table => prevents the column to be visible into the table (export-only)
                    data-visible-in-modal => if column appears on responsive modal
                    data-visible-in-export => if this column is exportable
                    data-force-export => force export even if columns are hidden
                    --}}

              data-visible="{{ $exportOnlyColumn ? 'false' : var_export($visibleInTable) }}"
              data-visible-in-table="{{ var_export($visibleInTable) }}"
              data-can-be-visible-in-table="{{ $exportOnlyColumn ? 'false' : 'true' }}"
              data-visible-in-modal="{{ var_export($visibleInModal) }}"
              data-visible-in-export="{{ $exportOnlyColumn ? 'true' : ($visibleInExport ? 'true' : 'false') }}"
              data-force-export="{{ var_export($forceExport) }}">
              {{-- Bulk checkbox --}}
              @if($loop->first && $crud->getOperationSetting('bulkActions'))
              {!! View::make('crud::columns.inc.bulk_actions_checkbox')->render() !!}
              @endif
              {!! $column['label'] !!}
            </th>
            @endforeach

            @if ( $crud->buttons()->where('stack', 'line')->count() )
            <th data-orderable="false"
              data-priority="{{ $crud->getActionsColumnPriority() }}"
              data-visible-in-export="false"
              data-action-column="true">{{ trans('backpack::crud.actions') }}</th>
            @endif
          </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
          <tr>
            {{-- Table columns --}}
            @foreach ($crud->columns() as $column)
            <th>
              {{-- Bulk checkbox --}}
              @if($loop->first && $crud->getOperationSetting('bulkActions'))
              {!! View::make('crud::columns.inc.bulk_actions_checkbox')->render() !!}
              @endif
              {!! $column['label'] !!}
            </th>
            @endforeach

            @if ( $crud->buttons()->where('stack', 'line')->count() )
            <th>{{ trans('backpack::crud.actions') }}</th>
            @endif
          </tr>
        </tfoot>
      </table>
    </div>

    @if ( $crud->buttons()->where('stack', 'bottom')->count() )
    <div id="bottom_buttons" class="d-print-none text-sm-left">
      @include('crud::inc.button_stack', ['stack' => 'bottom'])
      <div id="datatable_button_stack" class="float-right float-end text-right hidden-xs"></div>
    </div>
    @endif

  </div>

</div>

@endsection

@section('after_styles')
{{-- DATA TABLES --}}
@basset('https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css')
@basset('https://cdn.datatables.net/fixedheader/3.3.1/css/fixedHeader.dataTables.min.css')
@basset('https://cdn.datatables.net/responsive/2.4.0/css/responsive.dataTables.min.css')

{{-- CRUD LIST CONTENT - crud_list_styles stack --}}
@stack('crud_list_styles')
@endsection

@section('after_scripts')
@include('crud::inc.datatables_logic')

{{-- CRUD LIST CONTENT - crud_list_scripts stack --}}
@stack('crud_list_scripts')
@endsection