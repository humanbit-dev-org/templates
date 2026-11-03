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
    <div class="csv-actions-group">
        {{-- CSV Button + Popup --}}
        <div class="csv-actions-btn-wrapper">
            <button type="button" class="btn btn-primary csv-actions-btn" onclick="togglePopup('csvActionsPopup', 'csvActionsButton')">
                <i class="la la-file-csv"></i> CSV
            </button>
            <div id="csvActionsPopup" class="csv-popup">
        <div class="csv-popup-content">
            <div class="csv-popup-header">
                <h5><i class="la la-file-csv me-1"></i> CSV</h5>
                <button type="button" class="csv-popup-close" onclick="togglePopup('csvActionsPopup', 'csvActionsButton')">
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
                           class="btn btn-outline-success csv-export-btn"
                           data-loading-text="{{ trans('backpack::crud.csv_export_in_progress') }}"
                           data-timeout-text="{{ trans('backpack::crud.csv_export_timeout') }}"
                           onclick="showExportLoading(event, this)">
                            <i class="la la-file-export me-1 csv-export-icon"></i>
                            <span class="csv-export-text">{{ trans('backpack::crud.csv_export') }}</span>
                        </a>
                    </span>
                    @else
                    <a href="{{ url($crud->route.'/export-csv' . $queryString) }}"
                       class="btn btn-outline-success csv-export-btn"
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
                        <button class="btn btn-outline-success disabled-import-btn"
                            disabled
                            title="{{ trans('backpack::crud.csv_import_disabled', ['section' => ucfirst($currentRoute)]) }}">
                            <i class="la la-file-upload me-1"></i> {{ trans('backpack::crud.csv_import') }}
                        </button>
                    </span>
                    @else
                    <a href="{{ url($crud->route.'/import-csv') }}" class="btn btn-outline-success">
                        <i class="la la-file-upload me-1"></i> {{ trans('backpack::crud.csv_import') }}
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
        </div>

        {{-- Excel Button + Popup --}}
        <div class="csv-actions-btn-wrapper">
            <button type="button" class="btn btn-primary xlsx-actions-btn" onclick="togglePopup('xlsxActionsPopup', 'xlsxActionsButton')">
                <i class="la la-file-excel"></i> Excel
            </button>
            <div id="xlsxActionsPopup" class="csv-popup">
        <div class="csv-popup-content">
            <div class="csv-popup-header">
                <h5><i class="la la-file-excel me-1"></i> Excel</h5>
                <button type="button" class="csv-popup-close" onclick="togglePopup('xlsxActionsPopup', 'xlsxActionsButton')">
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
                        <a href="{{ url($crud->route.'/export-xlsx' . $queryString) }}"
                           class="btn btn-outline-success xlsx-export-btn"
                           data-loading-text="{{ trans('backpack::crud.csv_export_in_progress') }}"
                           data-timeout-text="{{ trans('backpack::crud.csv_export_timeout') }}"
                           onclick="showExportLoading(event, this)">
                            <i class="la la-file-export me-1 xlsx-export-icon"></i>
                            <span class="xlsx-export-text">{{ trans('backpack::crud.xlsx_export') }}</span>
                        </a>
                    </span>
                    @else
                    <a href="{{ url($crud->route.'/export-xlsx' . $queryString) }}"
                       class="btn btn-outline-success xlsx-export-btn"
                       data-loading-text="{{ trans('backpack::crud.csv_export_in_progress') }}"
                       data-timeout-text="{{ trans('backpack::crud.csv_export_timeout') }}"
                       onclick="showExportLoading(event, this)">
                        <i class="la la-file-export me-1 xlsx-export-icon"></i>
                        <span class="xlsx-export-text">{{ trans('backpack::crud.xlsx_export') }}</span>
                    </a>
                    @endif

                    @if($importDisabled)
                    <span
                        data-toggle="tooltip"
                        data-bs-toggle="tooltip"
                        data-placement="top"
                        data-bs-placement="top"
                        title="{{ trans('backpack::crud.csv_import_disabled', ['section' => ucfirst($currentRoute)]) }}">
                        <button class="btn btn-outline-success disabled-import-btn"
                            disabled
                            title="{{ trans('backpack::crud.csv_import_disabled', ['section' => ucfirst($currentRoute)]) }}">
                            <i class="la la-file-upload me-1"></i> {{ trans('backpack::crud.xlsx_import') }}
                        </button>
                    </span>
                    @else
                    <a href="{{ url($crud->route.'/import-xlsx') }}" class="btn btn-outline-success">
                        <i class="la la-file-upload me-1"></i> {{ trans('backpack::crud.xlsx_import') }}
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
        </div>
    </div>
