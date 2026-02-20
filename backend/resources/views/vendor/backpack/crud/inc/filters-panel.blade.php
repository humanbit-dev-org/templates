@php
use App\Http\Controllers\Admin\Helper\FilterHelper;

// Get filter configuration using helper
$filterConfig = FilterHelper::getFilterConfiguration($crud);
extract($filterConfig); // Extract all variables for backward compatibility
@endphp

<!-- Filter Panel (Collapsed by Default) -->
<div class="collapse" id="filterPanel">
  <form action="" method="GET" class="mt-3" id="filterForm">
    <!-- Hidden fields to preserve empty/not_empty filters -->
    @foreach($textColumns as $column)
      @if(request()->get($column['name'] . '_not_empty'))
        <input type="hidden" name="{{ $column['name'] }}_not_empty" value="{{ request()->get($column['name'] . '_not_empty') }}">
      @endif
      @if(request()->get($column['name'] . '_empty'))
        <input type="hidden" name="{{ $column['name'] }}_empty" value="{{ request()->get($column['name'] . '_empty') }}">
      @endif
    @endforeach

    <!-- Text Filters Accordion -->
    <div class="accordion mb-2" id="filtersAccordion">
      @if($textColumns->count() > 0)
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button"
            data-bs-toggle="collapse" data-bs-target="#textFiltersCollapse"
            aria-expanded="false" aria-controls="textFiltersCollapse">
            <i class="la la-align-left"></i> {{ trans('backpack::filters.value_filters') }}
            @if($textFilterCount > 0)
            <span class="badge bg-primary rounded-pill ms-2">{{ $textFilterCount }}</span>
            @endif
            <span class="filter-badges-container ms-2">
              @foreach($textColumns->pluck('name') as $name)
                <span class="filter-item-badge">{{ \App\Http\Controllers\Admin\Helper\FilterHelper::cleanFieldName($name) }}</span>
              @endforeach
            </span>
          </button>
        </h2>
        <div id="textFiltersCollapse" class="accordion-collapse collapse" data-bs-parent="#filtersAccordion">
          <div class="accordion-body">
            <div class="row g-1">
              @foreach ($textColumns as $column)
              @php
              $label = isset($column['label']) ? $column['label'] : $column['name'];
              $inputType = FilterHelper::getInputType($column, $tableName);
              $step = $inputType === 'number' ? FilterHelper::getNumberStep($column, $tableName) : null;
              
              // Check if this field has active filters
              $hasActiveFilter = request()->get($column['name']) || 
                                request()->get($column['name'] . '_not_empty') || 
                                request()->get($column['name'] . '_empty');
              @endphp
              <div class="col-lg-3 col-md-6 col-sm-12">
                <div class="filter-field-container {{ $hasActiveFilter ? 'has-active-filter' : '' }}">
                  <div class="d-flex align-items-center justify-content-between mb-1">
                    <label for="{{ $column['name'] }}" class="form-label small text-muted mb-0 filter-label fw-semibold" style="font-size: 0.8rem;">{{ $label }}</label>
                    <div class="filter-radio-group" role="group">
                      <input type="radio" class="btn-check" name="{{ $column['name'] }}_empty_filter" 
                             id="{{ $column['name'] }}_all" value=""
                             {{ !request()->get($column['name'] . '_not_empty') && !request()->get($column['name'] . '_empty') ? 'checked' : '' }}>
                      <label class="btn btn-outline-primary" for="{{ $column['name'] }}_all">Tutti</label>

                      <input type="radio" class="btn-check" name="{{ $column['name'] }}_empty_filter" 
                             id="{{ $column['name'] }}_not_empty" value="not_empty"
                             {{ request()->get($column['name'] . '_not_empty') == '1' ? 'checked' : '' }}>
                      <label class="btn btn-outline-success" for="{{ $column['name'] }}_not_empty">Non vuoto</label>

                      <input type="radio" class="btn-check" name="{{ $column['name'] }}_empty_filter" 
                             id="{{ $column['name'] }}_empty" value="empty"
                             {{ request()->get($column['name'] . '_empty') == '1' ? 'checked' : '' }}>
                      <label class="btn btn-outline-danger" for="{{ $column['name'] }}_empty">Vuoto</label>
                    </div>
                  </div>
                  <div class="position-relative">
                    <input autocomplete="off" 
                           type="{{ $inputType }}" 
                           name="{{ $column['name'] }}"
                           value="{{ request()->get($column['name']) }}"
                           class="form-control form-control-sm autocomplete-input"
                           data-column="{{ $column['name'] }}"
                           data-table="{{ $crud->model->getTable() }}"
                           data-autocomplete-url="{{ backpack_url('autocomplete-values') }}"
                           id="{{ $column['name'] }}"
                           placeholder="filtra per {{ strtolower($label) }}"
                           style="font-size: 0.75rem; padding: 0.25rem 0.4rem;"
                           @if($step) step="{{ $step }}" @endif>
                    <div class="autocomplete-suggestions" id="autocomplete-{{ $column['name'] }}"></div>
                  </div>
                </div>
              </div>
              @endforeach
            </div>
            <div class="d-flex justify-content-end mt-2">
              <button type="submit" class="btn btn-primary btn-sm filter-search-btn" form="filterForm">
                <i class="la la-search"></i> {{ trans('backpack::crud.search') }}
              </button>
            </div>
          </div>
        </div>
      </div>
      @endif

      <!-- Path Fields Filters Accordion -->
      @if($pathFields->count() > 0)
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button"
            data-bs-toggle="collapse" data-bs-target="#pathFiltersCollapse"
            aria-expanded="false" aria-controls="pathFiltersCollapse">
            <i class="la la-file"></i> {{ trans('backpack::filters.uploaded_files') }}
            @if($pathFilterCount > 0)
            <span class="badge bg-primary rounded-pill ms-2">{{ $pathFilterCount }}</span>
            @endif
          </button>
        </h2>
        <div id="pathFiltersCollapse" class="accordion-collapse collapse" data-bs-parent="#filtersAccordion">
          <div class="accordion-body">
            <div class="row g-1">
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
            <div class="d-flex justify-content-end mt-2">
              <button type="submit" class="btn btn-primary btn-sm filter-search-btn" form="filterForm">
                <i class="la la-search"></i> {{ trans('backpack::crud.search') }}
              </button>
            </div>
          </div>
        </div>
      </div>
      @endif

      <!-- Boolean Filters Accordion -->
      @if($booleanColumns->count() > 0)
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button"
            data-bs-toggle="collapse" data-bs-target="#booleanFiltersCollapse"
            aria-expanded="false" aria-controls="booleanFiltersCollapse">
            <i class="la la-check-square"></i> {{ trans('backpack::filters.status') }}
            @if($booleanFilterCount > 0)
            <span class="badge bg-primary rounded-pill ms-2">{{ $booleanFilterCount }}</span>
            @endif
            <span class="filter-badges-container ms-2">
              @foreach($booleanColumns->pluck('name') as $name)
                <span class="filter-item-badge">{{ \App\Http\Controllers\Admin\Helper\FilterHelper::cleanFieldName($name) }}</span>
              @endforeach
            </span>
          </button>
        </h2>
        <div id="booleanFiltersCollapse" class="accordion-collapse collapse" data-bs-parent="#filtersAccordion">
          <div class="accordion-body">
            <div class="row g-1">
              @foreach ($booleanColumns as $column)
              @php
              $label = isset($column['label']) ? $column['label'] : $column['name'];
              
              // Check if this field has active filters
              $hasActiveFilter = request()->get($column['name']) !== null && request()->get($column['name']) !== '';
              @endphp

              <div class="col-lg-3 col-md-6 col-sm-12">
                <div class="filter-field-container {{ $hasActiveFilter ? 'has-active-filter' : '' }}">
                  <div class="d-flex align-items-center justify-content-between mb-1">
                    <label class="form-label small text-muted mb-0 filter-label fw-semibold" style="font-size: 0.8rem;">{{ $label }}</label>
                    <div class="filter-radio-group" role="group">
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
                </div>
              </div>
              @endforeach
            </div>
            <div class="d-flex justify-content-end mt-2">
              <button type="submit" class="btn btn-primary btn-sm filter-search-btn" form="filterForm">
                <i class="la la-search"></i> {{ trans('backpack::crud.search') }}
              </button>
            </div>
          </div>
        </div>
      </div>
      @endif

      <!-- Date Filters Accordion -->
      @if($dateColumns->count() > 0)
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button"
            data-bs-toggle="collapse" data-bs-target="#dateFiltersCollapse"
            aria-expanded="false" aria-controls="dateFiltersCollapse">
            <i class="la la-calendar"></i> {{ trans('backpack::filters.date_filters') }}
            @if($dateFilterCount > 0)
            <span class="badge bg-primary rounded-pill ms-2">{{ $dateFilterCount }}</span>
            @endif
            <span class="filter-badges-container ms-2">
              @foreach($dateColumns->pluck('name') as $name)
                <span class="filter-item-badge">{{ \App\Http\Controllers\Admin\Helper\FilterHelper::cleanFieldName($name) }}</span>
              @endforeach
            </span>
          </button>
        </h2>
        <div id="dateFiltersCollapse" class="accordion-collapse collapse" data-bs-parent="#filtersAccordion">
          <div class="accordion-body">
            <div class="row g-1">
              @foreach ($dateColumns as $column)
              @php
              $label = isset($column['label']) ? $column['label'] : ucfirst($column['name']);
              $inputType = 'date'; // Always use simple date picker (time defaults to midnight)
              $hasRange = request()->get($column['name'] . '_from') || request()->get($column['name'] . '_to');
              
              // Check if this field has active filters
              $hasActiveFilter = request()->get($column['name']) || 
                                request()->get($column['name'] . '_from') || 
                                request()->get($column['name'] . '_to');
              @endphp
              <div class="col-lg-3 col-md-6 col-sm-12">
                <div class="filter-field-container {{ $hasActiveFilter ? 'has-active-filter' : '' }}">
                  <div class="d-flex align-items-center justify-content-between mb-1">
                    <label class="form-label small text-muted mb-0 filter-label fw-semibold" style="font-size: 0.8rem;">{{ $label }}</label>
                    <!-- Date Filter Type Toggle -->
                    <div class="btn-group btn-group-sm date-filter-toggle" role="group">
                      <input type="radio" class="btn-check" name="{{ $column['name'] }}_type" id="{{ $column['name'] }}_exact"
                             value="exact" {{ !$hasRange ? 'checked' : '' }}>
                      <label class="btn btn-outline-secondary" for="{{ $column['name'] }}_exact">
                        <i class="la la-calendar me-1"></i>{{ trans('backpack::filters.exact_date') }}
                      </label>

                      <input type="radio" class="btn-check" name="{{ $column['name'] }}_type" id="{{ $column['name'] }}_range"
                             value="range" {{ $hasRange ? 'checked' : '' }}>
                      <label class="btn btn-outline-secondary" for="{{ $column['name'] }}_range">
                        <i class="la la-calendar-check me-1"></i>{{ trans('backpack::filters.date_range') }}
                      </label>
                    </div>
                  </div>

                  <!-- Exact Date Input -->
                  <div id="{{ $column['name'] }}_exact_section" class="date-filter-section" style="{{ $hasRange ? 'display: none;' : '' }}">
                    <input autocomplete="off"
                           type="{{ $inputType }}"
                           name="{{ $column['name'] }}"
                           value="{{ request()->get($column['name']) }}"
                           class="form-control form-control-sm"
                           id="{{ $column['name'] }}"
                           placeholder="{{ trans('backpack::filters.select_date') }}"
                           style="font-size: 0.75rem; padding: 0.25rem 0.4rem;">
                  </div>

                  <!-- Date Range Inputs -->
                  <div id="{{ $column['name'] }}_range_section" class="date-filter-section date-range-inputs" style="{{ !$hasRange ? 'display: none;' : '' }}">
                    <div class="row g-1">
                      <div class="col-6">
                        <input autocomplete="off"
                               type="{{ $inputType }}"
                               name="{{ $column['name'] }}_from"
                               value="{{ request()->get($column['name'] . '_from') }}"
                               class="form-control form-control-sm"
                               id="{{ $column['name'] }}_from"
                               placeholder="{{ trans('backpack::filters.start_date') }}"
                               style="font-size: 0.75rem; padding: 0.25rem 0.4rem;">
                      </div>
                      <div class="col-6">
                        <input autocomplete="off"
                               type="{{ $inputType }}"
                               name="{{ $column['name'] }}_to"
                               value="{{ request()->get($column['name'] . '_to') }}"
                               class="form-control form-control-sm"
                               id="{{ $column['name'] }}_to"
                               placeholder="{{ trans('backpack::filters.end_date') }}"
                               style="font-size: 0.75rem; padding: 0.25rem 0.4rem;">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              @endforeach
            </div>
            <div class="d-flex justify-content-end mt-2">
              <button type="submit" class="btn btn-primary btn-sm filter-search-btn" form="filterForm">
                <i class="la la-search"></i> {{ trans('backpack::crud.search') }}
              </button>
            </div>
          </div>
        </div>
      </div>
      @endif

      <!-- Select Filters Accordion (Status, Enums, etc.) -->
      @if($selectColumns->count() > 0)
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button"
            data-bs-toggle="collapse" data-bs-target="#selectFiltersCollapse"
            aria-expanded="false" aria-controls="selectFiltersCollapse">
            <i class="la la-list"></i> {{ trans('backpack::filters.select_filters') }}
            @if($selectFilterCount > 0)
            <span class="badge bg-primary rounded-pill ms-2">{{ $selectFilterCount }}</span>
            @endif
            <span class="filter-badges-container ms-2">
              @foreach($selectColumns->pluck('name') as $name)
                <span class="filter-item-badge">{{ \App\Http\Controllers\Admin\Helper\FilterHelper::cleanFieldName($name) }}</span>
              @endforeach
            </span>
          </button>
        </h2>
        <div id="selectFiltersCollapse" class="accordion-collapse collapse" data-bs-parent="#filtersAccordion">
          <div class="accordion-body">
            <div class="row g-1">
              @foreach ($selectColumns as $column)
              @php
              $label = isset($column['label']) ? $column['label'] : ucfirst($column['name']);
              $options = FilterHelper::getSelectOptions($column, $tableName);
              $currentValue = request()->get($column['name']);
              
              // Check if this field has active filters
              $hasActiveFilter = $currentValue !== null && $currentValue !== '';
              @endphp

              <div class="col-lg-3 col-md-6 col-sm-12">
                <div class="filter-field-container {{ $hasActiveFilter ? 'has-active-filter' : '' }}">
                  <label for="{{ $column['name'] }}" class="form-label small text-muted mb-1 filter-label fw-semibold" style="font-size: 0.8rem;">{{ $label }}</label>
                  <select name="{{ $column['name'] }}" class="form-select form-select-sm" id="{{ $column['name'] }}" style="font-size: 0.75rem; padding: 0.25rem 0.4rem;">
                    <option value="">{{ trans('backpack::filters.all') }}</option>
                    @foreach ($options as $value => $optionLabel)
                    <option value="{{ $value }}" {{ $currentValue == $value ? 'selected' : '' }}>
                      {{ $optionLabel }}
                    </option>
                    @endforeach
                  </select>
                </div>
              </div>
              @endforeach
            </div>
            <div class="d-flex justify-content-end mt-2">
              <button type="submit" class="btn btn-primary btn-sm filter-search-btn" form="filterForm">
                <i class="la la-search"></i> {{ trans('backpack::crud.search') }}
              </button>
            </div>
          </div>
        </div>
      </div>
      @endif

      <!-- Relation Filters Accordion (includes both belongsTo and hasMany) -->
      @php
      // Check if hasMany filters are active
      $hasActiveHasManyFilter = false;
      if (!empty($hasManyRelations)) {
        foreach ($hasManyRelations as $relation) {
          if (isset($relation['searchable_keys'])) {
            foreach ($relation['searchable_keys'] as $keyInfo) {
              if (request()->filled($relation['name'] . '_' . $keyInfo['field'])) {
                $hasActiveHasManyFilter = true;
                break 2;
              }
            }
          }
        }
      }
      
      // Combined count and open state
      $totalRelationFilterCount = $relationFilterCount + $hasManyRelationsCount;
      // All accordions stay closed by default
      $shouldOpenRelations = false;
      
      // Combine all relation names (belongsTo + hasMany) for title
      $allRelationNames = $relationColumns->pluck('name')
        ->merge(collect($hasManyRelations)->pluck('name'))
        ->map(function($name) { 
          return \App\Http\Controllers\Admin\Helper\FilterHelper::cleanFieldName($name); 
        })
        ->implode(', ');
        
      // Get current model name for section titles
      $modelName = class_basename($crud->model);
      @endphp
      
      @if($relationColumns->count() > 0 || !empty($hasManyRelations))
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button"
            data-bs-toggle="collapse" data-bs-target="#relationFiltersCollapse"
            aria-expanded="false" aria-controls="relationFiltersCollapse">
            <i class="la la-link"></i> {{ trans('backpack::filters.relations') }}
            @if($totalRelationFilterCount > 0)
            <span class="badge bg-primary rounded-pill ms-2">{{ $totalRelationFilterCount }}</span>
            @endif
            <span class="filter-badges-container ms-2">
              @foreach($relationColumns->pluck('name')->merge(collect($hasManyRelations)->pluck('name')) as $name)
                <span class="filter-item-badge">{{ \App\Http\Controllers\Admin\Helper\FilterHelper::cleanFieldName($name) }}</span>
              @endforeach
            </span>
            @if($activeFilters->count() > 0)
            <small class="text-info ms-2 fw-bold" style="font-size: 0.7rem;">
              <i class="la la-filter me-1"></i>Le relazioni mostrate sono filtrate dai filtri attivi
            </small>
            @endif
          </button>
        </h2>
        <div id="relationFiltersCollapse" class="accordion-collapse collapse" data-bs-parent="#filtersAccordion">
          <div class="accordion-body">
            <div class="row g-1">
            @if($relationColumns->count() > 0)
            {{-- BelongsTo Relations --}}
                @foreach ($relationColumns as $column)
              @php
              $label = isset($column['label']) ? $column['label'] : $column['name'];
              @endphp

                <div class="col-lg-3 col-md-6 col-sm-12">
                  @php
                  $currentValue = request()->get($column['name']);
                  $hasActiveFilter = $currentValue !== null && $currentValue !== '' || 
                                    request()->get($column['name'] . '_not_empty') == '1' || 
                                    request()->get($column['name'] . '_empty') == '1';
                  
                  // Get relation info for description text and placeholder
                  $relationInfo = FilterHelper::getRelationFilterInfo($column, $crud, false);
                  $placeholderText = "cerca " . strtolower($relationInfo['primary_key']) . " di " . strtolower($relationInfo['related_table_singular']) . " e trova " . strtolower($relationInfo['current_table_singular']);
                  @endphp
                  <div class="filter-field-container {{ $hasActiveFilter ? 'has-active-filter' : '' }}">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                      <label for="{{ $column['name'] }}" class="form-label small text-muted mb-0 filter-label fw-semibold" style="font-size: 0.8rem;">{{ $label }}</label>
                      <div class="filter-radio-group" role="group">
                        <input type="radio" class="btn-check" name="{{ $column['name'] }}_empty_filter" 
                               id="{{ $column['name'] }}_all" value=""
                               {{ !request()->get($column['name'] . '_not_empty') && !request()->get($column['name'] . '_empty') ? 'checked' : '' }}>
                        <label class="btn btn-outline-primary" for="{{ $column['name'] }}_all">Tutti</label>

                        <input type="radio" class="btn-check" name="{{ $column['name'] }}_empty_filter" 
                               id="{{ $column['name'] }}_not_empty" value="not_empty"
                               {{ request()->get($column['name'] . '_not_empty') == '1' ? 'checked' : '' }}>
                        <label class="btn btn-outline-success" for="{{ $column['name'] }}_not_empty">Non vuoto</label>

                        <input type="radio" class="btn-check" name="{{ $column['name'] }}_empty_filter" 
                               id="{{ $column['name'] }}_empty" value="empty"
                               {{ request()->get($column['name'] . '_empty') == '1' ? 'checked' : '' }}>
                        <label class="btn btn-outline-danger" for="{{ $column['name'] }}_empty">Vuoto</label>
                      </div>
                    </div>
                    <div class="position-relative">
                      @php
                      // Get display value for current value if exists
                      $displayValue = $currentValue;
                      if ($currentValue) {
                          $displayValue = \App\Http\Controllers\Admin\Helper\FilterHelper::getFilterDisplayValue($column['name'], $currentValue, collect([$column]), $crud);
                      }
                      @endphp
                      <input autocomplete="off" 
                             type="text" 
                             class="form-control form-control-sm autocomplete-input relation-autocomplete-input"
                             data-column="{{ $column['name'] }}"
                             data-table="{{ $crud->model->getTable() }}"
                             data-relation-column="{{ $column['name'] }}"
                             id="{{ $column['name'] }}_display"
                             value="{{ $displayValue }}"
                             placeholder="{{ $placeholderText }}"
                             style="font-size: 0.75rem; padding: 0.25rem 0.4rem;">
                      <input type="hidden" 
                             name="{{ $column['name'] }}"
                             id="{{ $column['name'] }}"
                             value="{{ $currentValue }}">
                      <div class="autocomplete-suggestions" id="autocomplete-{{ $column['name'] }}_display"></div>
                    </div>
                  </div>
                </div>
                @endforeach
            @endif
            
            @if(!empty($hasManyRelations))
            {{-- HasMany Relations (Inverse) --}}
                @foreach ($hasManyRelations as $relation)
              @php
              $relationLabel = ucfirst(str_replace('_', ' ', $relation['name']));
              
              // Check if ANY searchable key has an active filter for this relation
              $hasActiveFilter = false;
              $selectedLabel = 'cerca';
              if (isset($relation['searchable_keys'])) {
                  foreach ($relation['searchable_keys'] as $keyInfo) {
                      $filterKey = $relation['name'] . '_' . $keyInfo['field'];
                      $value = request()->get($filterKey);
                      if ($value !== null && $value !== '') {
                          $hasActiveFilter = true;
                          $selectedLabel = $keyInfo['label'] . ': ' . $value;
                          break;
                      }
                  }
              }
              @endphp

              {{-- HasMany relation filter - same style as belongsTo --}}
              @if(isset($relation['searchable_keys']))
              <div class="col-lg-3 col-md-6 col-sm-12">
                @php
                // Get first searchable key (primary key or unique field)
                $firstKey = $relation['searchable_keys'][0];
                $filterKey = $relation['name'] . '_' . $firstKey['field'];
                $currentValue = request()->get($filterKey);
                $hasActiveFilter = $currentValue !== null && $currentValue !== '' || 
                                  request()->get($filterKey . '_not_empty') == '1' || 
                                  request()->get($filterKey . '_empty') == '1';
                
                // Get relation info for placeholder
                $hasManyRelationInfo = FilterHelper::getRelationFilterInfo(null, $crud, true, $relation);
                $placeholderText = "cerca " . strtolower($hasManyRelationInfo['primary_key']);
                if ($hasManyRelationInfo['unique_field']) {
                    $placeholderText .= " o " . strtolower($hasManyRelationInfo['unique_field']);
                }
                $placeholderText .= " di " . strtolower($hasManyRelationInfo['related_table_singular']) . " e trova " . strtolower($hasManyRelationInfo['current_table_singular']);
                @endphp
                <div class="filter-field-container {{ $hasActiveFilter ? 'has-active-filter' : '' }}">
                  <div class="d-flex align-items-center justify-content-between mb-1">
                    <label for="{{ $filterKey }}" class="form-label small text-muted mb-0 filter-label fw-semibold" style="font-size: 0.8rem;">{{ $relationLabel }}</label>
                    <div class="filter-radio-group" role="group">
                      <input type="radio" class="btn-check" name="{{ $filterKey }}_empty_filter" 
                             id="{{ $filterKey }}_all" value=""
                             {{ !request()->get($filterKey . '_not_empty') && !request()->get($filterKey . '_empty') ? 'checked' : '' }}>
                      <label class="btn btn-outline-primary" for="{{ $filterKey }}_all">Tutti</label>

                      <input type="radio" class="btn-check" name="{{ $filterKey }}_empty_filter" 
                             id="{{ $filterKey }}_not_empty" value="not_empty"
                             {{ request()->get($filterKey . '_not_empty') == '1' ? 'checked' : '' }}>
                      <label class="btn btn-outline-success" for="{{ $filterKey }}_not_empty">Non vuoto</label>

                      <input type="radio" class="btn-check" name="{{ $filterKey }}_empty_filter" 
                             id="{{ $filterKey }}_empty" value="empty"
                             {{ request()->get($filterKey . '_empty') == '1' ? 'checked' : '' }}>
                      <label class="btn btn-outline-danger" for="{{ $filterKey }}_empty">Vuoto</label>
                    </div>
                  </div>
                  <div class="position-relative">
                    @php
                    // Get display value for current value if exists (for hasMany, use helper to get unique field or primary key)
                    $displayValue = $currentValue;
                    if ($currentValue) {
                        $relatedModelClass = $relation['model'] ?? null;
                        if (!$relatedModelClass && isset($relation['name'])) {
                            $relatedModelName = \Illuminate\Support\Str::studly($relation['name']);
                            $relatedModelClass = "App\Models\\" . $relatedModelName;
                        }
                        if ($relatedModelClass && class_exists($relatedModelClass)) {
                            $displayValue = \App\Http\Controllers\Admin\Helper\FilterHelper::getHasManyFilterDisplayValue($relatedModelClass, $currentValue);
                        }
                    }
                    @endphp
                    <input autocomplete="off" 
                           type="text" 
                           class="form-control form-control-sm autocomplete-input relation-autocomplete-input hasmany-autocomplete-input"
                           data-column="{{ $firstKey['field'] }}"
                           data-table="{{ $crud->model->getTable() }}"
                           data-relation="{{ $relation['name'] }}"
                           data-model="{{ is_string($crud->model) ? $crud->model : get_class($crud->model) }}"
                           data-searchable-keys="{{ base64_encode(json_encode($relation['searchable_keys'])) }}"
                           id="{{ $filterKey }}_display"
                           value="{{ $displayValue }}"
                           placeholder="{{ $placeholderText }}"
                           style="font-size: 0.75rem; padding: 0.25rem 0.4rem;">
                    <input type="hidden" 
                           name="{{ $filterKey }}"
                           id="{{ $filterKey }}"
                           value="{{ $currentValue }}">
                    <div class="autocomplete-suggestions" id="autocomplete-{{ $filterKey }}_display"></div>
                  </div>
                </div>
              </div>
              @endif
                @endforeach
            @endif
            <div class="d-flex justify-content-end mt-2">
              <button type="submit" class="btn btn-primary btn-sm filter-search-btn" form="filterForm">
                <i class="la la-search"></i> {{ trans('backpack::crud.search') }}
              </button>
            </div>
            </div>
          </div>
        </div>
      </div>
      @endif

      <!-- Advanced Filters Accordion -->
      @if($advancedTextColumns->count() > 0 || $advancedRelationColumns->count() > 0 || $advancedDateColumns->count() > 0)
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button"
            data-bs-toggle="collapse" data-bs-target="#advancedFiltersCollapse"
            aria-expanded="false" aria-controls="advancedFiltersCollapse">
            <i class="la la-cog"></i> {{ trans('backpack::filters.advanced_filters') }}
            @if($advancedFilterCount > 0)
            <span class="badge bg-warning rounded-pill ms-2">{{ $advancedFilterCount }}</span>
            @endif
            <span class="filter-badges-container ms-2">
              @foreach($advancedTextColumns->pluck('name')->merge($advancedRelationColumns->pluck('name'))->merge($advancedDateColumns->pluck('name')) as $name)
                <span class="filter-item-badge">{{ \App\Http\Controllers\Admin\Helper\FilterHelper::cleanFieldName($name) }}</span>
              @endforeach
            </span>
          </button>
        </h2>
        <div id="advancedFiltersCollapse" class="accordion-collapse collapse" data-bs-parent="#filtersAccordion">
          <div class="accordion-body">
            <div class="row g-1">
              <!-- Advanced Text Filters -->
              @foreach ($advancedTextColumns as $column)
              @php
              $label = isset($column['label']) ? $column['label'] : $column['name'];
              $inputType = FilterHelper::getInputType($column, $tableName);
              $step = $inputType === 'number' ? FilterHelper::getNumberStep($column, $tableName) : null;
              
              // Check if this field has active filters
              $hasActiveFilter = request()->get($column['name']) !== null && request()->get($column['name']) !== '' ||
                                request()->get($column['name'] . '_not_empty') == '1' ||
                                request()->get($column['name'] . '_empty') == '1';
              @endphp
              <div class="col-lg-3 col-md-6 col-sm-12">
                <div class="filter-field-container {{ $hasActiveFilter ? 'has-active-filter' : '' }}">
                  <div class="d-flex align-items-center justify-content-between mb-1">
                    <label for="{{ $column['name'] }}" class="form-label small text-muted mb-0 filter-label fw-semibold" style="font-size: 0.8rem;">{{ $label }}</label>
                    <div class="filter-radio-group" role="group">
                      <input type="radio" class="btn-check" name="{{ $column['name'] }}_empty_filter" 
                             id="{{ $column['name'] }}_all" value=""
                             {{ !request()->get($column['name'] . '_not_empty') && !request()->get($column['name'] . '_empty') ? 'checked' : '' }}>
                      <label class="btn btn-outline-primary" for="{{ $column['name'] }}_all">Tutti</label>

                      <input type="radio" class="btn-check" name="{{ $column['name'] }}_empty_filter" 
                             id="{{ $column['name'] }}_not_empty" value="not_empty"
                             {{ request()->get($column['name'] . '_not_empty') == '1' ? 'checked' : '' }}>
                      <label class="btn btn-outline-success" for="{{ $column['name'] }}_not_empty">Non vuoto</label>

                      <input type="radio" class="btn-check" name="{{ $column['name'] }}_empty_filter" 
                             id="{{ $column['name'] }}_empty" value="empty"
                             {{ request()->get($column['name'] . '_empty') == '1' ? 'checked' : '' }}>
                      <label class="btn btn-outline-danger" for="{{ $column['name'] }}_empty">Vuoto</label>
                    </div>
                  </div>
                  <div class="position-relative">
                    <input autocomplete="off" 
                           type="{{ $inputType }}" 
                           name="{{ $column['name'] }}"
                           value="{{ request()->get($column['name']) }}"
                           class="form-control form-control-sm autocomplete-input"
                           data-column="{{ $column['name'] }}"
                           data-table="{{ $crud->model->getTable() }}"
                           data-autocomplete-url="{{ backpack_url('autocomplete-values') }}"
                           id="{{ $column['name'] }}"
                           placeholder="filtra per {{ strtolower($label) }}"
                           style="font-size: 0.75rem; padding: 0.25rem 0.4rem;"
                           @if($step) step="{{ $step }}" @endif>
                    <div class="autocomplete-suggestions" id="autocomplete-{{ $column['name'] }}"></div>
                  </div>
                </div>
              </div>
              @endforeach

              <!-- Advanced Relation Filters -->
              @foreach ($advancedRelationColumns as $column)
              @php
              $label = isset($column['label']) ? $column['label'] : $column['name'];
              $currentValue = request()->get($column['name']);
              $hasActiveFilter = $currentValue !== null && $currentValue !== '' || 
                                request()->get($column['name'] . '_not_empty') == '1' || 
                                request()->get($column['name'] . '_empty') == '1';
              
              // Get relation options to check if we should use select instead of autocomplete
              $relationOptions = \App\Http\Controllers\Admin\Helper\FilterHelper::getRelationOptions($column, $crud);
              $useSelect = count($relationOptions) <= 50; // Use select if 50 or fewer options
              @endphp
              <div class="col-lg-3 col-md-6 col-sm-12">
                <div class="filter-field-container {{ $hasActiveFilter ? 'has-active-filter' : '' }}">
                  <div class="d-flex align-items-center justify-content-between mb-1">
                    <label for="{{ $column['name'] }}" class="form-label small text-muted mb-0 filter-label fw-semibold" style="font-size: 0.8rem;">{{ $label }}</label>
                    <div class="filter-radio-group" role="group">
                      <input type="radio" class="btn-check" name="{{ $column['name'] }}_empty_filter" 
                             id="{{ $column['name'] }}_all" value=""
                             {{ !request()->get($column['name'] . '_not_empty') && !request()->get($column['name'] . '_empty') ? 'checked' : '' }}>
                      <label class="btn btn-outline-primary" for="{{ $column['name'] }}_all">Tutti</label>

                      <input type="radio" class="btn-check" name="{{ $column['name'] }}_empty_filter" 
                             id="{{ $column['name'] }}_not_empty" value="not_empty"
                             {{ request()->get($column['name'] . '_not_empty') == '1' ? 'checked' : '' }}>
                      <label class="btn btn-outline-success" for="{{ $column['name'] }}_not_empty">Non vuoto</label>

                      <input type="radio" class="btn-check" name="{{ $column['name'] }}_empty_filter" 
                             id="{{ $column['name'] }}_empty" value="empty"
                             {{ request()->get($column['name'] . '_empty') == '1' ? 'checked' : '' }}>
                      <label class="btn btn-outline-danger" for="{{ $column['name'] }}_empty">Vuoto</label>
                    </div>
                  </div>
                    <div class="position-relative">
                      @if($useSelect)
                        {{-- Use select dropdown for small datasets --}}
                        <select name="{{ $column['name'] }}" 
                                class="form-select form-select-sm" 
                                id="{{ $column['name'] }}" 
                                style="font-size: 0.75rem; padding: 0.25rem 0.4rem;">
                          <option value="">{{ trans('backpack::filters.all') }}</option>
                          @foreach ($relationOptions as $optionValue => $optionLabel)
                          <option value="{{ $optionValue }}" {{ $currentValue == $optionValue ? 'selected' : '' }}>
                            {{ $optionLabel }}
                          </option>
                          @endforeach
                        </select>
                      @else
                        {{-- Use autocomplete for larger datasets --}}
                        @php
                        // Get relation info for placeholder
                        $relationInfo = FilterHelper::getRelationFilterInfo($column, $crud, false);
                        $placeholderText = "cerca " . strtolower($relationInfo['primary_key']) . " di " . strtolower($relationInfo['related_table_singular']) . " e trova " . strtolower($relationInfo['current_table_singular']);
                        
                        // Get display value for current value if exists
                        $displayValue = $currentValue;
                        if ($currentValue) {
                            $displayValue = \App\Http\Controllers\Admin\Helper\FilterHelper::getFilterDisplayValue($column['name'], $currentValue, collect([$column]), $crud);
                        }
                        @endphp
                        <input autocomplete="off" 
                               type="text" 
                               class="form-control form-control-sm autocomplete-input relation-autocomplete-input"
                               data-column="{{ $column['name'] }}"
                               data-table="{{ $crud->model->getTable() }}"
                               data-relation-column="{{ $column['name'] }}"
                               id="{{ $column['name'] }}_display"
                               value="{{ $displayValue }}"
                               placeholder="{{ $placeholderText }}"
                               style="font-size: 0.75rem; padding: 0.25rem 0.4rem;">
                        <input type="hidden" 
                               name="{{ $column['name'] }}"
                               id="{{ $column['name'] }}"
                               value="{{ $currentValue }}">
                        <div class="autocomplete-suggestions" id="autocomplete-{{ $column['name'] }}_display"></div>
                      @endif
                    </div>
                </div>
              </div>
              @endforeach
              
              <!-- Advanced Date Filters (created_at, updated_at) -->
              @foreach ($advancedDateColumns as $column)
              @php
              $label = isset($column['label']) ? $column['label'] : ucfirst($column['name']);
              $inputType = 'date'; // Always use simple date picker (time defaults to midnight)
              $hasRange = request()->get($column['name'] . '_from') || request()->get($column['name'] . '_to');

              // Check if this field has active filters
              $hasActiveFilter = request()->get($column['name']) ||
                                request()->get($column['name'] . '_from') ||
                                request()->get($column['name'] . '_to');
              @endphp
              <div class="col-lg-3 col-md-6 col-sm-12">
                <div class="filter-field-container {{ $hasActiveFilter ? 'has-active-filter' : '' }}">
                  <div class="d-flex align-items-center justify-content-between mb-1">
                    <label class="form-label small text-muted mb-0 filter-label fw-semibold" style="font-size: 0.8rem;">{{ $label }}</label>
                    <!-- Date Filter Type Toggle -->
                    <div class="btn-group btn-group-sm date-filter-toggle" role="group">
                      <input type="radio" class="btn-check" name="{{ $column['name'] }}_type" id="{{ $column['name'] }}_exact"
                             value="exact" {{ !$hasRange ? 'checked' : '' }}>
                      <label class="btn btn-outline-secondary" for="{{ $column['name'] }}_exact">
                        <i class="la la-calendar me-1"></i>{{ trans('backpack::filters.exact_date') }}
                      </label>

                      <input type="radio" class="btn-check" name="{{ $column['name'] }}_type" id="{{ $column['name'] }}_range"
                             value="range" {{ $hasRange ? 'checked' : '' }}>
                      <label class="btn btn-outline-secondary" for="{{ $column['name'] }}_range">
                        <i class="la la-calendar-check me-1"></i>{{ trans('backpack::filters.date_range') }}
                      </label>
                    </div>
                  </div>

                  <!-- Exact Date Input -->
                  <div id="{{ $column['name'] }}_exact_section" class="date-filter-section" style="{{ $hasRange ? 'display: none;' : '' }}">
                    <input autocomplete="off"
                           type="{{ $inputType }}"
                           name="{{ $column['name'] }}"
                           value="{{ request()->get($column['name']) }}"
                           class="form-control form-control-sm"
                           id="{{ $column['name'] }}"
                           placeholder="{{ trans('backpack::filters.select_date') }}"
                           style="font-size: 0.75rem; padding: 0.25rem 0.4rem;">
                  </div>

                  <!-- Date Range Inputs -->
                  <div id="{{ $column['name'] }}_range_section" class="date-filter-section date-range-inputs" style="{{ !$hasRange ? 'display: none;' : '' }}">
                    <div class="row g-1">
                      <div class="col-6">
                        <input autocomplete="off"
                               type="{{ $inputType }}"
                               name="{{ $column['name'] }}_from"
                               value="{{ request()->get($column['name'] . '_from') }}"
                               class="form-control form-control-sm"
                               id="{{ $column['name'] }}_from"
                               placeholder="{{ trans('backpack::filters.start_date') }}"
                               style="font-size: 0.75rem; padding: 0.25rem 0.4rem;">
                      </div>
                      <div class="col-6">
                        <input autocomplete="off"
                               type="{{ $inputType }}"
                               name="{{ $column['name'] }}_to"
                               value="{{ request()->get($column['name'] . '_to') }}"
                               class="form-control form-control-sm"
                               id="{{ $column['name'] }}_to"
                               placeholder="{{ trans('backpack::filters.end_date') }}"
                               style="font-size: 0.75rem; padding: 0.25rem 0.4rem;">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              @endforeach
            </div>
            <div class="d-flex justify-content-end mt-2">
              <button type="submit" class="btn btn-primary btn-sm filter-search-btn" form="filterForm">
                <i class="la la-search"></i> {{ trans('backpack::crud.search') }}
              </button>
            </div>
          </div>
        </div>
      </div>
      @endif
    </div>

    <!-- Preserve persistent-table status -->
    <input type="hidden" name="persistent-table" value="1">
  </form>
