@php
// List of routes where import is disabled
$disableImportFor = ['page', 'contatto'];

// Extract the current route name from the path
$currentRoute = str_replace(config('backpack.base.route_prefix', 'admin').'/', '', $crud->route);

// Check if import should be disabled for this route
$importDisabled = in_array($currentRoute, $disableImportFor);

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

<div class="csv-actions-container">
    <button type="button" id="csvActionsButton" class="btn btn-primary" onclick="toggleCsvPopup()">
        <i class="la la-file-csv"></i> CSV
    </button>

    <!-- Custom popup instead of modal -->
    <div id="csvActionsPopup" class="csv-popup">
        <div class="csv-popup-content">
            <div class="csv-popup-header">
                <h5>{{ trans('backpack::crud.csv_actions') }}</h5>
                <button type="button" class="csv-popup-close" onclick="toggleCsvPopup()">
                    <span>&times;</span>
                </button>
            </div>
            <div class="csv-popup-body">
                <div class="csv-buttons">
                    @if($hasActiveFilters)
                    <span
                        data-toggle="tooltip"
                        data-bs-toggle="tooltip"
                        data-placement="top"
                        data-bs-placement="top"
                        title="{{ trans('backpack::crud.warning_export_with_filters') }}">
                        <a href="{{ url($crud->route.'/export-csv' . $queryString) }}" 
                           class="btn btn-primary csv-export-btn"
                           data-loading-text="{{ trans('backpack::crud.csv_export_in_progress') }}"
                           data-timeout-text="{{ trans('backpack::crud.csv_export_timeout') }}"
                           onclick="showExportLoading(event, this)">
                            <i class="la la-file-export me-1 csv-export-icon"></i>
                            <span class="csv-export-text">{{ trans('backpack::crud.csv_export') }}</span>
                        </a>
                    </span>
                    @else
                    <a href="{{ url($crud->route.'/export-csv' . $queryString) }}" 
                       class="btn btn-primary csv-export-btn"
                       data-loading-text="{{ trans('backpack::crud.csv_export_in_progress') }}"
                       data-timeout-text="{{ trans('backpack::crud.csv_export_timeout') }}"
                       onclick="showExportLoading(event, this)">
                        <i class="la la-file-export me-1 csv-export-icon"></i>
                        <span class="csv-export-text">{{ trans('backpack::crud.csv_export') }}</span>
                    </a>
                    @endif

                    @if($importDisabled)
                    <span
                        data-toggle="tooltip"
                        data-bs-toggle="tooltip"
                        data-placement="top"
                        data-bs-placement="top"
                        title="{{ trans('backpack::crud.csv_import_disabled', ['section' => ucfirst($currentRoute)]) }}">
                        <button class="btn btn-primary disabled-import-btn"
                            disabled
                            title="{{ trans('backpack::crud.csv_import_disabled', ['section' => ucfirst($currentRoute)]) }}">
                            <i class="la la-file-upload me-1"></i> {{ trans('backpack::crud.csv_import') }}
                        </button>
                    </span>
                    @else
                    <a href="{{ url($crud->route.'/import-csv') }}" class="btn btn-primary">
                        <i class="la la-file-upload me-1"></i> {{ trans('backpack::crud.csv_import') }}
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function showExportLoading(event, button) {
        // Disable button to prevent double clicks
        button.classList.add('csv-export-loading');
        button.style.pointerEvents = 'none';
        
        // Change icon to spinner
        var icon = button.querySelector('.csv-export-icon');
        var originalIconClass = icon.className;
        icon.className = 'la la-spinner la-spin me-1 csv-export-icon';
        
        // Change text
        var textElement = button.querySelector('.csv-export-text');
        var originalText = textElement.textContent;
        var loadingText = button.getAttribute('data-loading-text') || 'Esportazione in corso...';
        textElement.textContent = loadingText;

        // Generate a unique token for this download
        var downloadToken = 'download_' + new Date().getTime();
        
        // Add token to URL
        var url = button.href;
        var separator = url.includes('?') ? '&' : '?';
        button.href = url + separator + 'downloadToken=' + downloadToken;

        // Start checking for download completion via cookie
        var attempts = 0;
        var maxAttempts = 600; // 600 attempts * 500ms = 5 minutes max
        
        var checkInterval = setInterval(function() {
            attempts++;
            
            // Check if download cookie exists
            if (document.cookie.indexOf('downloadToken=' + downloadToken) !== -1) {
                // Download has started, restore button
                button.classList.remove('csv-export-loading');
                button.style.pointerEvents = '';
                icon.className = originalIconClass;
                textElement.textContent = originalText;
                clearInterval(checkInterval);
                
                // Clean up the cookie
                document.cookie = 'downloadToken=' + downloadToken + '; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            } else if (attempts >= maxAttempts) {
                // Timeout after 5 minutes
                button.classList.remove('csv-export-loading');
                button.style.pointerEvents = '';
                icon.className = originalIconClass;
                textElement.textContent = originalText;
                clearInterval(checkInterval);
                
                var timeoutMessage = button.getAttribute('data-timeout-text') || 'L\'export sta richiedendo più tempo del previsto. Controlla i download o riprova più tardi.';
                alert(timeoutMessage);
            }
        }, 500);
    }

    function toggleCsvPopup() {
        var popup = document.getElementById('csvActionsPopup');

        if (popup.classList.contains('visible')) {
            // Start closing animation
            popup.classList.add('animate-out');
            popup.classList.remove('animate-in');

            // Remove 'visible' class after animation completes
            setTimeout(function() {
                popup.classList.remove('visible');
                popup.classList.remove('animate-out');
                document.removeEventListener('click', closePopupOnClickOutside);
            }, 200); // Slightly shorter time to avoid lag
        } else {
            // Show popup and start opening animation
            popup.classList.add('visible');
            popup.classList.add('animate-in');
            popup.classList.remove('animate-out');

            // Add event listener to close when clicking outside
            document.addEventListener('click', closePopupOnClickOutside);
        }
    }

    function closePopupOnClickOutside(event) {
        var popup = document.getElementById('csvActionsPopup');
        var button = document.getElementById('csvActionsButton');

        // If click is not on popup or button, close the popup
        if (!popup.contains(event.target) && !button.contains(event.target)) {
            // Start closing animation
            popup.classList.add('animate-out');
            popup.classList.remove('animate-in');

            // Remove 'visible' class after animation completes
            setTimeout(function() {
                popup.classList.remove('visible');
                popup.classList.remove('animate-out');
                document.removeEventListener('click', closePopupOnClickOutside);
            }, 200); // Slightly shorter time to avoid lag
        }
    }

    // Initialize tooltips for Bootstrap 4 and 5
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize for Bootstrap 4
        if (typeof $ !== 'undefined' && typeof $.fn !== 'undefined' && typeof $.fn.tooltip === 'function') {
            $('[data-toggle="tooltip"]').tooltip();
        }

        // Initialize for Bootstrap 5
        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Tooltip !== 'undefined') {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    });

    // Fallback for tooltips with native title attribute
    document.addEventListener('DOMContentLoaded', function() {
        var disabledButtons = document.querySelectorAll('.disabled-import-btn');
        disabledButtons.forEach(function(button) {
            button.addEventListener('mouseenter', function() {
                if (!button.getAttribute('data-tooltip-initialized')) {
                    // If Bootstrap tooltips are not initialized, use the native title
                    button.style.position = 'relative';
                }
            });
        });
    });
</script>