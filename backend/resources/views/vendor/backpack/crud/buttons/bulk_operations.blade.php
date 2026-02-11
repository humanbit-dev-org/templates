@if ($crud->hasAccess('delete') || $crud->hasAccess('update'))
<!-- Bulk operations toolbar -->
<div id="bulk-operations" class="bulk-operations-container">
    <div class="bulk-operations-content">
        <div class="bulk-actions-counter">
            <i class="la la-check-square me-2"></i>
            <span class="bulk-counter-text"></span>
        </div>
        <div class="bulk-actions-buttons">
            @if ($crud->hasAccess('delete'))
            <button type="button" id="bulk-delete-btn" class="mini-btn mini-btn-danger" title="{{ trans('backpack::crud.delete') }}">
                <i class="la la-trash me-1"></i> {{ trans('backpack::crud.delete') }}
            </button>
            @endif
            
            @if ($crud->hasAccess('update'))
            <button type="button" id="bulk-duplicate-btn" class="mini-btn mini-btn-primary" title="{{ trans('backpack::crud.duplicate') }}">
                <i class="la la-clone me-1"></i> {{ trans('backpack::crud.duplicate') }}
            </button>
            @endif

            <button type="button" id="bulk-cancel-btn" class="mini-btn mini-btn-light" title="{{ trans('backpack::crud.cancel') }}">
                <i class="la la-times me-1"></i> {{ trans('backpack::crud.cancel') }}
            </button>
        </div>
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
                const isDuplicated = $(this).hasClass('duplicated');
                const duplicateBadge = isDuplicated ? '<span class="duplicate-badge" title="Elemento duplicato"><i class="la la-clone"></i></span>' : '';
                
                $(this).prepend(`
                    <td class="bulk-checkbox">
                        <div class="fancy-checkbox">
                            <input type="checkbox" class="entry-checkbox" id="${checkboxId}" data-entry-id="${entryId}">
                            <label for="${checkboxId}"></label>
                        </div>
                        ${duplicateBadge}
                    </td>
                `);
            }
        } else {
            // Checkbox already exists, check if row is duplicated and add badge if missing
            const checkboxCell = $(this).find('td.bulk-checkbox');
            if ($(this).hasClass('duplicated') && !checkboxCell.find('.duplicate-badge').length) {
                checkboxCell.append('<span class="duplicate-badge" title="Elemento duplicato"><i class="la la-clone"></i></span>');
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
    const bulkOps = $('#bulk-operations');
    const wrapper = $('#crudTable_wrapper');
    
    if (selectedCount > 0) {
        // Update counter text
        const counterText = selectedCount + ' {{ trans("backpack::crud.selected") }}';
        $('.bulk-counter-text').text(counterText);
        
        // Show bulk operations with smooth animation (zero layout shift)
        bulkOps.addClass('active');
        wrapper.addClass('bulk-ops-active');
        
        // Disable action buttons in the action column
        $('.crud-action-btn').addClass('disabled-action-btn');
        
        // Apply selected style to checked rows with animation
        $('#crudTable tbody tr').each(function() {
            const checkbox = $(this).find('.entry-checkbox');
            const checkboxLabel = $(this).find('.fancy-checkbox label');
            
            if (checkbox.prop('checked')) {
                $(this).addClass('bulk-selected-row');
                checkboxLabel.addClass('pulse-animation');
            } else {
                $(this).removeClass('bulk-selected-row');
                checkboxLabel.removeClass('pulse-animation');
            }
        });
        
        bulkModeActive = true;
    } else {
        // Hide bulk operations with smooth animation
        bulkOps.removeClass('active');
        wrapper.removeClass('bulk-ops-active');
        
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
                        
                        // Deselect all checkboxes
                        $('.entry-checkbox').prop('checked', false);
                        $('#select-all-checkbox').prop('checked', false);
                        
                        // Hide bulk operations immediately
                        updateBulkOperationsVisibility();
                        
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
                        
                        // Deselect all checkboxes
                        $('.entry-checkbox').prop('checked', false);
                        $('#select-all-checkbox').prop('checked', false);
                        
                        // Hide bulk operations immediately
                        updateBulkOperationsVisibility();
                        
                        // Redraw the table
                        crud.table.draw(false);
                        
                        // Return to the same page
                        crud.table.page(currentPage).draw('page');
                        
                        // Listen for the draw event to highlight the duplicated rows
                        crud.table.one('draw', function() {
                            // Small delay to ensure table is fully rendered
                            setTimeout(function() {
                                if (duplicatedEntries.length > 0) {
                                    // Convert all IDs to strings for consistent comparison
                                    const duplicatedIds = duplicatedEntries.map(id => String(id));
                                    
                                    $('#crudTable tbody tr').each(function() {
                                        let checkbox = $(this).find('.entry-checkbox');
                                        let id = checkbox.data('entry-id');
                                        
                                        if (id && duplicatedIds.includes(String(id))) {
                                            // Add duplicated class to row
                                            $(this).addClass('duplicated');
                                            
                                            // Add visual indicator badge near checkbox
                                            let checkboxCell = $(this).find('td.bulk-checkbox');
                                            if (checkboxCell.length && !checkboxCell.find('.duplicate-badge').length) {
                                                checkboxCell.append('<span class="duplicate-badge" title="Elemento duplicato"><i class="la la-clone"></i></span>');
                                            }
                                        }
                                    });
                                }
                            }, 100);
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
                
                // Check if there are actually any selected checkboxes
                const selectedCount = $('.entry-checkbox:checked').length;
                if (selectedCount === 0 && bulkModeActive) {
                    // No checkboxes selected, ensure bulk operations are hidden
                    bulkModeActive = false;
                    updateBulkOperationsVisibility();
                } else if (bulkModeActive) {
                    // Re-apply disabled style to action buttons if in bulk mode
                    $('.crud-action-btn').addClass('disabled-action-btn');
                    // Re-apply padding and active state
                    $('#crudTable_wrapper').addClass('bulk-ops-active');
                    $('#bulk-operations').addClass('active');
                }
            }, 10);
        });
    }
}