</div>


<script>
// Flash animation removed for cleaner UX

document.addEventListener('DOMContentLoaded', function() {
    // Initialize relation autocomplete functionality
    initializeRelationAutocomplete();
    
    // Initialize value filters autocomplete
    initializeValueFiltersAutocomplete();

    // Initialize date filter toggles
    initializeDateFilterToggles();

    // Initialize empty filter radio buttons
    initializeEmptyFilterRadios();

    // Initialize boolean filter radio buttons with auto-submit
    initializeBooleanFilterRadios();
    
    const filterForm = document.querySelector('#filterPanel form');
    
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Reset DataTables state in localStorage to go back to page 1
            const currentPath = window.location.pathname;
            const datatablesKey = 'DataTables_crudTable_' + currentPath;
            
            try {
                const currentState = localStorage.getItem(datatablesKey);
                if (currentState) {
                    const state = JSON.parse(currentState);
                    // Reset start to 0 to go back to first page
                    state.start = 0;
                    localStorage.setItem(datatablesKey, JSON.stringify(state));
                    console.log('DataTables state reset to page 1');
                }
            } catch (error) {
                console.warn('Could not reset DataTables state:', error);
            }
            
            // Collect form data
            const formData = new FormData(filterForm);
            const params = new URLSearchParams();
            
            // Add persistent-table to maintain state
            params.append('persistent-table', '1');
            
            // First, collect all empty filter radio groups to handle them properly
            const emptyFilterRadios = filterForm.querySelectorAll('input[name$="_empty_filter"]:checked');
            const processedEmptyFilters = new Set();
            const allEmptyFilterFieldNames = new Set();
            
            // First pass: collect all field names that have empty filter radio groups
            filterForm.querySelectorAll('input[name$="_empty_filter"]').forEach(radio => {
                const fieldName = radio.name.replace('_empty_filter', '');
                allEmptyFilterFieldNames.add(fieldName);
            });
            
            // Second pass: process checked radios
            emptyFilterRadios.forEach(radio => {
                const fieldName = radio.name.replace('_empty_filter', '');
                processedEmptyFilters.add(fieldName);
                const value = radio.value;
                
                // Remove existing empty filter parameters first
                params.delete(fieldName + '_not_empty');
                params.delete(fieldName + '_empty');
                
                // Add appropriate parameter based on radio value
                // Remove conflicting filter first to avoid conflicts
                if (value === 'not_empty') {
                    params.delete(fieldName + '_empty'); // Remove conflicting empty filter
                    params.append(fieldName + '_not_empty', '1');
                    // Clear the input value when selecting "not_empty"
                    params.delete(fieldName);
                } else if (value === 'empty') {
                    params.delete(fieldName + '_not_empty'); // Remove conflicting not_empty filter
                    params.append(fieldName + '_empty', '1');
                    // Clear the input value when selecting "empty"
                    params.delete(fieldName);
                }
                // If value is empty (Tutti), no parameter is added - existing params already deleted
            });
            
            // Third pass: remove params for fields with radio groups where "Tutti" is selected (no checked radio with value)
            allEmptyFilterFieldNames.forEach(fieldName => {
                if (!processedEmptyFilters.has(fieldName)) {
                    // This field has a radio group but "Tutti" is selected (empty value)
                    // Ensure both parameters are removed
                    params.delete(fieldName + '_not_empty');
                    params.delete(fieldName + '_empty');
                }
            });
            
            // Add all other form fields that have values (excluding last_filter_section and empty_filter radios)
            for (let [key, value] of formData.entries()) {
                if (key === 'last_filter_section' || key.endsWith('_empty_filter')) {
                    continue;
                }
                
                // Skip _type fields if the corresponding date field is not active
                if (key.endsWith('_type')) {
                    const dateFieldName = key.replace('_type', '');
                    const hasDateValue = formData.get(dateFieldName) && formData.get(dateFieldName).trim() !== '';
                    const hasDateFrom = formData.get(dateFieldName + '_from') && formData.get(dateFieldName + '_from').trim() !== '';
                    const hasDateTo = formData.get(dateFieldName + '_to') && formData.get(dateFieldName + '_to').trim() !== '';
                    
                    // Only include _type if the date filter is actually active
                    if (!hasDateValue && !hasDateFrom && !hasDateTo) {
                        continue;
                    }
                }
                
                // Skip _from and _to fields if they are empty
                if (key.endsWith('_from') || key.endsWith('_to')) {
                    if (!value || value.trim() === '') {
                        continue;
                    }
                }
                
                // Only add fields with non-empty values
                if (value && value.trim() !== '') {
                    // Skip if this field has an active empty filter (not_empty or empty)
                    // If a field has not_empty or empty filter active, don't include its input value
                    const hasActiveEmptyFilter = params.has(key + '_not_empty') || params.has(key + '_empty');
                    if (!hasActiveEmptyFilter) {
                        params.append(key, value);
                    }
                }
            }
            
            // Clean up: remove any _type parameters that don't have corresponding active date filters
            const allParams = Array.from(params.keys());
            allParams.forEach(paramKey => {
                if (paramKey.endsWith('_type')) {
                    const dateFieldName = paramKey.replace('_type', '');
                    const dateValue = params.get(dateFieldName);
                    const dateFrom = params.get(dateFieldName + '_from');
                    const dateTo = params.get(dateFieldName + '_to');
                    
                    const hasDateValue = dateValue && dateValue.trim() !== '';
                    const hasDateFrom = dateFrom && dateFrom.trim() !== '';
                    const hasDateTo = dateTo && dateTo.trim() !== '';
                    
                    // Remove _type if no date filter is active
                    if (!hasDateValue && !hasDateFrom && !hasDateTo) {
                        params.delete(paramKey);
                    }
                }
            });
            
            // Always remove last_filter_section to prevent automatic accordion opening
            params.delete('last_filter_section');
            
            // Do NOT close filter panel when clicking "Cerca" - keep it open
            // Removed filter panel closing logic
            
            // Redirect to the same page with filter parameters
            const currentUrl = window.location.pathname;
            const newUrl = currentUrl + '?' + params.toString();
            
            console.log('Redirecting to:', newUrl);
            window.location.href = newUrl;
        });
    }
    
    // Handle reset button click to also reset DataTables state
    const resetBtn = document.getElementById('resetFiltersBtn');
    if (resetBtn) {
        resetBtn.addEventListener('click', function(e) {
            // Reset DataTables state in localStorage
            const currentPath = window.location.pathname;
            const datatablesKey = 'DataTables_crudTable_' + currentPath;
            
            try {
                const currentState = localStorage.getItem(datatablesKey);
                if (currentState) {
                    const state = JSON.parse(currentState);
                    // Reset start to 0 to go back to first page
                    state.start = 0;
                    localStorage.setItem(datatablesKey, JSON.stringify(state));
                    console.log('DataTables state reset to page 1 (reset button)');
                }
            } catch (error) {
                console.warn('Could not reset DataTables state:', error);
            }
        });
    }
    
    // Relation filters now use autocomplete (same as value filters) - no modal needed
});

