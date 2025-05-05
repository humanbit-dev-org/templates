@if ($crud->hasAccess('delete') || $crud->hasAccess('update'))
<!-- Bulk operations toolbar -->
<div id="bulk-operations" class="d-none fade-in-out">
    <div class="bulk-actions-counter"></div>
    <div class="bulk-actions-buttons">
        @if ($crud->hasAccess('delete'))
        <button type="button" id="bulk-delete-btn" class="mini-btn mini-btn-danger">
            <i class="la la-trash me-1"></i> {{ trans('backpack::crud.delete') }}
        </button>
        @endif
        
        @if ($crud->hasAccess('update'))
        <button type="button" id="bulk-duplicate-btn" class="mini-btn mini-btn-primary">
            <i class="la la-clone me-1"></i> {{ trans('backpack::crud.duplicate') }}
        </button>
        @endif

        <button type="button" id="bulk-cancel-btn" class="mini-btn mini-btn-light">
            <i class="la la-times"></i>
        </button>
    </div>
</div>

{{-- Button Javascript --}}
@push('after_scripts')
<script>
// Keep track of selected IDs
let selectedEntryIds = [];
let bulkModeActive = false;

// Main function to add bulk operations to the table
function addBulkOperations() {
    console.log("Initializing bulk operations...");
    
    // Add select all checkbox in table header if it doesn't exist
    if (!$('#crudTable thead tr:first-child th:first-child').hasClass('bulk-checkbox')) {
        $('#crudTable thead tr:first-child').prepend(`
            <th class="bulk-checkbox">
                <div class="fancy-checkbox">
                    <input type="checkbox" id="select-all-checkbox">
                    <label for="select-all-checkbox"></label>
                </div>
            </th>
        `);
        
        console.log("Added select-all checkbox to table header");
    }
    
    // Add checkbox to each row if it doesn't exist
    $('#crudTable tbody tr').each(function() {
        if (!$(this).find('td:first-child').hasClass('bulk-checkbox')) {
            let entryId = $(this).attr('data-entry-id') || $(this).data('entry-id');
            if (!entryId) {
                // Try to get ID from the action buttons
                let actionButton = $(this).find('[data-button-type="delete"]');
                if (actionButton.length) {
                    let route = actionButton.data('route');
                    entryId = route.split('/').pop();
                }
            }
            
            if (entryId) {
                const checkboxId = 'entry-checkbox-' + entryId;
                $(this).prepend(`
                    <td class="bulk-checkbox">
                        <div class="fancy-checkbox">
                            <input type="checkbox" class="entry-checkbox" id="${checkboxId}" data-entry-id="${entryId}">
                            <label for="${checkboxId}"></label>
                        </div>
                    </td>
                `);
            }
        }
    });
    
    // Register event handlers for checkboxes
    setupCheckboxHandlers();
    
    // Restore previously selected checkboxes
    restoreSelectedRows();
    
    // Adjust other columns if needed
    if (typeof crud !== 'undefined' && crud.table && crud.table.responsive) {
        crud.table.responsive.recalc();
    }
}

// Setup event handlers for checkboxes
function setupCheckboxHandlers() {
    // Handle select all checkbox
    $('#select-all-checkbox').off('change').on('change', function() {
        const isChecked = $(this).prop('checked');
        
        $('.entry-checkbox').each(function() {
            const wasChecked = $(this).prop('checked');
            $(this).prop('checked', isChecked);
            
            // Add animation when changing state
            if (wasChecked !== isChecked) {
                const row = $(this).closest('tr');
                row.addClass('row-transition-animation');
                setTimeout(() => {
                    row.removeClass('row-transition-animation');
                }, 300);
            }
        });
        
        saveSelectedIds();
        updateBulkOperationsVisibility();
    });
    
    // Handle individual checkboxes
    $('.entry-checkbox').off('change').on('change', function() {
        const isChecked = $(this).prop('checked');
        const row = $(this).closest('tr');
        
        // Add animation when checking/unchecking
        row.addClass('row-transition-animation');
        setTimeout(() => {
            row.removeClass('row-transition-animation');
        }, 300);
        
        saveSelectedIds();
        updateBulkOperationsVisibility();
        
        // Update select all checkbox state
        if ($('.entry-checkbox:checked').length === $('.entry-checkbox').length) {
            $('#select-all-checkbox').prop('checked', true);
        } else {
            $('#select-all-checkbox').prop('checked', false);
        }
    });
    
    // Bulk delete operation
    $('#bulk-delete-btn').off('click').on('click', function() {
        if (selectedEntryIds.length === 0) return;
        
        bulkDelete(selectedEntryIds);
    });
    
    // Bulk duplicate operation
    $('#bulk-duplicate-btn').off('click').on('click', function() {
        if (selectedEntryIds.length === 0) return;
        
        bulkDuplicate(selectedEntryIds);
    });
    
    // Cancel bulk operations
    $('#bulk-cancel-btn').off('click').on('click', function() {
        $('.entry-checkbox').prop('checked', false);
        $('#select-all-checkbox').prop('checked', false);
        selectedEntryIds = [];
        localStorage.removeItem('bulkSelectedIds_' + window.location.pathname);
        updateBulkOperationsVisibility();
    });
}

