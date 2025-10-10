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

      <!-- Date Filters Accordion -->
      @if($dateColumns->count() > 0)
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button {{ $openDateFilters ? '' : 'collapsed' }}" type="button"
            data-bs-toggle="collapse" data-bs-target="#dateFiltersCollapse"
            aria-expanded="{{ $openDateFilters ? 'true' : 'false' }}" aria-controls="dateFiltersCollapse">
            <i class="la la-calendar"></i> {{ trans('backpack::filters.date_filters') }}
            @if($dateFilterCount > 0)
            <span class="badge bg-primary rounded-pill ms-2">{{ $dateFilterCount }}</span>
            @endif
          </button>
        </h2>
        <div id="dateFiltersCollapse" class="accordion-collapse collapse {{ $openDateFilters ? 'show' : '' }}" data-bs-parent="#filtersAccordion">
          <div class="accordion-body">
            <div class="row g-2 mt-1">
              @foreach ($dateColumns as $column)
              @php
              $label = isset($column['label']) ? $column['label'] : ucfirst($column['name']);
              $isDateTime = ($column['type'] ?? '') == 'datetime';
              @endphp
              <div class="col-lg-3 col-md-4 col-sm-6">
                <label for="{{ $column['name'] }}" class="form-label small text-muted mb-1 filter-label">{{ $label }}</label>
                <input autocomplete="off" 
                       type="{{ $isDateTime ? 'datetime-local' : 'date' }}" 
                       name="{{ $column['name'] }}"
                       value="{{ request()->get($column['name']) }}"
                       class="form-control"
                       id="{{ $column['name'] }}">
              </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>
      @endif

      <!-- Select Filters Accordion (Status, Enums, etc.) -->
      @if($selectColumns->count() > 0)
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button {{ $openSelectFilters ? '' : 'collapsed' }}" type="button"
            data-bs-toggle="collapse" data-bs-target="#selectFiltersCollapse"
            aria-expanded="{{ $openSelectFilters ? 'true' : 'false' }}" aria-controls="selectFiltersCollapse">
            <i class="la la-list"></i> {{ trans('backpack::filters.select_filters') }}
            @if($selectFilterCount > 0)
            <span class="badge bg-primary rounded-pill ms-2">{{ $selectFilterCount }}</span>
            @endif
          </button>
        </h2>
        <div id="selectFiltersCollapse" class="accordion-collapse collapse {{ $openSelectFilters ? 'show' : '' }}" data-bs-parent="#filtersAccordion">
          <div class="accordion-body">
            <div class="row g-2 mt-1">
              @foreach ($selectColumns as $column)
              @php
              $label = isset($column['label']) ? $column['label'] : ucfirst($column['name']);
              $options = FilterHelper::getSelectOptions($column, $tableName);
              $currentValue = request()->get($column['name']);
              @endphp

              <div class="col-lg-3 col-md-4 col-sm-6">
                <label for="{{ $column['name'] }}" class="form-label small text-muted mb-1 filter-label">{{ $label }}</label>
                <select name="{{ $column['name'] }}" class="form-select" id="{{ $column['name'] }}">
                  <option value="">{{ trans('backpack::filters.all') }}</option>
                  @foreach ($options as $value => $optionLabel)
                  <option value="{{ $value }}" {{ $currentValue == $value ? 'selected' : '' }}>
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
                  @php
                  $options = FilterHelper::getRelationOptions($column, $crud);
                  $useModal = FilterHelper::shouldUseModal($column, $crud);
                  $currentValue = request()->get($column['name']);
                  $selectedLabel = $currentValue && isset($options[$currentValue]) ? $options[$currentValue] : '';
                  
                  // Calculate stats for counter (count options with relations)
                  $optionsWithRelations = collect($options)->filter(function($label) {
                      return !str_ends_with($label, '[0]') && str_contains($label, '[');
                  })->count();
                  $totalOptions = count($options);
                  @endphp
                  
                  @if($useModal)
                    <!-- Modal trigger button for large datasets -->
                    <button type="button" 
                            class="btn btn-outline-secondary w-100 relation-modal-trigger text-start {{ $currentValue ? 'has-selection' : '' }}" 
                            data-field="{{ $column['name'] }}"
                            data-label="{{ $label }}"
                            data-options="{{ base64_encode(json_encode($options)) }}"
                            data-options-with-relations="{{ $optionsWithRelations }}"
                            data-total-options="{{ $totalOptions }}"
                            data-bs-toggle="modal" 
                            data-bs-target="#relationModal"
                            style="position: relative;">
                      <span class="selected-text">
                        {{ $selectedLabel ? $selectedLabel : trans('backpack::filters.all') }}
                      </span>
                      <i class="la la-search position-absolute" style="right: 10px; top: 50%; transform: translateY(-50%);"></i>
                    </button>
                    <input type="hidden" name="{{ $column['name'] }}" value="{{ $currentValue }}" class="relation-hidden-input">
                  @else
                    <!-- Regular select for smaller datasets -->
                    <select name="{{ $column['name'] }}" 
                            class="form-select relation-filter-select" 
                            id="{{ $column['name'] }}"
                            data-options-with-relations="{{ $optionsWithRelations }}"
                            data-total-options="{{ $totalOptions }}">
                      <option value="">{{ trans('backpack::filters.all') }}</option>
                      @foreach ($options as $value => $optionLabel)
                      <option value="{{ $value }}" {{ $currentValue == $value ? 'selected' : '' }} data-has-relations="{{ !str_ends_with($optionLabel, '[0]') && str_contains($optionLabel, '[') ? 'true' : 'false' }}">
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
      <button type="submit" class="btn btn-primary" id="filterSubmitBtn">
        <i class="la la-search"></i> {{ trans('backpack::crud.search') }}
      </button>
      <a href="{{ url()->current() }}?persistent-table=1" class="btn btn-outline-secondary ms-2" id="resetFiltersBtn">
        <i class="la la-times-circle"></i> {{ trans('backpack::crud.reset') }}
      </a>
    </div>
  </form>