// Initialize date filter toggles
function initializeDateFilterToggles() {
    // Find all date filter type radio buttons
    const dateTypeRadios = document.querySelectorAll('input[name$="_type"]');
    
    dateTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            const fieldName = this.name.replace('_type', '');
            const isExact = this.value === 'exact';
            
            // Toggle visibility of sections
            const exactSection = document.getElementById(fieldName + '_exact_section');
            const rangeSection = document.getElementById(fieldName + '_range_section');
            
            if (exactSection && rangeSection) {
                if (isExact) {
                    exactSection.style.display = 'block';
                    rangeSection.style.display = 'none';
                    
                    // Don't clear range inputs - preserve values
                } else {
                    exactSection.style.display = 'none';
                    rangeSection.style.display = 'block';
                    
                    // Don't clear exact input - preserve values
                }
            }
        });
    });
}

// Initialize value filters autocomplete functionality
function initializeValueFiltersAutocomplete() {
    console.log('initializeValueFiltersAutocomplete: Starting initialization');
    
    // Wait for jQuery to be available
    function setupValueAutocomplete() {
        if (typeof jQuery === 'undefined' && typeof $ === 'undefined') {
            console.warn('Value autocomplete: jQuery not loaded, retrying...');
            setTimeout(setupValueAutocomplete, 100);
            return;
        }
        
        const $ = typeof jQuery !== 'undefined' ? jQuery : window.$;
        console.log('Value autocomplete: jQuery available, setting up listeners, jQuery version:', $.fn.jquery);
        
        // Test if elements exist
        setTimeout(function() {
            const testInputs = document.querySelectorAll('.autocomplete-input:not(.relation-autocomplete-input)');
            console.log('Value autocomplete: Found', testInputs.length, 'value filter inputs');
            testInputs.forEach(function(input, idx) {
                console.log('Value autocomplete input', idx, ':', {
                    id: input.id,
                    column: input.dataset.column,
                    table: input.dataset.table,
                    url: input.dataset.autocompleteUrl
                });
            });
        }, 500);
        
        // Use event delegation
        $(document).on("input keyup", ".autocomplete-input:not(.relation-autocomplete-input)", function (e) {
            try {
                console.log('Value autocomplete: input/keyup event triggered on', this.id || this.className);
                const input = $(this);
                const columnName = input.data("column");
                const tableName = input.data("table");
                const query = (input.val() || '').trim();
                const suggestionBox = $("#autocomplete-" + columnName);

                console.log('Value autocomplete: input data', { columnName, tableName, query, queryLength: query.length });

                // Check if suggestion box exists
                if (suggestionBox.length === 0) {
                    console.warn('Value autocomplete: container not found for column:', columnName, 'Looking for: #autocomplete-' + columnName);
                    return;
                }

                if (!query || query.length < 4) {
                    console.log('Value autocomplete: query too short or empty, length:', query.length);
                    suggestionBox.hide();
                    return;
                }

            // Get autocomplete URL from data attribute or construct it
            let autocompleteUrl = input.data("autocomplete-url");
            console.log('Value autocomplete: URL from data attribute:', autocompleteUrl);
            
            if (!autocompleteUrl) {
                // Fallback: construct URL from current path
                const pathParts = window.location.pathname.split('/').filter(p => p);
                const adminIndex = pathParts.indexOf('admin');
                if (adminIndex !== -1) {
                    autocompleteUrl = '/' + pathParts.slice(0, adminIndex + 1).join('/') + '/autocomplete-values';
                } else {
                    autocompleteUrl = '/admin/autocomplete-values';
                }
                console.log('Value autocomplete: Constructed URL:', autocompleteUrl);
            }
            
            console.log('Value autocomplete: making request to', autocompleteUrl, 'with params:', { term: query, column: columnName, table: tableName });
            
            $.ajax({
                url: autocompleteUrl,
                data: { term: query, column: columnName, table: tableName },
                dataType: "json",
                success: function (data) {
                    console.log('Value autocomplete: received', data ? data.length : 0, 'results');
                    if (data && data.length > 0) {
                        let suggestions = "";
                        data.forEach(function (value) {
                            const escapedValue = String(value).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                            suggestions += "<div class='autocomplete-value-item' style='padding: 8px 12px !important; cursor: pointer !important; border-bottom: 1px solid #f0f0f0 !important; color: #000 !important; background-color: #fff !important; display: block !important; visibility: visible !important; opacity: 1 !important;'>" + escapedValue + "</div>";
                        });
                        suggestionBox.html(suggestions);
                        
                        // Force container to be visible with inline styles
                        suggestionBox.css({
                            'display': 'block',
                            'visibility': 'visible',
                            'opacity': '1',
                            'z-index': '9999',
                            'position': 'absolute',
                            'top': '100%',
                            'left': '0',
                            'width': '100%',
                            'max-height': '200px',
                            'overflow-y': 'auto',
                            'overflow-x': 'hidden',
                            'background-color': '#fff',
                            'border': '1px solid #ddd',
                            'border-radius': '0.25rem',
                            'box-shadow': '0 4px 10px rgba(0, 0, 0, 0.1)',
                            'margin-top': '2px',
                            'pointer-events': 'auto'
                        });
                        
                        // Ensure parent has position relative
                        const parent = suggestionBox.parent();
                        if (parent.length && parent.css('position') === 'static') {
                            parent.css('position', 'relative');
                        }
                        
                        console.log('Value autocomplete: showing suggestions box, items:', suggestionBox.find('div').length);
                        
                        // Verify container is visible
                        const rect = suggestionBox[0].getBoundingClientRect();
                        const computedStyle = window.getComputedStyle(suggestionBox[0]);
                        console.log('Value autocomplete: container visibility check:', {
                            display: computedStyle.display,
                            visibility: computedStyle.visibility,
                            opacity: computedStyle.opacity,
                            zIndex: computedStyle.zIndex,
                            position: computedStyle.position,
                            rect: rect,
                            isVisible: rect.width > 0 && rect.height > 0
                        });
                        
                        // Check parent (reuse existing parent variable)
                        if (parent.length) {
                            const parentStyle = window.getComputedStyle(parent[0]);
                            console.log('Value autocomplete: parent styles:', {
                                position: parentStyle.position,
                                overflow: parentStyle.overflow,
                                display: parentStyle.display
                            });
                            
                            // Fix parent if needed
                            if (parentStyle.overflow === 'hidden') {
                                parent.css('overflow', 'visible');
                                console.log('Value autocomplete: fixed parent overflow');
                            }
                        }
                        
                        // Force visibility one more time using cssText to override everything
                        const containerElement = suggestionBox[0];
                        containerElement.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; z-index: 99999 !important; position: absolute !important; top: 100% !important; left: 0 !important; width: 100% !important; max-height: 200px !important; overflow-y: auto !important; overflow-x: hidden !important; background-color: #fff !important; border: 1px solid #ddd !important; border-radius: 0.25rem !important; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1) !important; margin-top: 2px !important; pointer-events: auto !important;';
                        
                        // Check all ancestors for overflow hidden
                        let ancestor = containerElement.parentElement;
                        while (ancestor && ancestor !== document.body) {
                            const ancestorOverflow = window.getComputedStyle(ancestor).overflow;
                            if (ancestorOverflow === 'hidden') {
                                console.warn('Value autocomplete: ancestor has overflow hidden:', ancestor);
                                ancestor.style.overflow = 'visible';
                            }
                            ancestor = ancestor.parentElement;
                        }
                        
                        // Final check
                        const finalRect = containerElement.getBoundingClientRect();
                        const finalStyle = window.getComputedStyle(containerElement);
                        console.log('Value autocomplete: FINAL check after forcing styles:', {
                            display: finalStyle.display,
                            visibility: finalStyle.visibility,
                            opacity: finalStyle.opacity,
                            zIndex: finalStyle.zIndex,
                            rect: finalRect,
                            isVisible: finalRect.width > 0 && finalRect.height > 0,
                            innerHTML: containerElement.innerHTML.substring(0, 100)
                        });

                        // Click event for selecting a suggestion
                        suggestionBox.find("div").on("click", function () {
                            input.val($(this).text());
                            suggestionBox.hide();
                        });
                        
                        // Hover effect
                        suggestionBox.find("div").on("mouseenter", function() {
                            $(this).css({
                                'background-color': 'rgba(0, 123, 255, 0.1)',
                                'color': '#007bff'
                            });
                        }).on("mouseleave", function() {
                            $(this).css({
                                'background-color': '#fff',
                                'color': '#000'
                            });
                        });
                    } else {
                        suggestionBox.hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Value autocomplete error:', error, 'URL:', autocompleteUrl, 'Status:', status, 'Response:', xhr.responseText);
                    suggestionBox.hide();
                }
            });
            } catch (err) {
                console.error('Value autocomplete: Error in event handler:', err);
            }
        });
    }
    
    setupValueAutocomplete();
}

// Initialize relation autocomplete functionality
function initializeRelationAutocomplete() {
    const relationInputs = document.querySelectorAll('.relation-autocomplete-input');
    
    relationInputs.forEach(input => {
        let searchTimeout = null;
        const inputId = input.id;
        const hiddenInputId = inputId.replace('_display', '');
        const hiddenInput = document.getElementById(hiddenInputId);
        const suggestionsContainer = document.getElementById('autocomplete-' + inputId);
        
        if (!suggestionsContainer) {
            console.warn('Autocomplete container not found for input:', inputId, 'Expected ID: autocomplete-' + inputId);
            return;
        }
        
        if (!hiddenInput) {
            console.warn('Hidden input not found for input:', inputId, 'Expected ID: ' + hiddenInputId);
            return;
        }
        
        input.addEventListener('input', function() {
            console.log('Autocomplete relation: input event on', input.id, 'value:', this.value);
            clearTimeout(searchTimeout);
            const term = this.value.trim();
            
            // Clear hidden input when user starts typing (searching for new value)
            if (hiddenInput && term.length > 0) {
                hiddenInput.value = '';
            }
            
            // Clear suggestions if input is empty
            if (term.length === 0) {
                suggestionsContainer.innerHTML = '';
                suggestionsContainer.style.display = 'none';
                // Also clear hidden input when input is cleared
                if (hiddenInput) {
                    hiddenInput.value = '';
                }
                return;
            }
            
            // Debounce search
            console.log('Autocomplete relation: scheduling search for term:', term);
            searchTimeout = setTimeout(() => {
                console.log('Autocomplete relation: executing search for term:', term);
                fetchRelationSuggestions(input, term, suggestionsContainer);
            }, 300);
        });
        
        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                suggestionsContainer.style.display = 'none';
            }
        });
        
        // Handle keyboard navigation
        input.addEventListener('keydown', function(e) {
            const suggestions = suggestionsContainer.querySelectorAll('div');
            const active = suggestionsContainer.querySelector('div.active');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (active) {
                    active.classList.remove('active');
                    const next = active.nextElementSibling;
                    if (next) {
                        next.classList.add('active');
                    } else {
                        suggestions[0]?.classList.add('active');
                    }
                } else {
                    suggestions[0]?.classList.add('active');
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (active) {
                    active.classList.remove('active');
                    const prev = active.previousElementSibling;
                    if (prev) {
                        prev.classList.add('active');
                    } else {
                        suggestions[suggestions.length - 1]?.classList.add('active');
                    }
                } else {
                    suggestions[suggestions.length - 1]?.classList.add('active');
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (active) {
                    const value = active.dataset.value;
                    const label = active.dataset.label || active.textContent;
                    const inputId = input.id;
                    const hiddenInputId = inputId.replace('_display', '');
                    const hiddenInput = document.getElementById(hiddenInputId);
                    if (hiddenInput) {
                        hiddenInput.value = value;
                    }
                    input.value = label;
                    suggestionsContainer.style.display = 'none';
                }
            } else if (e.key === 'Escape') {
                suggestionsContainer.style.display = 'none';
            }
        });
    });
    
    function fetchRelationSuggestions(input, term, container) {
        console.log('fetchRelationSuggestions called with:', { inputId: input.id, term, containerExists: !!container });
        
        if (!container) {
            console.error('Autocomplete container not found for input:', input.id);
            return;
        }
        
        const isHasMany = input.classList.contains('hasmany-autocomplete-input');
        const baseUrl = '{{ backpack_url("autocomplete-relation-values") }}';
        console.log('Autocomplete relation: baseUrl:', baseUrl, 'isHasMany:', isHasMany);
        
        const params = new URLSearchParams({
            term: term
        });
        
        if (isHasMany) {
            params.append('is_hasmany', '1');
            params.append('relation', input.dataset.relation);
            params.append('model', input.dataset.model);
            params.append('searchable_keys', input.dataset.searchableKeys);
        } else {
            params.append('relation_column', input.dataset.relationColumn);
            params.append('table', input.dataset.table);
        }
        
        fetch(baseUrl + '?' + params.toString())
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error('HTTP error! status: ' + response.status + ', body: ' + text);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (!container) return; // Container might have been removed
                
                container.innerHTML = '';
                
                // Handle error response
                if (data && data.error) {
                    console.error('Autocomplete error:', data.error);
                    container.style.display = 'none';
                    return;
                }
                
                if (!data || !Array.isArray(data) || data.length === 0) {
                    container.style.display = 'none';
                    return;
                }
                
                console.log('Autocomplete: received', data.length, 'items for', input.id, 'data:', data);
                
                // Build HTML string directly - simpler and more reliable
                let html = '';
                data.forEach((item, index) => {
                    const displayText = (item.label || item.value || String(item.value)).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                    const itemValue = String(item.value).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                    html += `<div class="autocomplete-item" data-value="${itemValue}" data-label="${displayText}" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0; color: #000; background-color: #fff; display: block; position: relative; z-index: 10000;">${displayText}</div>`;
                });
                
                container.innerHTML = html;
                
                // Attach click handlers to all items
                container.querySelectorAll('.autocomplete-item').forEach(suggestion => {
                    suggestion.addEventListener('mouseenter', function() {
                        this.style.backgroundColor = 'rgba(0, 123, 255, 0.1)';
                        this.style.color = '#007bff';
                    });
                    suggestion.addEventListener('mouseleave', function() {
                        this.style.backgroundColor = '#fff';
                        this.style.color = '#000';
                    });
                    
                    suggestion.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const itemValue = this.dataset.value;
                        const itemLabel = this.dataset.label;
                        
                        // Store the ID in hidden input, display the label in visible input
                        const inputId = input.id;
                        const hiddenInputId = inputId.replace('_display', '');
                        const hiddenInput = document.getElementById(hiddenInputId);
                        if (hiddenInput) {
                            hiddenInput.value = itemValue;
                        }
                        input.value = itemLabel;
                        container.style.display = 'none';
                    });
                });
                
                console.log('Autocomplete: HTML set, items:', container.children.length);
                
                // Ensure container is visible and positioned correctly - FORCE EVERYTHING
                container.setAttribute('style', '');
                container.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; z-index: 99999 !important; position: absolute !important; top: 100% !important; left: 0 !important; width: 100% !important; max-height: 200px !important; overflow-y: auto !important; overflow-x: hidden !important; background-color: #fff !important; border: 1px solid #ddd !important; border-radius: 0.25rem !important; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1) !important; margin-top: 2px !important; pointer-events: auto !important; user-select: none !important;';
                
                // Ensure parent has position relative for absolute positioning
                const parent = container.parentElement;
                if (parent) {
                    const parentPosition = window.getComputedStyle(parent).position;
                    if (parentPosition === 'static') {
                        parent.style.position = 'relative';
                    }
                    // Ensure parent is not hidden and has no overflow hidden
                    const parentDisplay = window.getComputedStyle(parent).display;
                    if (parentDisplay === 'none') {
                        parent.style.display = 'block';
                    }
                    const parentOverflow = window.getComputedStyle(parent).overflow;
                    if (parentOverflow === 'hidden') {
                        parent.style.overflow = 'visible';
                    }
                }
                
                // Check all ancestors for overflow hidden
                let ancestor = container.parentElement;
                while (ancestor && ancestor !== document.body) {
                    const overflow = window.getComputedStyle(ancestor).overflow;
                    if (overflow === 'hidden') {
                        console.warn('Autocomplete: ancestor has overflow hidden:', ancestor);
                        ancestor.style.overflow = 'visible';
                    }
                    ancestor = ancestor.parentElement;
                }
                
                // Verify items are in DOM and visible
                const items = container.querySelectorAll('.autocomplete-item');
                const rect = container.getBoundingClientRect();
                const isVisible = rect.width > 0 && rect.height > 0 && 
                                 window.getComputedStyle(container).display !== 'none' &&
                                 window.getComputedStyle(container).visibility !== 'hidden';
                
                console.log('Autocomplete FINAL CHECK:', {
                    containerChildren: container.children.length,
                    itemsFound: items.length,
                    containerVisible: isVisible,
                    containerRect: rect,
                    containerStyles: {
                        display: window.getComputedStyle(container).display,
                        visibility: window.getComputedStyle(container).visibility,
                        opacity: window.getComputedStyle(container).opacity,
                        zIndex: window.getComputedStyle(container).zIndex,
                        position: window.getComputedStyle(container).position,
                        overflow: window.getComputedStyle(container).overflow
                    },
                    containerHTML: container.innerHTML.substring(0, 200)
                });
                
                if (items.length > 0) {
                    const firstItem = items[0];
                    const firstItemRect = firstItem.getBoundingClientRect();
                    console.log('Autocomplete FIRST ITEM:', {
                        text: firstItem.textContent,
                        rect: firstItemRect,
                        styles: {
                            display: window.getComputedStyle(firstItem).display,
                            visibility: window.getComputedStyle(firstItem).visibility,
                            opacity: window.getComputedStyle(firstItem).opacity,
                            color: window.getComputedStyle(firstItem).color,
                            backgroundColor: window.getComputedStyle(firstItem).backgroundColor
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error fetching relation suggestions:', error);
                if (container) {
                    container.style.display = 'none';
                }
            });
    }
}

