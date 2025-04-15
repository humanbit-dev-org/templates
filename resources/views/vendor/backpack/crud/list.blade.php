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
      $hasFilters = collect(request()->except(['page']))
          ->filter(function ($value) {
              return $value !== null && $value !== "";
          })->isNotEmpty();
          
      // Get all columns excluding pagination and other non-filterable fields
      $columns = collect($crud->columns())->filter(function ($column) {
        return isset($column['name'])
          && !in_array($column['name'], ['created_at', 'updated_at', 'order'])
          && (!isset($column['relation_type']) || $column['relation_type'] !== 'HasMany');
      });
      
      // Group columns by type
      $textColumns = $columns->filter(function($column) {
        return !str_ends_with($column['name'], '_id') 
          && !str_ends_with($column['name'], '_path')
          && ($column['type'] ?? '') != 'switch'
          && $column['name'] !== 'page';
      });
      
      // Check if specifically we have any path fields
      $pathFields = $columns->filter(function($column) {
        return str_ends_with($column['name'], '_path');
      });
      
      // Boolean filters will now exclude path fields
      $booleanColumns = $columns->filter(function($column) {
        return ($column['type'] ?? '') == 'switch' && !str_ends_with($column['name'], '_path');
      });
      
      $relationColumns = $columns->filter(function($column) {
        return str_ends_with($column['name'], '_id') || $column['name'] === 'page';
      });

      // Get count of applied filters per section (count only real user filters)
      $textFilterCount = collect(request()->all())
          ->filter(function($value, $key) use ($textColumns) {
              return $textColumns->contains('name', $key) && $value !== null && $value !== '';
          })->count();
          
      $booleanFilterCount = collect(request()->all())
          ->filter(function($value, $key) use ($booleanColumns) {
              return $booleanColumns->contains('name', $key) && $value !== null && $value !== '';
          })->count();
          
      $pathFilterCount = collect(request()->all())
          ->filter(function($value, $key) use ($pathFields) {
              return $pathFields->contains('name', $key) && $value !== null && $value !== '';
          })->count();
          
      $relationFilterCount = collect(request()->all())
          ->filter(function($value, $key) use ($relationColumns) {
              if ($key === 'page_filter' && $value !== null && $value !== '') {
                  return true;
              }
              return $relationColumns->contains('name', $key) && $value !== null && $value !== '';
          })->count();

      // Check if specific sections have active filters
      $hasTextFilters = $textFilterCount > 0;
      $hasBooleanFilters = $booleanFilterCount > 0;
      $hasPathFilters = $pathFilterCount > 0;
      $hasRelationFilters = $relationFilterCount > 0;
      
      // Determine if multiple filter sections are active
      $activeFilterSections = 0;
      if ($hasTextFilters) $activeFilterSections++;
      if ($hasBooleanFilters) $activeFilterSections++;
      if ($hasPathFilters) $activeFilterSections++;
      if ($hasRelationFilters) $activeFilterSections++;
      
      // Only auto-open sections if exactly one section has filters
      $autoOpenSection = $activeFilterSections == 1;
      
      // Determine which section to auto-open (if any)
      $openTextFilters = $hasTextFilters && $autoOpenSection;
      $openBooleanFilters = $hasBooleanFilters && $autoOpenSection;
      $openPathFilters = $hasPathFilters && $autoOpenSection;
      $openRelationFilters = $hasRelationFilters && $autoOpenSection;
    @endphp

    <!-- Filter Panel (Collapsed by Default) -->
    <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="filterPanel">
      <form action="" method="GET" class="mt-3">
        
        <div class="row mb-3">
          <!-- Active Filters Summary -->
          <div class="col-12 mb-2">
            @php
              $activeFilters = collect(request()->except(['page', 'persistent-table']))
                  ->filter(function ($value, $key) {
                      return $value !== null && $value !== "" && $key !== '_token';
                  });
            @endphp
            
            @if($activeFilters->count() > 0)
              <div class="d-flex flex-wrap gap-2 align-items-center py-2">
                <span class="fw-bold text-muted">{{ trans('backpack::filters.active_filters') }}:</span>
                @foreach($activeFilters as $key => $value)
                  @php
                    $columnName = $key === 'page_filter' ? 'page' : $key;
                    $column = $columns->firstWhere('name', $columnName);
                    if (!$column) continue; // Skip if not a valid column
                    
                    $label = isset($column['label']) ? $column['label'] : $columnName;
                    
                    // Determine filter display value
                    if(str_ends_with($columnName, '_path')) {
                      $displayValue = trans('backpack::filters.with_file');
                    } elseif(str_ends_with($columnName, '_id')) {
                      // Get related model name
                      $relatedModelName = Str::studly(str_replace('_id', '', $columnName));
                      $relatedModel = "App\\Models\\" . $relatedModelName;
                      $displayValue = 'ID: ' . $value; // Default fallback
                      
                      if(class_exists($relatedModel)) {
                        $item = call_user_func([$relatedModel, 'find'], $value);
                        if($item) {
                          // Check for most informative fields in priority order
                          if (!empty($item->title_italian)) {
                            $displayValue = $item->title_italian;
                          } elseif (!empty($item->title_english)) {
                            $displayValue = $item->title_english;
                          } elseif (!empty($item->title)) {
                            $displayValue = $item->title;
                          } elseif (!empty($item->name) && !empty($item->surname)) {
                            $displayValue = $item->name . ' ' . $item->surname;
                          } elseif (!empty($item->author_name) && !empty($item->author_surname)) {
                            $displayValue = $item->author_name . ' ' . $item->author_surname;
                          } elseif (!empty($item->firstname) && !empty($item->lastname)) {
                            $displayValue = $item->firstname . ' ' . $item->lastname;
                          } elseif (!empty($item->name)) {
                            $displayValue = $item->name;
                          } elseif (!empty($item->surname)) {
                            $displayValue = $item->surname;
                          } elseif (!empty($item->email)) {
                            $displayValue = $item->email;
                          } elseif (!empty($item->username)) {
                            $displayValue = $item->username;
                          }
                        }
                      }
                    } elseif(($column['type'] ?? '') == 'switch') {
                      $displayValue = $value == '1' ? trans('backpack::filters.yes') : trans('backpack::filters.no');
                    } elseif($key === 'page_filter') {
                      $displayValue = match($value) {
                        'home' => 'Homepage',
                        'riflessioni-su-milano' => 'Riflessioni su Milano',
                        default => $value
                      };
                    } else {
                      $displayValue = $value;
                    }
                  @endphp
                  <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except(['page', $key]), ['persistent-table' => '1'])) }}" 
                    class="btn btn-filter filter-badge d-flex align-items-center">
                    <i class="la la-times-circle filter-remove-icon me-1"></i>
                    <span><span class="filter-field-name">{{ $label }}</span><span>: {{ $displayValue }}</span></span>
                  </a>
                @endforeach
                <a href="{{ url($crud->route) }}?persistent-table=1" class="btn btn-outline-secondary reset-btn">
                  <i class="la la-times-circle me-1"></i> {{ trans('backpack::crud.reset') }}
                </a>
              </div>
            @endif
          </div>
        </div>

        <!-- Text Filters Accordion -->
        <div class="accordion mb-3" id="filtersAccordion">
          @if($textColumns->count() > 0)
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button {{ $openTextFilters ? '' : 'collapsed' }}" type="button" 
                      data-bs-toggle="collapse" data-bs-target="#textFiltersCollapse" 
                      aria-expanded="{{ $openTextFilters ? 'true' : 'false' }}" aria-controls="textFiltersCollapse">
                <i class="la la-align-left me-1"></i> {{ trans('backpack::filters.text_filters') }}
                @if($textFilterCount > 0)
                  <span class="badge bg-primary rounded-pill ms-2">{{ $textFilterCount }}</span>
                @endif
              </button>
            </h2>
            <div id="textFiltersCollapse" class="accordion-collapse collapse {{ $openTextFilters ? 'show' : '' }}" data-bs-parent="#filtersAccordion">
              <div class="accordion-body">
                <div class="row g-2 mt-1">
                  @foreach ($textColumns as $column)
                    @php
                      $label = isset($column['label']) ? $column['label'] : $column['name'];
                    @endphp
                    <div class="col-lg-3 col-md-4 col-sm-6">
                      <label for="{{ $column['name'] }}" class="form-label small text-muted mb-1 filter-label">{{ $label }}</label>
                      <div class="position-relative">
                        <input autocomplete="off" type="text" name="{{ $column['name'] }}" 
                          value="{{ request()->get($column['name']) }}" 
                          class="form-control autocomplete-input" 
                          data-column="{{ $column['name'] }}"
                          data-table="{{ $crud->model->getTable() }}"
                          id="{{ $column['name'] }}">
                        <div class="autocomplete-suggestions" id="autocomplete-{{ $column['name'] }}"></div>
                      </div>
                    </div>
                  @endforeach
                </div>
              </div>
            </div>
          </div>
          @endif

          <!-- Path Fields Filters Accordion -->
          @if($pathFields->count() > 0)
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button {{ $openPathFilters ? '' : 'collapsed' }}" type="button" 
                      data-bs-toggle="collapse" data-bs-target="#pathFiltersCollapse" 
                      aria-expanded="{{ $openPathFilters ? 'true' : 'false' }}" aria-controls="pathFiltersCollapse">
                <i class="la la-file me-1"></i> {{ trans('backpack::filters.uploaded_files') }}
                @if($pathFilterCount > 0)
                  <span class="badge bg-primary rounded-pill ms-2">{{ $pathFilterCount }}</span>
                @endif
              </button>
            </h2>
            <div id="pathFiltersCollapse" class="accordion-collapse collapse {{ $openPathFilters ? 'show' : '' }}" data-bs-parent="#filtersAccordion">
              <div class="accordion-body">
                <div class="row g-2 mt-1">
                  @foreach ($pathFields as $column)
                    @php
                      $label = isset($column['label']) ? $column['label'] : str_replace('_path', '', $column['name']);
                      $label = ucfirst($label);
                    @endphp
                    
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-2">
                      <div class="form-check form-switch mt-1">
                        <input type="checkbox" class="form-check-input" name="{{ $column['name'] }}" value="1" 
                          id="{{ $column['name'] }}" {{ request()->get($column['name']) == '1' ? 'checked' : '' }}>
                        <label class="form-check-label filter-label" for="{{ $column['name'] }}">{{ $label }}</label>
                      </div>
                    </div>
                  @endforeach
                </div>
              </div>
            </div>
          </div>
          @endif

          <!-- Boolean Filters Accordion -->
          @if($booleanColumns->count() > 0)
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button {{ $openBooleanFilters ? '' : 'collapsed' }}" type="button" 
                      data-bs-toggle="collapse" data-bs-target="#booleanFiltersCollapse" 
                      aria-expanded="{{ $openBooleanFilters ? 'true' : 'false' }}" aria-controls="booleanFiltersCollapse">
                <i class="la la-check-square me-1"></i> {{ trans('backpack::filters.status') }}
                @if($booleanFilterCount > 0)
                  <span class="badge bg-primary rounded-pill ms-2">{{ $booleanFilterCount }}</span>
                @endif
              </button>
            </h2>
            <div id="booleanFiltersCollapse" class="accordion-collapse collapse {{ $openBooleanFilters ? 'show' : '' }}" data-bs-parent="#filtersAccordion">
              <div class="accordion-body">
                <div class="row g-2 mt-1">
                  @foreach ($booleanColumns as $column)
                    @php
                      $label = isset($column['label']) ? $column['label'] : $column['name'];
                    @endphp
                    
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-2">
                      <label class="form-label small text-muted mb-1 d-block filter-label">{{ $label }}</label>
                      <div class="btn-group btn-group-sm w-100">
                        <input type="radio" class="btn-check" name="{{ $column['name'] }}" id="{{ $column['name'] }}_all" 
                          {{ request()->get($column['name']) === null || request()->get($column['name']) === '' ? 'checked' : '' }} value="">
                        <label class="btn btn-outline-secondary" for="{{ $column['name'] }}_all">{{ trans('backpack::filters.all') }}</label>
                        
                        <input type="radio" class="btn-check" name="{{ $column['name'] }}" id="{{ $column['name'] }}_yes" 
                          {{ request()->get($column['name']) == '1' ? 'checked' : '' }} value="1">
                        <label class="btn btn-outline-success" for="{{ $column['name'] }}_yes">{{ trans('backpack::filters.yes') }}</label>
                        
                        <input type="radio" class="btn-check" name="{{ $column['name'] }}" id="{{ $column['name'] }}_no" 
                          {{ request()->get($column['name']) == '0' ? 'checked' : '' }} value="0">
                        <label class="btn btn-outline-danger" for="{{ $column['name'] }}_no">{{ trans('backpack::filters.no') }}</label>
                      </div>
                    </div>
                  @endforeach
                </div>
              </div>
            </div>
          </div>
          @endif

          <!-- Relation Filters Accordion -->
          @if($relationColumns->count() > 0)
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button {{ $openRelationFilters ? '' : 'collapsed' }}" type="button" 
                      data-bs-toggle="collapse" data-bs-target="#relationFiltersCollapse" 
                      aria-expanded="{{ $openRelationFilters ? 'true' : 'false' }}" aria-controls="relationFiltersCollapse">
                <i class="la la-link me-1"></i> {{ trans('backpack::filters.relations') }}
                @if($relationFilterCount > 0)
                  <span class="badge bg-primary rounded-pill ms-2">{{ $relationFilterCount }}</span>
                @endif
              </button>
            </h2>
            <div id="relationFiltersCollapse" class="accordion-collapse collapse {{ $openRelationFilters ? 'show' : '' }}" data-bs-parent="#filtersAccordion">
              <div class="accordion-body">
                <div class="row g-2 mt-1">
                  @foreach ($relationColumns as $column)
                    @php
                      $label = isset($column['label']) ? $column['label'] : $column['name'];
                    @endphp

                    <div class="col-lg-3 col-md-4 col-sm-6">
                      @if ($column['name'] === 'page')
                        <label for="page_filter" class="form-label small text-muted mb-1 filter-label">{{ $label }}</label>
                        <select name="page_filter" class="form-select" id="page_filter">
                          <option value="">Tutte le pagine</option>
                          <option value="home" {{ request()->get('page_filter') == 'home' ? 'selected' : '' }}>Homepage</option>
                          <option value="riflessioni-su-milano" {{ request()->get('page_filter') == 'riflessioni-su-milano' ? 'selected' : '' }}>Riflessioni su Milano</option>
                        </select>
                      @else
                        <label for="{{ $column['name'] }}" class="form-label small text-muted mb-1 filter-label">{{ $label }}</label>
                        <select name="{{ $column['name'] }}" class="form-select" id="{{ $column['name'] }}">
                          <option value="">Tutti</option>
                          @php
                            $relatedModelName = Str::studly(str_replace('_id', '', $column['name']));
                            $relatedModel = "App\Models\\" . $relatedModelName;
                            $options = [];
                            
                            if (class_exists($relatedModel)) {
                              $collection = call_user_func([$relatedModel, 'all']);
                              $options = $collection->mapWithKeys(function ($item) {
                                          $displayValue = '';
                                          // Check for most informative fields in priority order
                                          if (!empty($item->title_italian)) {
                                            $displayValue = $item->title_italian;
                                          } elseif (!empty($item->title_english)) {
                                            $displayValue = $item->title_english;
                                          } elseif (!empty($item->title)) {
                                            $displayValue = $item->title;
                                          } elseif (!empty($item->name) && !empty($item->surname)) {
                                            $displayValue = $item->name . ' ' . $item->surname;
                                          } elseif (!empty($item->author_name) && !empty($item->author_surname)) {
                                            $displayValue = $item->author_name . ' ' . $item->author_surname;
                                          } elseif (!empty($item->firstname) && !empty($item->lastname)) {
                                            $displayValue = $item->firstname . ' ' . $item->lastname;
                                          } elseif (!empty($item->name)) {
                                            $displayValue = $item->name;
                                          } elseif (!empty($item->surname)) {
                                            $displayValue = $item->surname;
                                          } elseif (!empty($item->email)) {
                                            $displayValue = $item->email;
                                          } elseif (!empty($item->username)) {
                                            $displayValue = $item->username;
                                          } else {
                                            // Fallback to ID if no other field found
                                            $displayValue = 'ID: ' . $item->id;
                                          }
                                          
                                          return [$item->id => $displayValue];
                          })->toArray();
                                    
                                    // Sort options alphabetically
                                    asort($options);
                          }
                        @endphp
                        @foreach ($options as $value => $optionLabel)
                          <option value="{{ $value }}" {{ request()->get($column['name']) == $value ? 'selected' : '' }}>
                            {{ $optionLabel }}
                          </option>
                        @endforeach
                      </select>
                      @endif
                    </div>
                  @endforeach
                </div>
              </div>
            </div>
          </div>
          @endif
        </div>

        <!-- Preserve persistent-table status -->
        <input type="hidden" name="persistent-table" value="1">

        <div class="d-flex justify-content-end mb-3">
          <button type="submit" class="btn btn-primary">
            <i class="la la-search me-1"></i> {{ trans('backpack::crud.search') }}
          </button>
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

