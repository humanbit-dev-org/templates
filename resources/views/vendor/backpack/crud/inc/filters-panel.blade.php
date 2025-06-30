@php
use App\Http\Controllers\Admin\Helper\FilterHelper;

// Get filter configuration using helper
$filterConfig = FilterHelper::getFilterConfiguration($crud);
extract($filterConfig); // Extract all variables for backward compatibility
@endphp

<!-- Filter Panel (Collapsed by Default) -->
<div class="collapse {{ $hasFilters ? 'show' : '' }}" id="filterPanel">
  <form action="" method="GET" class="mt-3">

    <div class="row mb-3">
      <!-- Active Filters Summary -->
      <div class="col-12 mb-2">
        @php
        $activeFilters = FilterHelper::getActiveFilters($columns);
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
          $displayValue = FilterHelper::getFilterDisplayValue($key, $value, $columns, $crud);
          @endphp
          <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except(['page', $key]), ['persistent-table' => '1'])) }}"
            class="btn btn-filter">
            <strong class="filter-field-name">{{ $label }}</strong><span>: {{ $displayValue }}</span>
            <span class="filter-remove-icon">×</span>
          </a>
          @endforeach
          {{-- <a href="{{ url($crud->route) }}?persistent-table=1" class="btn btn-outline-secondary reset-btn">
            <i class="la la-times-circle me-1"></i> {{ trans('backpack::crud.reset') }}
          </a> --}}
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
            <i class="la la-align-left"></i> {{ trans('backpack::filters.text_filters') }}
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
            <i class="la la-file"></i> {{ trans('backpack::filters.uploaded_files') }}
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
            <i class="la la-check-square"></i> {{ trans('backpack::filters.status') }}
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
            <i class="la la-link"></i> {{ trans('backpack::filters.relations') }}
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
                <label for="{{ $column['name'] }}" class="form-label small text-muted mb-1 filter-label">{{ $label }}</label>
                <select name="{{ $column['name'] }}" class="form-select" id="{{ $column['name'] }}">
                  <option value="">{{ trans('backpack::filters.all') }}</option>
                  @php
                  $options = FilterHelper::getRelationOptions($column, $crud);
                  @endphp
                  @foreach ($options as $value => $optionLabel)
                  <option value="{{ $value }}" {{ request()->get($column['name']) == $value ? 'selected' : '' }}>
                    {{ $optionLabel }}
                  </option>
                  @endforeach
                </select>
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
        <i class="la la-search"></i> {{ trans('backpack::crud.search') }}
      </button>
    </div>
  </form>
</div> 