// Helper function to clear localStorage for persistent table
function clearPersistentTableStorage() {
    if (typeof(Storage) !== "undefined") {
        // Clear all persistent table data for this path
        const keysToRemove = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.startsWith('persistent-table-' + window.location.pathname)) {
                keysToRemove.push(key);
            }
        }
        keysToRemove.forEach(key => localStorage.removeItem(key));
        
        // Also clear any filter-related data
        localStorage.removeItem('persistent-table-' + window.location.pathname);
        localStorage.removeItem('filters-' + window.location.pathname);
        
        console.log('localStorage cleared for persistent table');
    }
}

// Function to check if there are any real active filters (excluding last_filter_section)
function hasRealActiveFilters(params) {
    return Array.from(params.keys()).some(key => 
        key !== 'last_filter_section' && key !== 'page' && key !== 'persistent-table' && 
        params.get(key) !== null && params.get(key) !== ''
    );
}

// Function to check if there are any active filters in the form (not just URL params)
function hasActiveFiltersInForm(excludeField = null) {
    const form = document.querySelector('form[method="GET"]');
    if (!form) return false;
    
    const formData = new FormData(form);
    return Array.from(formData.keys()).some(key => 
        key !== 'last_filter_section' && key !== 'page' && key !== 'persistent-table' && 
        key !== excludeField && key !== excludeField + '_not_empty' && key !== excludeField + '_empty' &&
        formData.get(key) !== null && formData.get(key) !== ''
    );
}