</div>

<!-- Relation Search Modal -->
<div class="modal fade" id="relationModal" tabindex="-1" aria-labelledby="relationModalLabel" aria-hidden="true" data-bs-backdrop="false" data-bs-keyboard="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="relationModalLabel">Seleziona <span id="modalFieldLabel"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <input type="text" 
                 class="form-control form-control-lg" 
                 id="relationSearchInput" 
                 placeholder="Cerca...">
        </div>
        <div class="mb-3">
          <select id="modalRelationSelect" class="form-select" size="15" style="height: 400px;">
            <!-- Options will be populated by JavaScript -->
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="button" class="btn btn-outline-danger" id="clearSelectionBtn">Cancella selezione</button>
        <button type="button" class="btn btn-primary" id="confirmSelectionBtn">Conferma</button>
      </div>
    </div>
  </div>
</div>

<script>
// Flash animation removed for cleaner UX

document.addEventListener('DOMContentLoaded', function() {
    // Initialize relation modal functionality
    initializeRelationModal();
    
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
            
            // Add all form fields that have values
            for (let [key, value] of formData.entries()) {
                if (value && value.trim() !== '') {
                    params.append(key, value);
                }
            }
            
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
    
     // Enhanced functionality for relation filter dropdowns and modal buttons
     const relationSelects = document.querySelectorAll('.relation-filter-select');
     const relationModalButtons = document.querySelectorAll('.relation-modal-trigger');
     
     // Check if there are ANY filters active (including relation filters)
     const hasAnyActiveFilters = {{ isset($hasAnyActiveFilters) && $hasAnyActiveFilters ? 'true' : 'false' }};
     
     // Handle regular selects
     relationSelects.forEach(select => {
         addStatsToContainer(select.parentNode, select, null);
         addSelectEventListeners(select);
     });
     
     // Handle modal buttons
     relationModalButtons.forEach(button => {
         addStatsToContainer(button.parentNode, null, button);
     });
     
     function addStatsToContainer(container, select, modalButton) {
         const indicator = document.createElement('div');
         indicator.className = 'relation-stats mt-2';
         indicator.style.cssText = 'font-size: 11px; color: #6c757d; text-align: center; margin-top: 8px;';
         
         let optionsWithRelations = 0;
         let totalOptions = 0;
         
         if (select) {
             // Read pre-calculated counts from data attributes
             optionsWithRelations = parseInt(select.dataset.optionsWithRelations) || 0;
             totalOptions = parseInt(select.dataset.totalOptions) || 0;
         } else if (modalButton) {
             // Read pre-calculated counts from data attributes
             optionsWithRelations = parseInt(modalButton.dataset.optionsWithRelations) || 0;
             totalOptions = parseInt(modalButton.dataset.totalOptions) || 0;
         }
         
         // If there are ANY filters active (including relation filters), show only "Collegati"
         // Show counter ALWAYS, even if count is 0
         if (hasAnyActiveFilters) {
             indicator.innerHTML = `
                 <span style="background: #e3f2fd; padding: 3px 8px; border-radius: 10px; font-weight: 600; color: #1976d2; border: 1px solid #bbdefb;">
                     ● Collegati: ${optionsWithRelations}
                 </span>
             `;
         } else {
             indicator.innerHTML = `
                 <span style="background: #e3f2fd; padding: 3px 8px; border-radius: 10px; margin-right: 6px; font-weight: 600; color: #1976d2; border: 1px solid #bbdefb;">
                     ● Collegati: ${optionsWithRelations}
                 </span>
                 <span style="background: #f8f9fa; padding: 3px 8px; border-radius: 10px; font-weight: 600; color: #757575; border: 1px solid #e0e0e0;">
                     ○ Non collegati: ${totalOptions - optionsWithRelations}
                 </span>
             `;
         }
         
         container.appendChild(indicator);
     }
     
     function addSelectEventListeners(select) {
         select.addEventListener('change', function() {
             // Selection change handled without animation for cleaner UX
         });
     }
     
    
    // Legacy badge support removed for cleaner code
});