// Initialization - Position the bulk operations toolbar within the wrapper
$(document).ready(function() {
    console.log("Document ready, initializing bulk operations");
    
    // Add bulk operations to table wrapper (always present to avoid layout shift)
    let bulkOperationsElement = $('#bulk-operations');
    if (bulkOperationsElement.length && !bulkOperationsElement.parent().is('#crudTable_wrapper')) {
        $('#crudTable_wrapper').prepend(bulkOperationsElement);
    }
    
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
            /* Bulk operations container - zero layout shift solution */
            #crudTable_wrapper {
                position: relative;
            }
            
            .bulk-operations-container {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                z-index: 10;
                opacity: 0;
                visibility: hidden;
                transform: translateY(-10px);
                transition: opacity 0.25s cubic-bezier(0.4, 0, 0.2, 1),
                            visibility 0.25s cubic-bezier(0.4, 0, 0.2, 1),
                            transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
                pointer-events: none;
            }
            
            .bulk-operations-container.active {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
                pointer-events: auto;
            }
            
            /* Add padding to table wrapper when bulk operations are active to prevent overlap */
            #crudTable_wrapper.bulk-ops-active {
                padding-top: 60px;
                transition: padding-top 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            /* Content wrapper */
            .bulk-operations-content {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 16px;
                background: linear-gradient(135deg, rgba(var(--tblr-primary-rgb), 0.08) 0%, rgba(var(--tblr-primary-rgb), 0.04) 100%);
                border-radius: 8px;
                border: 1.5px solid rgba(var(--tblr-primary-rgb), 0.2);
                box-shadow: 0 2px 8px rgba(var(--tblr-primary-rgb), 0.1),
                            0 1px 3px rgba(0, 0, 0, 0.08);
                backdrop-filter: blur(4px);
            }
            
            /* Counter for selected items */
            .bulk-actions-counter {
                display: flex;
                align-items: center;
                font-size: 0.9rem;
                color: var(--tblr-primary);
                font-weight: 600;
            }
            
            .bulk-actions-counter i {
                font-size: 1.1rem;
                color: var(--tblr-primary);
            }
            
            .bulk-counter-text {
                font-weight: 600;
                letter-spacing: 0.02em;
            }
            
            /* Buttons container */
            .bulk-actions-buttons {
                display: flex;
                gap: 8px;
                align-items: center;
            }
            
            /* Disabled action buttons */
            .disabled-action-btn {
                opacity: 0.4 !important;
                pointer-events: none !important;
                cursor: default !important;
            }
            
            /* Enhanced button styles */
            .mini-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 0.8rem;
                padding: 0.4rem 0.85rem;
                border-radius: 6px;
                border: 0;
                font-weight: 600;
                line-height: 1.4;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                cursor: pointer;
                white-space: nowrap;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
            }
            
            .mini-btn i {
                font-size: 0.9rem;
            }
            
            .mini-btn:active {
                transform: translateY(1px);
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
            }
            
            .mini-btn-primary {
                background: linear-gradient(135deg, var(--tblr-primary) 0%, rgba(var(--tblr-primary-rgb), 0.9) 100%);
                color: #fff;
                box-shadow: 0 2px 4px rgba(var(--tblr-primary-rgb), 0.25);
            }
            
            .mini-btn-primary:hover {
                opacity: 0.85;
            }
            
            .mini-btn-danger {
                background: linear-gradient(135deg, var(--tblr-danger) 0%, rgba(var(--tblr-danger-rgb), 0.9) 100%);
                color: #fff;
                box-shadow: 0 2px 4px rgba(var(--tblr-danger-rgb), 0.25);
            }
            
            .mini-btn-danger:hover {
                opacity: 0.85;
            }
            
            .mini-btn-light {
                background-color: #ffffff;
                color: #6b7280;
                border: 1.5px solid #e5e7eb;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
            }
            
            .mini-btn-light:hover {
                opacity: 0.85;
            }
            
            /* Checkbox column - small and compact */
            .bulk-checkbox {
                width: 24px !important;
                text-align: center;
                vertical-align: middle;
                padding-left: 4px !important;
                padding-right: 4px !important;
            }
            
            /* Select all checkbox in header - add left padding */
            #crudTable thead tr th.bulk-checkbox {
                padding-left: 12px !important;
            }
            
            /* Enhanced fancy checkboxes */
            .fancy-checkbox {
                position: relative;
                display: inline-block;
                width: 18px;
                height: 18px;
            }
            
            .fancy-checkbox input[type="checkbox"] {
                opacity: 0;
                position: absolute;
                cursor: pointer;
                z-index: 2;
                width: 18px;
                height: 18px;
                margin: 0;
            }
            
            .fancy-checkbox label {
                position: absolute;
                width: 18px;
                height: 18px;
                background-color: #ffffff;
                border: 2px solid rgba(var(--tblr-primary-rgb), 0.4);
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                margin: 0;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            }
            
            .fancy-checkbox input[type="checkbox"]:checked + label {
                background: linear-gradient(135deg, var(--tblr-primary) 0%, rgba(var(--tblr-primary-rgb), 0.9) 100%);
                border-color: var(--tblr-primary);
                box-shadow: 0 2px 4px rgba(var(--tblr-primary-rgb), 0.3);
            }
            
            .fancy-checkbox input[type="checkbox"]:checked + label:after {
                content: '';
                position: absolute;
                left: 5px;
                top: 2px;
                width: 5px;
                height: 9px;
                border: solid white;
                border-width: 0 2px 2px 0;
                transform: rotate(45deg);
            }
            
            .fancy-checkbox input[type="checkbox"]:hover + label {
                border-color: var(--tblr-primary);
                box-shadow: 0 0 0 2px rgba(var(--tblr-primary-rgb), 0.15);
                transform: scale(1.05);
            }
            
            .fancy-checkbox input[type="checkbox"]:checked:hover + label {
                transform: scale(1.08);
                box-shadow: 0 3px 6px rgba(var(--tblr-primary-rgb), 0.4);
            }
            
            /* Selected row highlighting - subtle and elegant */
            tr.bulk-selected-row {
                background-color: rgba(var(--tblr-primary-rgb), 0.04) !important;
                transition: background-color 0.2s ease;
            }
            
            tr.bulk-selected-row td:first-child {
                border-left: 3px solid var(--tblr-primary) !important;
                padding-left: calc(4px - 3px) !important;
            }
            
            tr.bulk-selected-row:hover {
                background-color: rgba(var(--tblr-primary-rgb), 0.06) !important;
            }
            
            /* Pulse animation for checkbox - subtle */
            @keyframes pulse {
                0% {
                    box-shadow: 0 0 0 0 rgba(var(--tblr-primary-rgb), 0.4);
                }
                70% {
                    box-shadow: 0 0 0 3px rgba(var(--tblr-primary-rgb), 0);
                }
                100% {
                    box-shadow: 0 0 0 0 rgba(var(--tblr-primary-rgb), 0);
                }
            }
            
            .pulse-animation {
                animation: pulse 2s infinite;
            }
            
            /* Row transition animation - smooth */
            @keyframes rowTransition {
                0% { background-color: transparent; }
                50% { background-color: rgba(var(--tblr-primary-rgb), 0.08); }
                100% { background-color: transparent; }
            }
            
            .row-transition-animation {
                animation: rowTransition 0.4s ease-out;
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
            
            /* Duplicate badge indicator - visible near checkbox */
            .duplicate-badge {
                position: absolute;
                top: -2px;
                right: -8px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 18px;
                height: 18px;
                background: var(--tblr-primary);
                color: #ffffff;
                border-radius: 50%;
                font-size: 0.7rem;
                z-index: 5;
                box-shadow: 0 2px 4px rgba(var(--tblr-primary), 0.4);
                animation: duplicateBadgePulse 1.5s ease-in-out infinite;
                pointer-events: none;
            }
            
            .duplicate-badge i {
                line-height: 1;
                margin: 0;
            }
            
            @keyframes duplicateBadgePulse {
                0%, 100% {
                    transform: scale(1);
                    box-shadow: 0 2px 4px rgba(var(--tblr-primary), 0.4);
                }
                50% {
                    transform: scale(1.15);
                    box-shadow: 0 3px 8px rgba(var(--tblr-primary), 0.6);
                }
            }
            
            /* Ensure checkbox cell has relative positioning for badge */
            td.bulk-checkbox {
                position: relative;
            }
            
            /* Responsive design for mobile */
            @media (max-width: 768px) {
                .bulk-operations-content {
                    flex-direction: column;
                    gap: 10px;
                    padding: 12px;
                }
                
                .bulk-actions-counter {
                    width: 100%;
                    justify-content: center;
                }
                
                .bulk-actions-buttons {
                    width: 100%;
                    justify-content: center;
                    flex-wrap: wrap;
                }
                
                .mini-btn {
                    font-size: 0.75rem;
                    padding: 0.35rem 0.7rem;
                }
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