// Initialize empty filter radio buttons
function initializeEmptyFilterRadios() {
    // Find all empty filter radio button groups
    const emptyFilterGroups = document.querySelectorAll('input[name$="_empty_filter"]');
    
    emptyFilterGroups.forEach(radio => {
        radio.addEventListener('change', function() {
            const fieldName = this.name.replace('_empty_filter', '');
            const value = this.value;
            
            // Update the form's hidden inputs or radio state to reflect the change
            // This ensures the form state matches the selection
            const form = this.closest('form');
            if (form) {
                // Update the form's radio button state
                const allRadios = form.querySelectorAll(`input[name="${this.name}"]`);
                allRadios.forEach(radio => {
                    radio.checked = (radio === this);
                });
                
                // Force update of visual state for all radio buttons in this group
                // This ensures Bootstrap's btn-check styling is applied correctly
                allRadios.forEach(r => {
                    const label = document.querySelector(`label[for="${r.id}"]`);
                    if (label) {
                        // Remove and re-add classes to force CSS update
                        if (r.checked) {
                            label.classList.add('active');
                        } else {
                            label.classList.remove('active');
                        }
                    }
                });
                
                // Remove existing hidden inputs for this field's filters
                const notEmptyInput = form.querySelector(`input[name="${fieldName}_not_empty"]`);
                const emptyInput = form.querySelector(`input[name="${fieldName}_empty"]`);
                if (notEmptyInput && notEmptyInput.type === 'hidden') {
                    notEmptyInput.remove();
                }
                if (emptyInput && emptyInput.type === 'hidden') {
                    emptyInput.remove();
                }
            }
            
            // Remove existing empty filter parameters from URL
            const url = new URL(window.location);
            const params = new URLSearchParams(url.search);
            params.delete(fieldName + '_not_empty');
            params.delete(fieldName + '_empty');
            
            // Add new parameter based on selection
            if (value === 'not_empty') {
                params.set(fieldName + '_not_empty', '1');
                // Clear the input value when selecting "not_empty"
                params.delete(fieldName);
            } else if (value === 'empty') {
                params.set(fieldName + '_empty', '1');
                // Clear the input value when selecting "empty"
                params.delete(fieldName);
            }
            // If value is empty (Tutti), both parameters are already deleted above
            
            // Special case: if selecting "Tutti" (empty value), always clear localStorage
            if (value === '') {
                console.log('DEBUG: Selecting "Tutti" for field:', fieldName, '- ALWAYS clearing localStorage');
                
                // Direct localStorage clearing
                if (typeof(Storage) !== "undefined") {
                    console.log('DEBUG: localStorage available, clearing...');
                    localStorage.clear();
                    console.log('DEBUG: localStorage cleared completely');
                } else {
                    console.log('DEBUG: localStorage not available');
                }
                
                // Also call the specific function
                clearPersistentTableStorage();
            }
            
            // Only add last_filter_section if not selecting "Tutti" (already handled above)
            if (value !== '') {
                // Check if there are any real active filters (excluding last_filter_section)
                const hasRealFilters = hasRealActiveFilters(params);
                
                // Always remove last_filter_section first, then add it back if needed
                params.delete('last_filter_section');
                
                // Always remove last_filter_section - accordions should not open automatically
                params.delete('last_filter_section');
                
                // Clear localStorage for persistent table BEFORE reload if no real filters
                if (!hasRealFilters) {
                    clearPersistentTableStorage();
                }
            } else {
                // For "Tutti" selection, just remove last_filter_section
                params.delete('last_filter_section');
            }
            
            // Do not reload automatically - user must click "Cerca" button
            // Removed automatic search: window.location.href = newUrl;
        });
    });
}