// Initialize relation modal functionality (simplified)
function initializeRelationModal() {
    const modal = document.getElementById('relationModal');
    const modalLabel = document.getElementById('modalFieldLabel');
    const searchInput = document.getElementById('relationSearchInput');
    const modalSelect = document.getElementById('modalRelationSelect');
    const clearBtn = document.getElementById('clearSelectionBtn');
    const confirmBtn = document.getElementById('confirmSelectionBtn');
    
    let currentField = null;
    let currentTriggerButton = null;
    let currentHiddenInput = null;
    let allOptions = [];
    
    // Handle modal trigger clicks
    document.addEventListener('click', function(e) {
        if (e.target.closest('.relation-modal-trigger')) {
            const button = e.target.closest('.relation-modal-trigger');
            currentField = button.dataset.field;
            currentTriggerButton = button;
            currentHiddenInput = button.parentNode.querySelector('.relation-hidden-input');
            
            modalLabel.textContent = button.dataset.label;
            loadOptionsForModal();
        }
    });
    
    // Load options for the modal
    function loadOptionsForModal() {
        try {
            // Get options from data attribute
            const optionsData = currentTriggerButton.dataset.options;
            const decodedOptions = JSON.parse(atob(optionsData));
            
            // Convert to the format we need
            allOptions = Object.entries(decodedOptions).map(([value, label]) => ({
                value: value,
                label: label,
                hasRelations: !label.endsWith('[0]') && label.includes('[')
            }));
            
            // Populate the select directly
            populateModalSelect(allOptions);
        } catch (error) {
            console.error('Error loading options:', error);
            modalSelect.innerHTML = '<option value="">Errore nel caricamento</option>';
        }
    }
    
    // Populate modal select with options
    function populateModalSelect(options) {
        modalSelect.innerHTML = '';
        
        // Add "All" option
        const allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = '{{ trans("backpack::filters.all") }}';
        modalSelect.appendChild(allOption);
        
        // Add regular options
        options.forEach(option => {
            const optionElement = document.createElement('option');
            optionElement.value = option.value;
            optionElement.textContent = option.label;
            optionElement.dataset.hasRelations = option.hasRelations ? 'true' : 'false';
            
            // Mark as selected if it matches current value
            if (currentHiddenInput && currentHiddenInput.value === option.value) {
                optionElement.selected = true;
            }
            
            modalSelect.appendChild(optionElement);
        });
    }
    
    // Handle search input
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        filterSelectOptions(query);
    });
    
    function filterSelectOptions(query) {
        const options = modalSelect.querySelectorAll('option');
        options.forEach(option => {
            const text = option.textContent.toLowerCase();
            if (text.includes(query) || option.value === '') {
                option.style.display = 'block';
            } else {
                option.style.display = 'none';
            }
        });
    }
    
    // Handle confirm selection
    confirmBtn.addEventListener('click', function(e) {
        e.preventDefault();
        console.log('Confirm button clicked');
        const selectedOption = modalSelect.options[modalSelect.selectedIndex];
        if (selectedOption) {
            console.log('Confirming selection:', selectedOption.textContent);
            selectOption(selectedOption.value, selectedOption.textContent);
        } else {
            console.log('No option selected');
        }
    });
    
    // Handle double-click on select for quick selection
    let lastClickTime = 0;
    modalSelect.addEventListener('click', function(e) {
        const currentTime = new Date().getTime();
        const timeDiff = currentTime - lastClickTime;
        
        if (timeDiff < 300) { // Double click detected (300ms threshold)
            e.preventDefault();
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption) {
                console.log('Double-click detected on:', selectedOption.textContent);
                selectOption(selectedOption.value, selectedOption.textContent);
            }
        }
        lastClickTime = currentTime;
    });
    
    // Handle option selection
    function selectOption(value, label) {
        // Update hidden input
        if (currentHiddenInput) {
            currentHiddenInput.value = value;
        }
        
        // Update button text
        if (currentTriggerButton) {
            const selectedText = currentTriggerButton.querySelector('.selected-text');
            selectedText.textContent = label;
            
            // Update button styling
            if (value) {
                currentTriggerButton.classList.add('has-selection');
                
                // Flash animation removed for cleaner UX
            } else {
                currentTriggerButton.classList.remove('has-selection');
            }
        }
        
        // Close modal
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) {
            bsModal.hide();
        }
    }
    
    // Handle clear selection
    clearBtn.addEventListener('click', function() {
        selectOption('', '{{ trans("backpack::filters.all") }}');
    });
    
    // Reset search when modal opens
    modal.addEventListener('shown.bs.modal', function() {
        searchInput.value = '';
        searchInput.focus();
        
        // Reset select filter
        const options = modalSelect.querySelectorAll('option');
        options.forEach(option => {
            option.style.display = 'block';
        });
    });
}


// CSS animations removed for cleaner UX
</script> 