// Check if there are any selected checkboxes and show/hide bulk operations
function updateBulkOperationsVisibility() {
    const selectedCount = $('.entry-checkbox:checked').length;
    
    if (selectedCount > 0) {
        // Show bulk operations with animation
        $('#bulk-operations').removeClass('d-none').addClass('active');
        $('.bulk-actions-counter').text(selectedCount + ' {{ trans("backpack::crud.selected") }}');
        
        // Disable action buttons in the action column
        $('.crud-action-btn').addClass('disabled-action-btn');
        
        // Apply selected style to checked rows with animation
        $('#crudTable tbody tr').each(function() {
            const checkbox = $(this).find('.entry-checkbox');
            const checkboxLabel = $(this).find('.fancy-checkbox label');
            
            if (checkbox.prop('checked')) {
                $(this).addClass('bulk-selected-row');
                // Add ripple effect animation
                checkboxLabel.addClass('pulse-animation');
                
                // Add a slight animation to the row
                $(this).addClass('row-selected-animation');
                setTimeout(() => {
                    $(this).removeClass('row-selected-animation');
                }, 500);
            } else {
                $(this).removeClass('bulk-selected-row');
                checkboxLabel.removeClass('pulse-animation');
            }
        });
        
        bulkModeActive = true;
    } else {
        // Hide bulk operations with animation
        $('#bulk-operations').removeClass('active');
        setTimeout(function() {
            if (!$('#bulk-operations').hasClass('active')) {
                $('#bulk-operations').addClass('d-none');
            }
        }, 300);
        
        // Enable action buttons
        $('.crud-action-btn').removeClass('disabled-action-btn');
        
        // Remove selected style from all rows
        $('#crudTable tbody tr').removeClass('bulk-selected-row');
        $('.fancy-checkbox label').removeClass('pulse-animation');
        
        bulkModeActive = false;
    }
}

// Save selected IDs
function saveSelectedIds() {
    selectedEntryIds = $('.entry-checkbox:checked').map(function() {
        return $(this).data('entry-id').toString();
    }).get();
    
    // Store in localStorage for persistence across page reloads
    if (selectedEntryIds.length > 0) {
        localStorage.setItem('bulkSelectedIds_' + window.location.pathname, JSON.stringify(selectedEntryIds));
    } else {
        localStorage.removeItem('bulkSelectedIds_' + window.location.pathname);
    }
}

// Restore selected rows
function restoreSelectedRows() {
    console.log("Attempting to restore selected rows...");
    
    // First check localStorage
    const storedIds = localStorage.getItem('bulkSelectedIds_' + window.location.pathname);
    if (storedIds) {
        selectedEntryIds = JSON.parse(storedIds);
        console.log("Found stored IDs:", selectedEntryIds);
    }
    
    if (selectedEntryIds.length > 0) {
        $('.entry-checkbox').each(function() {
            let entryId = $(this).data('entry-id').toString();
            if (selectedEntryIds.includes(entryId)) {
                $(this).prop('checked', true);
            }
        });
        
        updateBulkOperationsVisibility();
    }
}