// Initialize boolean filter radio buttons with auto-submit
function initializeBooleanFilterRadios() {
    // Find all boolean filter radio button groups (not empty filter groups)
    const booleanFilterGroups = document.querySelectorAll('input[name]:not([name$="_empty_filter"]):not([name$="_type"])');
    
    booleanFilterGroups.forEach(radio => {
        // Only process radio buttons that are part of boolean filters
        if (radio.type === 'radio' && radio.name && !radio.name.includes('_empty_filter') && !radio.name.includes('_type')) {
            radio.addEventListener('change', function() {
                const fieldName = this.name;
                const value = this.value;
                
                // Build URL with new parameter
                const url = new URL(window.location);
                const params = new URLSearchParams(url.search);
                
                // Set the parameter (empty value means remove the parameter)
                if (value === '') {
                    params.delete(fieldName);
                } else {
                    params.set(fieldName, value);
                }
                
                // Special case: if selecting empty value, always clear localStorage
                if (value === '') {
                    console.log('DEBUG: Selecting empty value for boolean field:', fieldName, '- ALWAYS clearing localStorage');
                    
                    // Direct localStorage clearing
                    if (typeof(Storage) !== "undefined") {
                        console.log('DEBUG: localStorage available for boolean, clearing...');
                        localStorage.clear();
                        console.log('DEBUG: localStorage cleared completely for boolean');
                    } else {
                        console.log('DEBUG: localStorage not available for boolean');
                    }
                    
                    // Also call the specific function
                    clearPersistentTableStorage();
                }
                
                // Only add last_filter_section if not selecting empty value (already handled above)
                if (value !== '') {
                    // Check if there are any real active filters (excluding last_filter_section)
                    const hasRealFilters = hasRealActiveFilters(params);
                    
                    // Always remove last_filter_section - accordions should not open automatically
                    params.delete('last_filter_section');
                    
                    // Clear localStorage for persistent table BEFORE reload if no real filters
                    if (!hasRealFilters) {
                        clearPersistentTableStorage();
                    }
                } else {
                    // For empty value selection, just remove last_filter_section
                    params.delete('last_filter_section');
                }
                
                // Do not reload automatically - user must click "Cerca" button
                // Removed automatic search: window.location.href = newUrl;
            });
        }
    });
}