</div>

<script>
    function showExportLoading(event, button) {
        button.classList.add('csv-export-loading');
        button.style.pointerEvents = 'none';

        var icon = button.querySelector('[class*="export-icon"]');
        var originalIconClass = icon.className;
        var iconClass = originalIconClass.split(' ').find(function(c) { return c.includes('export-icon'); }) || 'csv-export-icon';
        icon.className = 'la la-spinner la-spin me-1 ' + iconClass;

        var textElement = button.querySelector('[class*="export-text"]');
        var originalText = textElement.textContent;
        var loadingText = button.getAttribute('data-loading-text') || 'Esportazione in corso...';
        textElement.textContent = loadingText;

        var downloadToken = 'download_' + new Date().getTime();
        var url = button.href;
        var separator = url.includes('?') ? '&' : '?';
        button.href = url + separator + 'downloadToken=' + downloadToken;

        var attempts = 0;
        var maxAttempts = 600;

        var checkInterval = setInterval(function() {
            attempts++;
            if (document.cookie.indexOf('downloadToken=' + downloadToken) !== -1) {
                button.classList.remove('csv-export-loading');
                button.style.pointerEvents = '';
                icon.className = originalIconClass;
                textElement.textContent = originalText;
                clearInterval(checkInterval);
                document.cookie = 'downloadToken=' + downloadToken + '; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            } else if (attempts >= maxAttempts) {
                button.classList.remove('csv-export-loading');
                button.style.pointerEvents = '';
                icon.className = originalIconClass;
                textElement.textContent = originalText;
                clearInterval(checkInterval);
                alert(button.getAttribute('data-timeout-text') || 'Export timeout');
            }
        }, 500);
    }

    function togglePopup(popupId, buttonId) {
        var popup = document.getElementById(popupId);

        // Close any other open popup
        document.querySelectorAll('.csv-popup.visible').forEach(function(p) {
            if (p.id !== popupId) {
                p.classList.add('animate-out');
                p.classList.remove('animate-in');
                setTimeout(function() {
                    p.classList.remove('visible');
                    p.classList.remove('animate-out');
                }, 200);
            }
        });

        if (popup.classList.contains('visible')) {
            popup.classList.add('animate-out');
            popup.classList.remove('animate-in');
            setTimeout(function() {
                popup.classList.remove('visible');
                popup.classList.remove('animate-out');
                document.removeEventListener('click', closePopupOnClickOutside);
            }, 200);
        } else {
            popup.classList.add('visible');
            popup.classList.add('animate-in');
            popup.classList.remove('animate-out');
            document.addEventListener('click', closePopupOnClickOutside);
        }
    }

    function closePopupOnClickOutside(event) {
        document.querySelectorAll('.csv-popup.visible').forEach(function(popup) {
            var button = popup.previousElementSibling;
            if (!popup.contains(event.target) && (!button || !button.contains(event.target))) {
                popup.classList.add('animate-out');
                popup.classList.remove('animate-in');
                setTimeout(function() {
                    popup.classList.remove('visible');
                    popup.classList.remove('animate-out');
                }, 200);
            }
        });
        // Remove listener if no popups are visible
        if (document.querySelectorAll('.csv-popup.visible').length === 0) {
            document.removeEventListener('click', closePopupOnClickOutside);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof $ !== 'undefined' && typeof $.fn !== 'undefined' && typeof $.fn.tooltip === 'function') {
            $('[data-toggle="tooltip"]').tooltip();
        }
        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Tooltip !== 'undefined') {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    });
</script>