// Bulk delete function
function bulkDelete(ids) {
    swal({
        title: "{!! trans('backpack::base.warning') !!}",
        text: "{!! trans('backpack::crud.bulk_delete_confirm') !!}",
        icon: "warning",
        buttons: {
            cancel: {
                text: "{!! trans('backpack::crud.cancel') !!}",
                value: null,
                visible: true,
                className: "bg-secondary",
                closeModal: true,
            },
            delete: {
                text: "{!! trans('backpack::crud.delete') !!}",
                value: true,
                visible: true,
                className: "bg-danger",
            },
        },
        dangerMode: true,
    }).then((value) => {
        if (value) {
            $.ajax({
                url: "{{ url($crud->route.'/bulk-delete') }}",
                type: 'POST',
                data: {
                    ids: ids,
                    _token: '{{ csrf_token() }}'
                },
                success: function(result) {
                    if (result.success) {
                        // Save the current page number before redrawing
                        const currentPage = crud.table.page();
                        
                        // Clear selected IDs after successful deletion
                        selectedEntryIds = [];
                        localStorage.removeItem('bulkSelectedIds_' + window.location.pathname);
                        
                        // Redraw the table
                        crud.table.draw(false);
                        
                        // Return to the same page
                        crud.table.page(currentPage).draw('page');
                        
                        // Show success notification
                        new Noty({
                            type: "success",
                            text: "{!! '<strong>'.trans('backpack::crud.bulk_delete_confirmation_title').'</strong><br>'.trans('backpack::crud.bulk_delete_confirmation_message') !!}"
                        }).show();
                    } else {
                        // Show error notification
                        swal({
                            title: "{!! trans('backpack::crud.delete_confirmation_not_title') !!}",
                            text: result.message || "{!! trans('backpack::crud.delete_confirmation_not_message') !!}",
                            icon: "error",
                            timer: 4000,
                            buttons: false,
                        });
                    }
                },
                error: function(result) {
                    // Show error notification
                    swal({
                        title: "{!! trans('backpack::crud.delete_confirmation_not_title') !!}",
                        text: "{!! trans('backpack::crud.delete_confirmation_not_message') !!}",
                        icon: "error",
                        timer: 4000,
                        buttons: false,
                    });
                }
            });
        }
    });
}

// Bulk duplicate function
function bulkDuplicate(ids) {
    swal({
        title: "{!! trans('backpack::base.notice') !!}",
        text: "{!! trans('backpack::crud.bulk_duplicate_confirm') !!}",
        icon: "info",
        buttons: {
            cancel: {
                text: "{!! trans('backpack::crud.cancel') !!}",
                visible: true,
                className: "bg-secondary",
                closeModal: true,
            },
            confirm: {
                text: "{!! trans('backpack::crud.duplicate') !!}",
                visible: true,
                className: "bg-primary",
            },
        },
    }).then((value) => {
        if (value) {
            $.ajax({
                url: "{{ url($crud->route.'/bulk-duplicate') }}",
                type: 'POST',
                data: {
                    ids: ids,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        // Keep a reference to the duplicated items
                        let duplicatedEntries = response.new_entries || [];
                        
                        // Save the current page number before redrawing
                        const currentPage = crud.table.page();
                        
                        // Clear selected IDs after successful duplication
                        selectedEntryIds = [];
                        localStorage.removeItem('bulkSelectedIds_' + window.location.pathname);
                        
                        // Redraw the table
                        crud.table.draw(false);
                        
                        // Return to the same page
                        crud.table.page(currentPage).draw('page');
                        
                        // Listen for the draw event to highlight the duplicated rows
                        crud.table.one('draw', function() {
                            // Highlight the duplicated rows
                            if (duplicatedEntries.length > 0) {
                                $('#crudTable tbody tr').each(function() {
                                    let id = $(this).find('.entry-checkbox').data('entry-id');
                                    if (id && duplicatedEntries.includes(id)) {
                                        $(this).addClass('duplicated');
                                    }
                                });
                            }
                        });
                        
                        // Show success notification
                        new Noty({
                            type: "success",
                            text: "{!! '<strong>'.trans('backpack::crud.bulk_duplicate_confirmation_title').'</strong><br>'.trans('backpack::crud.bulk_duplicate_confirmation_message') !!}"
                        }).show();
                    } else {
                        // Show error notification
                        swal({
                            title: "{!! trans('backpack::crud.duplicate_confirmation_not_title') !!}",
                            text: response.message || "{!! trans('backpack::crud.duplicate_confirmation_not_message') !!}",
                            icon: "error",
                            timer: 4000,
                            buttons: false,
                        });
                    }
                },
                error: function() {
                    // Show error notification
                    swal({
                        title: "{!! trans('backpack::crud.duplicate_confirmation_not_title') !!}",
                        text: "{!! trans('backpack::crud.duplicate_confirmation_not_message') !!}",
                        icon: "error",
                        timer: 4000,
                        buttons: false,
                    });
                }
            });
        }
    });
}