// Function to determine filter section and add to URL
function addLastFilterSection(params, fieldName) {
    // Removed - accordions should not open automatically
    // Always remove last_filter_section to prevent automatic opening
    params.delete('last_filter_section');
}

// Add global event listener for all filter changes
document.addEventListener('DOMContentLoaded', function() {
    // Add change listeners to all filter inputs
    const filterInputs = document.querySelectorAll('input[name], select[name]');
    
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            const fieldName = this.name;
            
            // Skip if this is already handled by specific functions
            if (fieldName.includes('_empty_filter') || fieldName.includes('_type')) {
                return;
            }
            
            // Add last filter section parameter to form
            const form = this.closest('form');
            if (form) {
                // Check if there are any real active filters (excluding last_filter_section)
                const formData = new FormData(form);
                const hasRealFilters = Array.from(formData.keys()).some(key => 
                    key !== 'last_filter_section' && key !== 'page' && key !== 'persistent-table' && 
                    formData.get(key) !== null && formData.get(key) !== ''
                );
                
                // Always remove existing last_filter_section first
                const existingInput = form.querySelector('input[name="last_filter_section"]');
                if (existingInput) {
                    existingInput.remove();
                }
                
                if (hasRealFilters) {
                    // Add new hidden input with the correct section only if there are real filters
                    const section = getFilterSection(fieldName);
                    console.log('Filter section determined:', fieldName, '->', section);
                    
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'last_filter_section';
                    hiddenInput.value = section;
                    form.appendChild(hiddenInput);
                } else {
                    // Clear localStorage for persistent table BEFORE form submit
                    clearPersistentTableStorage();
                }
            }
        });
    });
});