<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Ottiene tutti i parametri dell'URL corrente
    const urlParams = new URLSearchParams(window.location.search);
    
    // Itera su tutti i parametri
    for (const [key, value] of urlParams.entries()) {
      // Ignora parametri di sistema e valori vuoti
      if (key !== 'page' && key !== 'persistent-table' && value !== '') {
        // Trova tutte le label con class="filter-label" associate a questo campo
        const labels = document.querySelectorAll(`.filter-label[for="${key}"]`);
        
        // Se non ci sono label con for= esatto, cerca label all'interno del container del campo
        if (labels.length === 0) {
          const fieldContainers = document.querySelectorAll(`[name="${key}"]`);
          fieldContainers.forEach(container => {
            const parentCol = container.closest('.col-lg-3, .col-md-4, .col-sm-6');
            if (parentCol) {
              const fieldLabels = parentCol.querySelectorAll('.filter-label');
              fieldLabels.forEach(label => {
                // Evidenzia solo la label principale con classe filter-label
                label.classList.add('filter-active');
              });
            }
          });
        } else {
          // Applica classe alle label trovate direttamente
          labels.forEach(label => {
            label.classList.add('filter-active');
          });
        }
      }
    }
    
    // Assicurarsi che solo un collapse sia aperto alla volta
    const accordionBtns = document.querySelectorAll('.accordion-button');
    accordionBtns.forEach(btn => {
      btn.addEventListener('click', function() {
        // Quando un pulsante viene cliccato, chiudi tutti gli altri collapse
        const target = this.getAttribute('data-bs-target');
        document.querySelectorAll('.accordion-collapse.show').forEach(collapse => {
          const collapseId = '#' + collapse.id;
          if (collapseId !== target) {
            // Trova il pulsante associato a questo collapse
            const button = document.querySelector(`[data-bs-target="${collapseId}"]`);
            if (button) {
              button.classList.add('collapsed');
              button.setAttribute('aria-expanded', 'false');
            }
            // Chiudi il collapse senza animazione per evitare conflitti
            collapse.classList.remove('show');
          }
        });
      });
    });
  });
</script>
@endsection