// Intercept DataTables events before and after drawing
function interceptDataTablesEvents() {
    if (typeof crud !== 'undefined' && crud.table) {
        // When DataTables is about to redraw, register an event to run after drawing
        crud.table.on('preDrawComplete', function() {
            console.log("Table is about to be redrawn");
        });
        
        // After DataTables has redrawn the table
        crud.table.on('draw', function() {
            console.log("Table has been redrawn, reinitializing bulk operations");
            
            // Small delay to ensure the table is fully rendered
            setTimeout(function() {
                addBulkOperations();
                if (bulkModeActive) {
                    // Re-apply disabled style to action buttons if in bulk mode
                    $('.crud-action-btn').addClass('disabled-action-btn');
                }
            }, 10);
        });
    }
}

// Initialization - Position the bulk operations toolbar within the wrapper
$(document).ready(function() {
    console.log("Document ready, initializing bulk operations");
    
    // Add bulk operations to table wrapper
    let bulkOperationsElement = $('#bulk-operations');
    $('#crudTable_wrapper').prepend(bulkOperationsElement);
    
    // Make sure to intercept DataTables events
    interceptDataTablesEvents();
    
    // Initial setup
    addBulkOperations();
});

// Make sure bulk operations are added after each table redraw
if (typeof crud !== 'undefined') {
    crud.addFunctionToDataTablesDrawEventQueue('addBulkOperations');
    
    // Override the default DataTables draw method to ensure our code runs
    if (crud.table) {
        const originalDraw = crud.table.draw;
        crud.table.draw = function() {
            console.log("DataTables draw method called");
            const result = originalDraw.apply(this, arguments);
            
            // Our custom code after the original draw method
            setTimeout(function() {
                addBulkOperations();
            }, 50);
            
            return result;
        };
    }
}