// Function to determine filter section based on field name and context
function getFilterSection(fieldName) {
    console.log('DEBUG: getFilterSection called for field:', fieldName);
    
    // First, try to find the actual container to determine the section
    const input = document.querySelector(`[name="${fieldName}"]`);
    if (input) {
        console.log('DEBUG: Input found, checking container...');
        const fieldContainer = input.closest('.filter-field-container');
        if (fieldContainer) {
            console.log('DEBUG: Field container found, checking accordion...');
            const accordionItem = fieldContainer.closest('.accordion-item');
            if (accordionItem) {
                console.log('DEBUG: Accordion item found, checking button...');
                const button = accordionItem.querySelector('.accordion-button');
                if (button) {
                    const text = button.textContent.toLowerCase();
                    console.log('DEBUG: Button text:', text);
                    // Check "advanced" FIRST before "date" to handle advanced date filters correctly
                    if (text.includes('avanzati') || text.includes('advanced')) {
                        console.log('DEBUG: Detected as advanced from button text');
                        return 'advanced';
                    } else if (text.includes('relazioni') || text.includes('relations')) {
                        console.log('DEBUG: Detected as relation from button text');
                        return 'relation';
                    } else if (text.includes('data') || text.includes('date')) {
                        console.log('DEBUG: Detected as date from button text');
                        return 'date';
                    } else if (text.includes('stato') || text.includes('boolean')) {
                        console.log('DEBUG: Detected as boolean from button text');
                        return 'boolean';
                    } else if (text.includes('select') || text.includes('selezione')) {
                        console.log('DEBUG: Detected as select from button text');
                        return 'select';
                    } else if (text.includes('valore') || text.includes('text') || text.includes('filtri')) {
                        console.log('DEBUG: Detected as text from button text');
                        return 'text';
                    }
                }
            }
        }
    } else {
        console.log('DEBUG: Input not found, using fallback logic...');
    }
    
    // Check if it's an advanced field FIRST (before other checks)
    const advancedFields = ['token_expire', 'email_verified_at', 'role_id', 'created_at', 'updated_at', 'backpack_role_id'];
    if (advancedFields.some(field => fieldName.includes(field))) {
        console.log('DEBUG: Field detected as advanced:', fieldName);
        return 'advanced';
    }
    
    // Fallback: Check if it's a relation field (ends with _id)
    if (fieldName.endsWith('_id')) {
        return 'relation';
    }
    
    // Check if it's a date field (look for date-related field names, excluding advanced dates)
    const dateFields = ['date', 'time', 'timestamp'];
    if (dateFields.some(field => fieldName.includes(field))) {
        return 'date';
    }
    
    // Check if it's a boolean field (look for boolean-related field names)
    const booleanFields = ['active', 'enabled', 'visible', 'status', 'is_'];
    if (booleanFields.some(field => fieldName.includes(field))) {
        return 'boolean';
    }
    
    // Check if it's a select field (look for enum-related field names)
    const selectFields = ['type', 'category', 'status', 'role', 'level'];
    if (selectFields.some(field => fieldName.includes(field))) {
        return 'select';
    }
    
    // Default to text
    return 'text';
}

// Removed: HasMany Relation Modal - replaced with autocomplete

// CSS animations removed for cleaner UX
</script> 