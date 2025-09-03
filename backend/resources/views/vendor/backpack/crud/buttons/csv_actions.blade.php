@php
// List of routes where import is disabled
$disableImportFor = ['page'];

// Extract the current route name from the path
$currentRoute = str_replace(config('backpack.base.route_prefix', 'admin').'/', '', $crud->route);

// Check if import should be disabled for this route
$importDisabled = in_array($currentRoute, $disableImportFor);
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
                    <a href="{{ url($crud->route.'/export-csv') }}" class="btn btn-primary">
                        <i class="la la-file-export me-1"></i> {{ trans('backpack::crud.csv_export') }}
                    </a>

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