// Add CSS styles for bulk operations
$(document).ready(function() {
    $('head').append(`
        <style>
            /* Bulk operations bar */
            .fade-in-out {
                transition: all 0.3s ease-in-out;
                opacity: 0;
                transform: translateY(-10px);
                overflow: hidden;
            }
            
            .fade-in-out.active {
                opacity: 1;
                transform: translateY(0);
            }
            
            #bulk-operations {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 5px 10px;
                background-color: rgba(var(--tblr-primary-rgb), 0.03);
                border-radius: 4px;
                margin-bottom: 10px;
                border: 1px solid rgba(var(--tblr-primary-rgb), 0.1);
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            
            /* Counter for selected items */
            .bulk-actions-counter {
                font-size: 0.85rem;
                color: var(--tblr-primary);
                font-weight: 500;
            }
            
            /* Buttons container */
            .bulk-actions-buttons {
                display: flex;
                gap: 6px;
            }
            
            /* Disabled action buttons */
            .disabled-action-btn {
                opacity: 0.4 !important;
                pointer-events: none !important;
                cursor: default !important;
            }
            
            /* Extra-small custom button styles */
            .mini-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 0.75rem;
                padding: 0.2rem 0.5rem;
                border-radius: 3px;
                border: 0;
                font-weight: 500;
                line-height: 1.3;
                transition: all 0.15s ease-in-out;
                cursor: pointer;
            }
            
            .mini-btn i {
                font-size: 0.875rem;
                margin-right: 0.25rem;
            }
            
            .mini-btn-primary {
                background-color: var(--tblr-primary);
                color: #fff;
                box-shadow: 0 1px 2px rgba(var(--tblr-primary-rgb), 0.15);
            }
            
            .mini-btn-primary:hover {
                background-color: rgba(var(--tblr-primary-rgb), 0.9);
            }
            
            .mini-btn-danger {
                background-color: var(--tblr-danger);
                color: #fff;
                box-shadow: 0 1px 2px rgba(var(--tblr-danger-rgb), 0.15);
            }
            
            .mini-btn-danger:hover {
                background-color: rgba(var(--tblr-danger-rgb), 0.9);
            }
            
            .mini-btn-light {
                background-color: #f8f9fa;
                color: #495057;
                border: 1px solid #dee2e6;
            }
            
            .mini-btn-light:hover {
                background-color: #e9ecef;
                border-color: #dde0e3;
            }
            
            /* Checkbox column - small and compact */
            .bulk-checkbox {
                width: 24px !important;
                text-align: center;
                vertical-align: middle;
                padding-left: 4px !important;
                padding-right: 4px !important;
            }
            
            /* Fancy checkboxes */
            .fancy-checkbox {
                position: relative;
                display: inline-block;
                width: 14px;
                height: 14px;
            }
            
            .fancy-checkbox input[type="checkbox"] {
                opacity: 0;
                position: absolute;
                cursor: pointer;
                z-index: 2;
                width: 14px;
                height: 14px;
                margin: 0;
            }
            
            .fancy-checkbox label {
                position: absolute;
                width: 14px;
                height: 14px;
                background-color: var(--tblr-bg-surface);
                border: 1px solid rgba(var(--tblr-primary-rgb), 0.3);
                border-radius: 2px;
                cursor: pointer;
                transition: all 0.15s ease;
                margin: 0;
            }
            
            .fancy-checkbox input[type="checkbox"]:checked + label {
                background-color: var(--tblr-primary);
                border-color: var(--tblr-primary);
            }
            
            .fancy-checkbox input[type="checkbox"]:checked + label:after {
                content: '';
                position: absolute;
                left: 4px;
                top: 1px;
                width: 4px;
                height: 8px;
                border: solid white;
                border-width: 0 2px 2px 0;
                transform: rotate(45deg);
            }
            
            .fancy-checkbox input[type="checkbox"]:hover + label {
                border-color: var(--tblr-primary);
                box-shadow: 0 0 0 1px rgba(var(--tblr-primary-rgb), 0.1);
            }
            
            /* Selected row highlighting - just left border */
            tr.bulk-selected-row td:first-child {
                border-left: 3px solid var(--tblr-primary) !important;
            }
            
            /* Pulse animation for checkbox */
            @keyframes pulse {
                0% {
                    box-shadow: 0 0 0 0 rgba(var(--tblr-primary-rgb), 0.7);
                }
                70% {
                    box-shadow: 0 0 0 4px rgba(var(--tblr-primary-rgb), 0);
                }
                100% {
                    box-shadow: 0 0 0 0 rgba(var(--tblr-primary-rgb), 0);
                }
            }
            
            .pulse-animation {
                animation: pulse 1.5s infinite;
            }
            
            /* Row selection animation */
            @keyframes rowSelectedEffect {
                0% { background-color: transparent; }
                50% { background-color: rgba(var(--tblr-primary-rgb), 0.1); }
                100% { background-color: transparent; }
            }
            
            .row-selected-animation {
                animation: rowSelectedEffect 0.5s ease-in-out;
            }
            
            /* Row transition animation */
            @keyframes rowTransition {
                0% { background-color: transparent; }
                50% { background-color: rgba(var(--tblr-primary-rgb), 0.1); }
                100% { background-color: transparent; }
            }
            
            .row-transition-animation {
                animation: rowTransition 0.3s ease-in-out;
            }
            
            /* Animation for duplicated rows */
            .duplicated {
                animation: pulseEffect 2s ease-in-out 6;
            }
            
            @keyframes pulseEffect {
                0% { background-color: transparent; }
                50% { background-color: rgba(var(--tblr-primary-rgb), 0.2); }
                100% { background-color: transparent; }
            }
        </style>
    `);
    
    // Add a class to all action buttons for easier targeting
    setTimeout(function() {
        $('#crudTable .btn').addClass('crud-action-btn');
    }, 100);
});
</script>
@endpush
@endif
