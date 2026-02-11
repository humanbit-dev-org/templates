@extends(backpack_view('blank'))

@section('header')
<section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
    <h1 class="text-capitalize mb-0" bp-section="page-heading">
        {{ trans('backpack::import.configure_import', ['name' => $crud]) }}
    </h1>
</section>
@endsection

@section('content')
<div class="row" bp-section="crud-operation-import">
    <div class="col-md-12">
        <!-- Unique Field Highlight -->
        <div class="card mb-3 border-primary unique-field-card">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <i class="la la-key text-primary fs-3 me-1"></i>
                    <h4 class="mb-0 fw-bold">{{ trans('backpack::import.unique_field') }}</h4>
                </div>

                <select name="unique_field" id="unique_field" class="form-control form-select">
                    <option value="">{{ trans('backpack::import.no_unique_field') }}</option>
                    <optgroup label="{{ trans('backpack::import.table_field') }}">
                        @foreach($tableColumns as $column)
                        <option value="{{ $column }}">{{ $column }}</option>
                        @endforeach
                    </optgroup>
                </select>
                
                <!-- Import Behavior Options -->
                <div id="import-behavior-options" class="mt-3 pt-3 border-top" style="display: none;">
                    <p class="mb-2 text-muted"><i class="la la-info-circle"></i> {{ trans('backpack::import.select_import_behavior') }}</p>
                    <div class="import-behavior-radios">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="import_behavior" id="behavior-update-insert" value="update_insert" checked>
                            <label class="form-check-label" for="behavior-update-insert">
                                <i class="la la-plus-circle text-success"></i> {{ trans('backpack::import.update_and_insert') }}
                                <small class="d-block text-muted">{{ trans('backpack::import.update_and_insert_description') }}</small>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="import_behavior" id="behavior-update-only" value="update_only">
                            <label class="form-check-label" for="behavior-update-only">
                                <i class="la la-sync text-primary"></i> {{ trans('backpack::import.update_only') }}
                                <small class="d-block text-muted">{{ trans('backpack::import.update_only_description') }}</small>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CSV Column Mapping -->
        <div class="card import-card">
            <div class="card-header border-bottom">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <h3 class="card-title mb-0">
                        <i class="la la-exchange-alt"></i>
                        {{ trans('backpack::import.column_mapping') }}
                    </h3>
                    <div>
                        <button type="button" id="auto-map-btn" class="btn btn-primary">
                            <i class="la la-magic"></i> {{ trans('backpack::import.auto_map') }}
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form id="import-mapping-form" class="import-form" method="POST" action="{{ url($crud_route.'/import-csv/process') }}">
                    @csrf
                    <input type="hidden" name="file_path" value="{{ $filePath }}">
                    <input type="hidden" name="delimiter" value="{{ $delimiter }}">
                    @if(isset($originalFileName))
                    <input type="hidden" name="original_file_name" value="{{ $originalFileName }}">
                    @endif
                    @if(isset($originalFileLastModified))
                    <input type="hidden" name="original_file_last_modified" value="{{ $originalFileLastModified }}">
                    @endif

                    <div class="alert alert-info mb-4">
                        <i class="la la-info-circle"></i>
                        {{ trans('backpack::import.mapping_instructions') }}
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover mapping-table">
                            <thead class="table-light">
                                <tr>
                                    <th width="3%">#</th>
                                    <th width="47%">{{ trans('backpack::import.table_field') }}</th>
                                    <th width="50%">{{ trans('backpack::import.csv_column') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tableColumns as $columnIndex => $column)
                                <tr class="mapping-row" data-table-column="{{ $column }}">
                                    <td class="text-center align-middle column-index-cell">
                                        <span class="badge bg-primary-subtle text-primary-emphasis column-index">{{ $columnIndex + 1 }}</span>
                                    </td>
                                    <td class="align-middle table-column">
                                        <span class="fw-medium">{{ $column }}</span>
                                        @if(in_array($column, $requiredColumns))
                                            <span class="badge bg-danger-subtle text-danger-emphasis required-field-badge">{{ trans('backpack::import.required_field') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center position-relative">
                                            <span class="mapping-type-indicator" data-column="{{ $column }}"></span>
                                            <select name="column_mapping_reverse[{{ $column }}]" class="form-select field-mapping-select csv-field-select @if(in_array($column, $requiredColumns)) required-field-select @endif">
                                                <option value="">{{ trans('backpack::import.do_not_import') }}</option>
                                                <optgroup label="{{ trans('backpack::import.csv_column') }}">
                                                    @foreach($csvHeaders as $index => $header)
                                                    <option value="{{ $index }}" data-csv-header="{{ $header }}" class="csv-option">
                                                        {{ $header }}
                                                    </option>
                                                    @endforeach
                                                </optgroup>
                                            </select>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="form-group mt-4 import-form-actions">
                        <a href="{{ url($crud_route) }}" class="btn btn-outline-secondary import-cancel-btn">
                            <i class="la la-ban"></i>
                            {{ trans('backpack::import.cancel') }}
                        </a>
                        <button type="submit" class="btn btn-success import-submit-btn" id="start-import">
                            <i class="la la-play"></i>
                            {{ trans('backpack::import.start_import') }}
                        </button>
                    </div>
                </form>

                <div id="import-progress" style="display: none;">
                    <div class="progress-container p-4 border rounded bg-light">
                        <div class="d-flex align-items-center gap-3 flex-wrap mb-3 import-status-header">
                            <h4 class="text-primary mb-0 d-flex align-items-center gap-2">
                                <i class="la la-sync fa-spin"></i>
                            {{ trans('backpack::import.import_in_progress') }}
                        </h4>
                            <div class="d-flex align-items-center gap-2">
                                <div class="import-loader-wrapper">
                                    <div class="import-loader-waves">
                                        <div class="wave"></div>
                                        <div class="wave"></div>
                                        <div class="wave"></div>
                                    </div>
                                </div>
                                <span id="progress-text" class="badge bg-primary fs-6">0%</span>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div id="import-stats" class="stats-container mt-3">
                                <div class="row">
                                    <div class="col-sm-6 col-md-3 mb-2">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-body text-center">
                                                <div class="h5 text-muted mb-2">{{ trans('backpack::import.processed_rows') }}</div>
                                                <div class="h3" id="total-rows">0</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-md-3 mb-2">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-body text-center">
                                                <div class="h5 text-muted mb-2">{{ trans('backpack::import.new_records') }}</div>
                                                <div class="h3 text-success" id="created-rows">0</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-md-3 mb-2">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-body text-center">
                                                <div class="h5 text-muted mb-2">{{ trans('backpack::import.updated_records') }}</div>
                                                <div class="h3 text-primary" id="updated-rows">0</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-md-3 mb-2">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-body text-center">
                                                <div class="h5 text-muted mb-2">{{ trans('backpack::import.skipped_rows') }}</div>
                                                <div class="h3 text-warning" id="skipped-rows">0</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Log operazioni in tempo reale -->
                        <div class="mt-4">
                            <h5 class="text-primary mb-3">
                                <i class="la la-terminal me-2"></i>
                                {{ trans('backpack::import.operation_log_title') }}
                            </h5>
                            
                            <!-- Box operazione corrente -->
                            <div id="current-operation-box" class="border rounded bg-light p-3 mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i id="current-op-icon" class="la la-info-circle fs-2 text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1 text-dark">
                                            <span id="current-op-title">{{ trans('backpack::import.import_in_progress') }}</span>
                                        </h6>
                                        <p id="current-op-details" class="mb-0 text-muted">
                                            <span id="operation-text">{{ trans('backpack::import.import_in_progress') }}</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Log storico compatto -->
                            <div class="border rounded bg-white p-2 import-log-container">
                                <div id="operation-log" class="small">
                                    <div class="text-muted">{{ trans('backpack::import.import_in_progress') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="import-results" style="display: none;">
                    <div class="progress-container p-4 border rounded bg-light">
                        <div class="d-flex align-items-center gap-3 flex-wrap mb-3 import-status-header">
                            <h4 class="text-success mb-0 d-flex align-items-center gap-2">
                                <i class="la la-check-circle"></i>
                            {{ trans('backpack::import.import_completed') }}
                        </h4>
                            <span id="result-progress-text" class="badge bg-success fs-6">100%</span>
                        </div>

                        <div id="result-stats" class="stats-container mt-3">
                            <div class="row">
                                <div class="col-sm-6 col-md-3 mb-2">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body text-center">
                                            <div class="h5 text-muted mb-2">{{ trans('backpack::import.processed_rows') }}</div>
                                            <div class="h3" id="result-total-rows">0</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3 mb-2">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body text-center">
                                            <div class="h5 text-muted mb-2">{{ trans('backpack::import.new_records') }}</div>
                                            <div class="h3 text-success" id="result-created-rows">0</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3 mb-2">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body text-center">
                                            <div class="h5 text-muted mb-2">{{ trans('backpack::import.updated_records') }}</div>
                                            <div class="h3 text-primary" id="result-updated-rows">0</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3 mb-2">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body text-center">
                                            <div class="h5 text-muted mb-2">{{ trans('backpack::import.skipped_rows') }}</div>
                                            <div class="h3 text-warning" id="result-skipped-rows">0</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-4">
                            <i class="la la-info-circle me-2"></i>
                            <span>
                                {{ trans('backpack::import.backup_created') }}
                                <code>/storage/backups/csv-imports/</code>
                            </span>
                        </div>
                        
                        <div class="alert alert-success mt-3" id="log-path-alert" style="display: none;">
                            <i class="la la-file-alt me-2"></i>
                            <span>
                                <strong>Log import salvati in:</strong>
                                <code id="log-path-text">-</code>
                            </span>
                        </div>

                        <div class="text-center mt-4">
                            <a href="{{ url($crud_route) }}" class="btn btn-success">
                                <i class="la la-table me-1"></i>
                                {{ trans('backpack::import.back_to_list') }}
                            </a>
                        </div>
                    </div>
                </div>

                <div id="import-error" style="display: none;">
                    <div class="alert alert-danger">
                        <h4 class="alert-heading mb-3">
                            <i class="la la-exclamation-circle me-2"></i>
                            {{ trans('backpack::import.import_error') }}
                        </h4>
                        <div id="error-details" class="border rounded bg-light p-3 mb-4" style="display: none;">
                            <h6 class="text-danger mb-3"><i class="la la-bug"></i> Technical Error Details:</h6>
                            <pre id="error-log" class="small text-pre-wrap bg-dark text-light p-3 rounded" style="max-height: 400px; overflow-y: auto; overflow-x: auto; word-wrap: break-word; white-space: pre-wrap;"></pre>
                        </div>
                        <div class="pt-3 border-top">
                            <p class="mb-2">
                                <i class="la la-info-circle me-2"></i>
                                {{ trans('backpack::import.backup_created') }}
                                <code>/storage/backups/csv-imports/</code>
                            </p>
                            <div id="error-log-path" class="mt-2" style="display: none;">
                                <i class="la la-file-alt me-2"></i>
                                <strong>Log import salvati in:</strong>
                                <code id="error-log-path-text">-</code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tooltip container to show full text -->
<div class="content-tooltip" id="contentTooltip"></div>

<!-- Overlay for Auto-Map effect -->
<div id="auto-map-overlay" class="auto-map-overlay">
    <div class="stars-container">
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
    </div>
    <div class="auto-map-animation">
        <i class="la la-magic fa-spin"></i>
        <span>{{ trans('backpack::import.mapping_in_progress') }}</span>
    </div>
</div>
@endsection

@push('after_scripts')

<script>
    $(document).ready(function() {
        // Set per tenere traccia degli ID di operazione già visualizzati
        const processedOperationIds = new Set();
        
        // Handling tooltips for truncated texts
        const contentTooltip = document.getElementById('contentTooltip');
        let activeTooltipElement = null;

        // Unified function to handle clicks on truncated elements
        function handleTruncatedClick(e) {
            e.stopPropagation();
            e.preventDefault(); // Prevents default behavior (text selection)

            // Get the full text, with fallback to the content itself
            const fullText = this.getAttribute('data-full-text') || this.textContent.trim();
            if (!fullText || fullText === '') return;

            // Remove active class from all elements
            document.querySelectorAll('.truncated-text').forEach(el => {
                el.classList.remove('truncated-text-active');
            });

            // If clicking on the same element, close the tooltip
            if (activeTooltipElement === this) {
                closeTooltip();
                return;
            }

            // First scroll the element into view, if necessary
            const cellElement = this.closest('.csv-cell');
            if (cellElement) {
                // Use scrollIntoView with smooth behavior for natural scrolling
                // and 'nearest' block to avoid scrolling too much
                cellElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                    inline: 'nearest'
                });
            }

            // If there's already an active tooltip, close it before opening the new one
            if (activeTooltipElement) {
                // Close the current tooltip with animation
                contentTooltip.classList.remove('show');

                // Store the new element to activate
                const newElement = this;
                const newText = fullText;

                // Wait for the closing animation to finish before opening the new one
                setTimeout(() => {
                    // Update the reference to the active element
                    activeTooltipElement = newElement;

                    // Add active class to the current element
                    newElement.classList.add('truncated-text-active');

                    // Update tooltip content
                    contentTooltip.textContent = newText;

                    // Position the tooltip near the clicked element
                    updateTooltipPosition();

                    // Show the tooltip with animation
                    setTimeout(() => {
                        contentTooltip.classList.add('show');
                    }, 10);
                }, 200);

                return;
            }

            // Otherwise, show the tooltip for the new element
            activeTooltipElement = this;

            // Add active class to the current element
            this.classList.add('truncated-text-active');

            // Update tooltip content
            contentTooltip.textContent = fullText;

            // Small delay to ensure scrolling is complete
            setTimeout(() => {
                // Position the tooltip near the clicked element and then show it
                updateTooltipPosition();

                // Show the tooltip with animation
                setTimeout(() => {
                    contentTooltip.classList.add('show');
                }, 10);
            }, 50);
        }

        // Function to close the tooltip with animation
        function closeTooltip() {
            if (!activeTooltipElement) return;

            // Remove the active class from the element
            activeTooltipElement.classList.remove('truncated-text-active');

            // Hide with animation
            contentTooltip.classList.remove('show');

            // After animation, hide completely
            setTimeout(() => {
                contentTooltip.style.display = 'none';
                activeTooltipElement = null;
            }, 200);
        }

        // Function to update tooltip position
        function updateTooltipPosition() {
            if (!activeTooltipElement) return;

            const rect = activeTooltipElement.getBoundingClientRect();

            // Calculate optimal position
            const tooltipWidth = Math.min(400, window.innerWidth - 40);
            contentTooltip.style.maxWidth = tooltipWidth + 'px';

            // Initialize base positioning
            let left = rect.left;
            let top = rect.bottom + window.scrollY + 8; // Added space

            // Check if tooltip goes off the right edge and adjust
            if (left + tooltipWidth > window.innerWidth - 20) {
                left = window.innerWidth - tooltipWidth - 20;
            }

            // Check if tooltip goes off the left edge and adjust
            if (left < 20) {
                left = 20;
            }

            // Check if tooltip goes off the bottom and adjust by positioning above the element
            if (top + 100 > window.innerHeight + window.scrollY) { // Estimate tooltip height ~100px
                top = rect.top + window.scrollY - 100 - 8; // Position above with space
            }

            // Safety check: if tooltip goes off screen at the top, reposition below
            if (top < window.scrollY) {
                top = rect.bottom + window.scrollY + 8;
            }

            // Set position and show tooltip
            contentTooltip.style.left = left + 'px';
            contentTooltip.style.top = top + 'px';
            contentTooltip.style.display = 'block'; // Must be visible before animation
        }

        // Function to initialize tooltips on truncated-text elements
        function setupTooltips() {
            // Remove any existing handlers to avoid duplications
            document.querySelectorAll('.truncated-text').forEach(element => {
                element.removeEventListener('click', handleTruncatedClick);
                // Add the new handler
                element.addEventListener('click', handleTruncatedClick);
            });
        }

        // Close the tooltip when clicking elsewhere
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.truncated-text')) {
                closeTooltip();
            }
        });

        // Update tooltip position during scrolling
        window.addEventListener('scroll', function() {
            requestAnimationFrame(updateTooltipPosition);
        }, {
            passive: true
        });

        // Also update during container scrolling
        document.querySelectorAll('.table-responsive').forEach(container => {
            container.addEventListener('scroll', function() {
                requestAnimationFrame(updateTooltipPosition);
            }, {
                passive: true
            });
        });

        // Also update during window resizing
        window.addEventListener('resize', function() {
            requestAnimationFrame(updateTooltipPosition);
        }, {
            passive: true
        });

        // Combination of two approaches in a single robust function
        function makeAllTruncatedElementsClickable() {
            let changesApplied = false;

            // 1. First check visually truncated elements (CSS overflow)
            // Select only data cells, explicitly excluding column headers
            // and other elements that should not trigger tooltips
            const allCells = document.querySelectorAll('.csv-cell:not(th):not(.table-column)');

            allCells.forEach(element => {
                // Skip elements that already have a child with truncated-text class
                if (element.querySelector('.truncated-text')) return;

                // Skip elements in the mapping table
                if (element.closest('.mapping-table')) return;

                try {
                    // Check if the text is visually truncated
                    const isOverflowing = element.scrollWidth > element.clientWidth + 1; // Add tolerance

                    // Alternative solution if scrollWidth is not reliable
                    const computedStyle = window.getComputedStyle(element);
                    const hasEllipsis = computedStyle.textOverflow === 'ellipsis';
                    const isFixedWidth = computedStyle.width !== 'auto' && !computedStyle.width.includes('%');
                    const hasOverflow = computedStyle.overflow === 'hidden' ||
                        computedStyle.overflowX === 'hidden' ||
                        computedStyle.whiteSpace === 'nowrap';

                    // Make sure there's actually text in the element and it's long enough
                    const hasSubstantialText = element.textContent.trim().length > 0;

                    // Check that the text is long enough to POTENTIALLY be truncated
                    const mayBeTruncated = element.textContent.trim().length > 10;

                    // Apply truncated class only if there's actually truncation
                    if (hasSubstantialText && mayBeTruncated &&
                        ((isOverflowing && hasOverflow) || (hasEllipsis && isFixedWidth))) {
                        const originalText = element.textContent.trim();

                        // Create a new span with the full text
                        const truncatedSpan = document.createElement('span');
                        truncatedSpan.className = 'truncated-text';
                        truncatedSpan.setAttribute('data-full-text', originalText);
                        truncatedSpan.textContent = originalText;

                        // Empty and refill the element
                        element.textContent = '';
                        element.appendChild(truncatedSpan);
                        changesApplied = true;
                    }
                } catch (e) {
                    console.error("Error detecting truncated text:", e);
                }
            });

            // 2. Then find all visible texts that contain "..." but only in data cells
            const textNodes = [];

            function findTextNodes(element) {
                if (element.nodeType === Node.TEXT_NODE) {
                    // Look only for nodes that aren't already children of truncated-text elements
                    // and that contain ellipses
                    if (element.textContent.includes('...') &&
                        element.parentNode &&
                        !element.parentNode.closest('.truncated-text') &&
                        !element.parentNode.closest('th') &&
                        !element.parentNode.closest('.table-column')) {
                        textNodes.push(element);
                    }
                } else {
                    for (let i = 0; i < element.childNodes.length; i++) {
                        findTextNodes(element.childNodes[i]);
                    }
                }
            }


            // Transform the found text nodes
            textNodes.forEach(textNode => {
                if (textNode.parentNode && !textNode.parentNode.classList.contains('truncated-text')) {
                    // Get all text before the ellipses and remove the ellipses
                    const displayedText = textNode.textContent.trim();
                    let fullText = '';

                    // Try to get the full text from the broader context if possible
                    const parentElement = textNode.parentNode.closest('.csv-cell');
                    if (parentElement && parentElement.getAttribute('title')) {
                        // If there's a title attribute, use it as the full text
                        fullText = parentElement.getAttribute('title');
                    } else {
                        // Otherwise reconstruct approximately
                        fullText = displayedText;
                        // If it's truncated text, add an indicator
                        if (displayedText.endsWith('...')) {
                            fullText += " {{ trans('backpack::import.full_text_unavailable') }}";
                        }
                    }

                    // Create a new span
                    const span = document.createElement('span');
                    span.className = 'truncated-text';
                    span.setAttribute('data-full-text', fullText);
                    span.textContent = displayedText;

                    // Replace the text node with the span
                    textNode.parentNode.replaceChild(span, textNode);
                    changesApplied = true;
                }
            });

            // In any case, reinitialize tooltips on all elements
            // (both existing ones and newly created ones)
            setupTooltips();
        }

        // Run immediately at startup to handle server-generated elements
        setupTooltips();

        // Then run the complete detection with a short delay to ensure
        // the page is fully loaded
        setTimeout(makeAllTruncatedElementsClickable, 300);

        // Run more times at increasing intervals to catch elements that might have been
        // loaded more slowly or after CSS animations
        setTimeout(makeAllTruncatedElementsClickable, 1000);
        setTimeout(makeAllTruncatedElementsClickable, 2000);

        // Run after every AJAX update
        $(document).ajaxComplete(function() {
            setTimeout(makeAllTruncatedElementsClickable, 300);
        });

        // Add a listener for DOM mutations to capture dynamically added elements
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    setTimeout(setupTooltips, 100);
                }
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        // Variable to track the original form
        const uniqueField = document.getElementById('unique_field');
        const importMappingForm = document.getElementById('import-mapping-form');
        
        // Handle uniqueField changes
        document.querySelector('#unique_field').addEventListener('change', function(e) {
            const uniqueFieldValue = e.target.value;
            
            // Handle showing/hiding behavior options
            const behaviorOptions = document.getElementById('import-behavior-options');
            if (uniqueFieldValue) {
                // Highlight this field in the table
                highlightUniqueField(uniqueFieldValue);
                
                // Show behavior options with animation
                behaviorOptions.style.display = 'block';
                behaviorOptions.style.opacity = '0';
                
                // Fade in the behavior options
                setTimeout(() => {
                    behaviorOptions.style.opacity = '1';
                    
                    // Scroll to and highlight the radio buttons with zoom effect
                    const radioContainer = document.querySelector('.import-behavior-radios');
                    
                    if (radioContainer) {
                        // Scroll to radio buttons
                        radioContainer.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center' 
                        });
                        
                        // Apply zoom effect to entire option containers
                        const radioOptions = radioContainer.querySelectorAll('.form-check');
                        radioOptions.forEach(option => {
                            option.style.transition = 'transform 0.5s ease';
                            option.style.transform = 'scale(1.009)';
                            
                            // Return to normal size after animation
                            setTimeout(() => {
                                option.style.transform = 'scale(1)';
                            }, 500);
                        });
                    }
                }, 100);
            } else {
                // Hide behavior options if no unique field is selected
                behaviorOptions.style.display = 'none';
                
                // Remove highlights
                document.querySelectorAll('.mapping-row').forEach(row => {
                    row.classList.remove('unique-field-selected');
                });
            }
        });

        // Function to highlight the unique field in the table
        function highlightUniqueField(fieldName) {
            // Remove existing highlights
            document.querySelectorAll('.mapping-row').forEach(row => {
                row.classList.remove('unique-field-selected');
            });
            
            // Add highlight to the selected field
            const selectedRow = document.querySelector(`.mapping-row[data-table-column="${fieldName}"]`);
            if (selectedRow) {
                selectedRow.classList.add('unique-field-selected');
            }
        }

        // Handle the unique_field field that has been moved outside the form
        if (uniqueField && importMappingForm) {
            // When the form is submitted, intercept the event and handle submission via AJAX
            importMappingForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent standard form submission

                // Add a hidden input for the value of the unique_field field
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'unique_field';
                hiddenInput.value = uniqueField.value;
                this.appendChild(hiddenInput);
                
                // Add the import behavior value to the form data
                const selectedBehavior = document.querySelector('input[name="import_behavior"]:checked');
                if (selectedBehavior) {
                    const behaviorInput = document.createElement('input');
                    behaviorInput.type = 'hidden';
                    behaviorInput.name = 'import_behavior';
                    behaviorInput.value = selectedBehavior.value;
                    this.appendChild(behaviorInput);
                }

                // Convert the reverse mapping to the original mapping expected by the backend
                const reverseMapping = {};
                document.querySelectorAll('.csv-field-select').forEach(select => {
                    const tableColumn = select.closest('.mapping-row').getAttribute('data-table-column');
                    const csvIndex = select.value;

                    if (csvIndex) {
                        reverseMapping[csvIndex] = tableColumn;
                    }
                });

                // Add hidden inputs for the original mapping
                Object.entries(reverseMapping).forEach(([csvIndex, tableColumn]) => {
                    const hiddenMapping = document.createElement('input');
                    hiddenMapping.type = 'hidden';
                    hiddenMapping.name = `column_mapping[${csvIndex}]`;
                    hiddenMapping.value = tableColumn;
                    this.appendChild(hiddenMapping);
                });

                // Collect all form data
                const formData = new FormData(this);

                // Show the progress panel
                $('#import-mapping-form').hide();
                $('#import-progress').show();
                
                // Hide other navigation elements during import
                $('.unique-field-card').hide();
                $('#auto-map-btn').hide();
                $('.card-header').hide();
                $('.import-card').find('h3').hide();
                $('.import-card .card-header').hide();

                // Submit data via AJAX
                $.ajax({
                    url: this.action,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    xhr: function() {
                        var xhr = new XMLHttpRequest();
                        
                        // Gestisci gli aggiornamenti di progresso in tempo reale
                        xhr.onprogress = function(e) {
                            if (e.currentTarget.responseText) {
                                try {
                                    // Dividi la risposta in oggetti JSON singoli
                                    const jsonStrings = e.currentTarget.responseText.replace(/}{/g, '}|{').split('|');
                                    
                                    // Prendiamo l'ultimo oggetto JSON completo
                                    let lastValidJson = null;
                                    
                                    // Tentiamo di analizzare ogni stringa JSON separata
                                    for (let i = 0; i < jsonStrings.length; i++) {
                                        try {
                                            const jsonObj = JSON.parse(jsonStrings[i]);
                                            lastValidJson = jsonObj;
                                            
                                            // Se questo è l'oggetto finale di successo o errore
                                            if (jsonObj.status === 'success' || jsonObj.status === 'error') {
                                                break;
                                            }
                                        } catch (jsonError) {
                                            // Ignora i JSON non validi o incompleti
                                            console.log('JSON incompleto o non valido:', jsonStrings[i]);
                                        }
                                    }
                                    
                                    if (lastValidJson) {
                                        // Aggiorna l'interfaccia con l'ultimo stato valido
                                        if (lastValidJson.status === 'processing') {
                                            // Aggiorna la barra di progresso
                                            const progressPercentage = lastValidJson.progress || 0;
                                            $('#progress-text').text(progressPercentage + '%');
                                            
                                            // Aggiorna le statistiche in tempo reale
                                            $('#total-rows').text(lastValidJson.total || 0);
                                            $('#created-rows').text(lastValidJson.created || 0);
                                            $('#updated-rows').text(lastValidJson.updated || 0);
                                            $('#skipped-rows').text(lastValidJson.skipped || 0);
                                            
                                            // Aggiorna i log di operazione se ci sono informazioni sull'operazione corrente
                                            if (lastValidJson.current_operation) {
                                                const op = lastValidJson.current_operation;
                                                
                                                // Aggiorniamo sempre il log per ogni operazione, anche se non viene inviato
                                                // un aggiornamento completo di progresso
                                                updateOperationLog(op);
                                            }
                                            
                                            // Se ci sono operazioni in batch, mostriamole tutte
                                            if (lastValidJson.operations && Array.isArray(lastValidJson.operations)) {
                                                lastValidJson.operations.forEach(op => {
                                                    updateOperationLog(op);
                                                });
                                            }
                                        } 
                                        else if (lastValidJson.status === 'success') {
                                            // L'importazione è completa, mostra i risultati
                                            // Hide import elements
                                            $('.unique-field-card').hide();
                                            $('#auto-map-btn').hide();
                                            $('.card-header').hide();
                                            $('.import-card').find('h3').hide();
                                            $('.import-card .card-header').hide();
                                            $('#import-mapping-form').hide();
                                            $('#import-progress').hide();
                                            $('#import-results').show();
                                            
                                            // Copiamo il log delle operazioni nella sezione dei risultati
                                            // Aggiungiamo una sezione per mostrare il log delle operazioni anche nella schermata dei risultati
                                            if (!$('#result-operation-log-container').length) {
                                                $('#result-stats').after(`
                                                    <div class="mt-4" id="result-operation-log-container">
                                                        <h5 class="text-primary mb-3">
                                                            <i class="la la-terminal me-2"></i>
                                                            {{ trans('backpack::import.operation_log_title') }}
                                                        </h5>
                                                        <div class="border rounded bg-white p-2" style="max-height: 200px; overflow-y: auto;">
                                                            <div id="result-operation-log" class="small">
                                                                ${$('#operation-log').html()}
                                                            </div>
                                                        </div>
                                                    </div>
                                                `);
                                            }
                                            
                                            // Aggiorna le statistiche finali
                                            $('#result-total-rows').text(lastValidJson.total || 0);
                                            $('#result-created-rows').text(lastValidJson.created || 0);
                                            $('#result-updated-rows').text(lastValidJson.updated || 0);
                                            $('#result-skipped-rows').text(lastValidJson.skipped || 0);
                                            $('#result-progress-text').text('100%');
                                            
                                            // Mostra il path del log se disponibile
                                            if (lastValidJson.logPath) {
                                                $('#log-path-text').text(lastValidJson.logPath);
                                                $('#log-path-alert').show();
                                            }
                                        }
                                        else if (lastValidJson.status === 'error') {
                                            // Mostra l'errore con dettagli tecnici
                                            showImportError(lastValidJson.message || "{{ trans('backpack::import.import_error') }}", lastValidJson);
                                        }
                                    }
                                } catch (e) {
                                    console.error('Errore durante l\'elaborazione degli aggiornamenti:', e);
                                }
                            }
                        };
                        
                        return xhr;
                    },
                    success: function(response) {
                        // Questa parte viene eseguita solo se la risposta è un JSON valido singolo
                        // Poiché stiamo usando l'approccio di streaming, gran parte della logica
                        // è stata spostata nell'handler "onprogress" qui sopra
                        console.log('Importazione completata con successo');
                        
                        // Assicuriamoci che il log delle operazioni sia copiato nei risultati
                        if (!$('#result-operation-log-container').length) {
                            $('#result-stats').after(`
                                <div class="mt-4" id="result-operation-log-container">
                                    <h5 class="text-primary mb-3">
                                        <i class="la la-terminal me-2"></i>
                                        {{ trans('backpack::import.operation_log_title') }}
                                    </h5>
                                    <div class="border rounded bg-white p-2" style="max-height: 200px; overflow-y: auto;">
                                        <div id="result-operation-log" class="small">
                                            ${$('#operation-log').html()}
                                        </div>
                                    </div>
                                </div>
                            `);
                        }
                    },
                    error: function(xhr) {
                        // Collect detailed error information
                        let errorDetails = {
                            status: xhr.status,
                            statusText: xhr.statusText,
                            responseText: xhr.responseText
                        };

                        try {
                            // Try to parse the JSON response
                            if (xhr.responseText) {
                                const jsonStrings = xhr.responseText.replace(/}{/g, '}|{').split('|');
                                let lastValidJson = null;
                                
                                // Analizza l'ultimo JSON valido
                                for (let i = 0; i < jsonStrings.length; i++) {
                                    try {
                                        const jsonObj = JSON.parse(jsonStrings[i]);
                                        lastValidJson = jsonObj;
                                    } catch (e) {
                                        // Ignora gli oggetti JSON non validi
                                    }
                                }
                                
                                if (lastValidJson) {
                                    errorDetails.parsedResponse = lastValidJson;
                                    
                                    if (lastValidJson.status === 'error') {
                                        // Se abbiamo già un errore strutturato, mostralo direttamente
                                        showImportError(lastValidJson.message || "{{ trans('backpack::import.import_error') }}", lastValidJson);
                                        return;
                                    }
                                }
                            }
                        } catch (e) {
                            // If it's not JSON, use the raw text
                            errorDetails.parseError = e.message;
                        }

                        // Mostra l'errore con i dettagli disponibili
                        showImportError(xhr.responseJSON?.message || "{{ trans('backpack::import.import_error') }}", errorDetails);
                    }
                });
            });
        }

        // Initial setting of select fields to exact matching fields (if they exist)
        function setInitialExactMatches() {
            // Get all mapping rows
            const tableColumns = Array.from(document.querySelectorAll('.mapping-row')).map(row => {
                return {
                    element: row,
                    column: row.getAttribute('data-table-column'),
                    select: row.querySelector('.csv-field-select'),
                    indicator: row.querySelector('.mapping-type-indicator'),
                    isRequired: row.querySelector('.csv-field-select.required-field-select') !== null
                };
            });

            // Get all CSV headers
            const csvHeaders = Array.from(document.querySelectorAll('.csv-option')).map(option => {
                return {
                    index: option.value,
                    header: option.getAttribute('data-csv-header')
                };
            });

            // For each table column, look for an exact match
            tableColumns.forEach(tableCol => {
                const exactMatch = csvHeaders.find(csv =>
                    csv.header.toLowerCase() === tableCol.column.toLowerCase());

                // If there's an exact match, set the select value
                if (exactMatch) {
                    tableCol.select.value = exactMatch.index;
                    tableCol.select.classList.add('mapping-match-exact');
                    
                    // If this is a required field, make sure to remove the invalid state
                    if (tableCol.isRequired) {
                        tableCol.select.classList.remove('is-invalid');
                        tableCol.element.classList.remove('has-invalid-field');
                    }

                    // Also add the visual indicator
                    if (tableCol.indicator) {
                        tableCol.indicator.innerHTML = "<i class='la la-check'></i>{{ trans('backpack::import.exact_match') }}";
                        tableCol.indicator.className = 'mapping-type-indicator mapping-type-exact';
                        tableCol.indicator.classList.add('show');

                        // Hide after 3 seconds but keep visible on hover
                        setTimeout(() => {
                            tableCol.indicator.classList.add('hide-after-delay');
                            tableCol.indicator.classList.remove('show');
                        }, 3000);
                    }
                } else if (tableCol.isRequired) {
                    // If it's a required field with no match, mark it as invalid
                    tableCol.select.classList.add('is-invalid');
                    tableCol.element.classList.add('has-invalid-field');
                }
            });
            
            // After setting all matches, run validation to update button state
            validateRequiredFields();
        }

        // Run the function at page startup
        setTimeout(setInitialExactMatches, 500);

        // Highlight the unique field selected in the mapping table
        function highlightUniqueFieldInTable() {
            // Remove existing highlighting
            document.querySelectorAll('.mapping-row.unique-field-row').forEach(row => {
                row.classList.remove('unique-field-row');
            });
            
            // Rimuoviamo la classe highlighted-field da tutti gli elementi prima
            document.querySelectorAll('.fw-medium.highlighted-field').forEach(el => {
                el.classList.remove('highlighted-field');
            });

            // Get the selected value in the unique_field field
            const uniqueFieldSelect = document.getElementById('unique_field');
            if (!uniqueFieldSelect || !uniqueFieldSelect.value) return;

            // Find the corresponding row in the mapping table
            const mappingRow = document.querySelector(`.mapping-row[data-table-column="${uniqueFieldSelect.value}"]`);
            if (mappingRow) {
                // Highlight the row
                mappingRow.classList.add('unique-field-row');
                
                // Troviamo l'elemento che contiene il nome della colonna
                const columnNameEl = mappingRow.querySelector('.table-column .fw-medium');
                if (columnNameEl) {
                    // Aggiungiamo la classe highlighted-field che mostra l'icona chiave e l'animazione pulse
                    columnNameEl.classList.add('highlighted-field');
                }
                
                // Effettuiamo lo scroll alle radio button di import behavior
                const behaviorOptions = document.getElementById('import-behavior-options');
                if (behaviorOptions) {
                    // Facciamo lo scroll con un leggero ritardo per lasciare il tempo di applicare gli stili
                    setTimeout(() => {
                        behaviorOptions.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 100);
                }
            }
        }

        // Listen for changes in the unique_field select
        const uniqueFieldSelect = document.getElementById('unique_field');
        if (uniqueFieldSelect) {
            uniqueFieldSelect.addEventListener('change', function() {
                // Aggiorniamo l'evidenziazione della riga nella tabella
                highlightUniqueFieldInTable();
            });

            // Also check at the beginning if there is already a selected value
            setTimeout(highlightUniqueFieldInTable, 500);
        }
        
        // Function to monitor import status
        function monitorImportProgress(importId) {
            const statusUrl = "{{ url($crud_route.'/import-csv/status') }}";

            // Function to update the interface with current status
            function updateProgressUI(data) {
                // Log complete data object for debugging
                console.log('Progress data received:', data);
                
                // Update progress bar with real percentage
                const progressPercentage = Math.round((data.processed / data.total) * 100);
                $('#progress-text').text(progressPercentage + '%');

                // Update statistics
                $('#total-rows').text(data.processed);
                $('#created-rows').text(data.created || 0);
                $('#updated-rows').text(data.updated || 0);
                $('#skipped-rows').text(data.skipped || 0);

                // If import is completed
                if (data.status === 'completed') {
                    // Hide import elements
                    $('.unique-field-card').hide();
                    $('#auto-map-btn').hide(); // Correct: use ID instead of class
                    $('.card-header').hide(); // Hide the card header completely
                    $('.import-card').find('h3').hide(); // Hide the "Column Mapping" title
                    $('.import-card .card-header').hide(); // Hide the card header
                    $('#import-mapping-form').hide();
                    $('#import-progress').hide();
                    $('#import-results').show();

                    // Update final statistics
                    $('#result-total-rows').text(data.processed || 0);
                    $('#result-created-rows').text(data.created || 0);
                    $('#result-updated-rows').text(data.updated || 0);
                    $('#result-skipped-rows').text(data.skipped || 0);
                    $('#result-progress-text').text('100%');
                    
                    // Mostra il path del log se disponibile
                    if (data.logPath) {
                        $('#log-path-text').text(data.logPath);
                        $('#log-path-alert').show();
                    }

                    // Try all possible backup filename keys and log everything
                    console.log('Complete data object:', data);
                    
                    // Update the backup message to show only directory, not filename
                    $('#backup-filename').closest('.alert').find('span').html(
                        '{{ trans("backpack::import.backup_created") }} <code>/storage/backups/csv-imports/</code>'
                    );
                    
                    // Make backup container visible with a highlight effect
                    $('#backup-filename').closest('.alert').addClass('alert-highlight');
                    setTimeout(() => {
                        $('#backup-filename').closest('.alert').removeClass('alert-highlight');
                    }, 2000);

                    return true; // Import completed
                }

                // If there was an error
                if (data.status === 'error') {
                    showImportError(data.message || "{{ trans('backpack::import.import_error') }}", data);
                    return true; // Stop polling
                }

                // If the import is already completed directly
                if (data.status === 'success') {
                    // Hide import elements
                    $('.unique-field-card').hide();
                    $('#auto-map-btn').hide(); // Correct: use ID instead of class
                    $('.card-header').hide(); // Hide the card header completely
                    $('.import-card').find('h3').hide(); // Hide the "Column Mapping" title
                    $('.import-card .card-header').hide(); // Hide the card header
                    $('#import-mapping-form').hide();
                    $('#import-progress').hide();
                    $('#import-results').show();

                    // Update final statistics
                    $('#result-total-rows').text(data.total || 0);
                    $('#result-created-rows').text(data.created || 0);
                    $('#result-updated-rows').text(data.updated || 0);
                    $('#result-skipped-rows').text(data.skipped || 0);
                    $('#result-progress-text').text('100%');

                    // Try all possible backup filename keys and log everything
                    console.log('Complete data object:', data);
                    
                    // Update the backup message to show only directory, not filename
                    $('#backup-filename').closest('.alert').find('span').html(
                        '{{ trans("backpack::import.backup_created") }} <code>/storage/backups/csv-imports/</code>'
                    );
                    
                    // Make backup container visible with a highlight effect
                    $('#backup-filename').closest('.alert').addClass('alert-highlight');
                    setTimeout(() => {
                        $('#backup-filename').closest('.alert').removeClass('alert-highlight');
                    }, 2000);

                    return true; // Import completed
                }

                return false; // Continue polling
            }

            // Recursive function for status polling
            function pollStatus() {
                $.ajax({
                    url: statusUrl,
                    method: 'GET',
                    data: {
                        import_id: importId
                    },
                    dataType: 'json',
                    success: function(data) {
                        const completed = updateProgressUI(data);
                        if (!completed) {
                            // Continue polling every 2 seconds
                            setTimeout(pollStatus, 2000);
                        }
                    },
                    error: function(xhr) {
                        // Collect detailed information about the monitoring error
                        let monitorErrorDetails = {
                            status: xhr.status,
                            statusText: xhr.statusText,
                            responseText: xhr.responseText,
                            url: statusUrl
                        };

                        // Show the error with technical details
                        showImportError("{{ trans('backpack::import.import_error') }}", monitorErrorDetails);
                    }
                });
            }

            // Start polling
            pollStatus();
        }

        // Function to show import errors
        function showImportError(message, details = null) {
            $('#import-progress').hide();
            $('#import-mapping-form').hide();
            $('#import-error').show();
            
            // Extract message from details if it's an object with message property
            let errorMessage = message;
            if (details && typeof details === 'object') {
                // If details has a message and message is the same, use details.message
                if (details.message && details.message === message) {
                    errorMessage = details.message;
                } else if (details.parsedResponse && details.parsedResponse.message) {
                    errorMessage = details.parsedResponse.message;
                }
            }
            
            // Always show error details box with the message
            $('#error-details').show();
            
            // Build log text starting with the error message
            let logParts = [];
            
            // Add error message first
            logParts.push('=== MESSAGGIO ERRORE ===');
            logParts.push(errorMessage);
            logParts.push('');
            
            // Add technical details if available
            if (details) {
                let technicalDetails = {};
                if (typeof details === 'object') {
                    Object.keys(details).forEach(key => {
                        // Exclude message to avoid duplication
                        if (key !== 'message') {
                            if (key === 'parsedResponse' && details[key] && details[key].message) {
                                // Create a copy without the message
                                let parsedCopy = Object.assign({}, details[key]);
                                delete parsedCopy.message;
                                if (Object.keys(parsedCopy).length > 0) {
                                    technicalDetails[key] = parsedCopy;
                                }
                            } else {
                                technicalDetails[key] = details[key];
                            }
                        }
                    });
                } else {
                    technicalDetails = details;
                }
                
                if (Object.keys(technicalDetails).length > 0) {
                    logParts.push('=== DETTAGLI TECNICI ===');
                    logParts.push(typeof technicalDetails === 'object' ? JSON.stringify(technicalDetails, null, 2) : technicalDetails.toString());
                }
            }
            
            $('#error-log').text(logParts.join('\n'));
            
            // Show log path if available
            if (details && details.logPath) {
                $('#error-log-path-text').text(details.logPath);
                $('#error-log-path').show();
            } else if (details && details.parsedResponse && details.parsedResponse.logPath) {
                $('#error-log-path-text').text(details.parsedResponse.logPath);
                $('#error-log-path').show();
            } else {
                $('#error-log-path').hide();
            }
        }


        // Re-run the truncated text detection when a collapse type element is opened
        document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(collapseButton => {
            collapseButton.addEventListener('shown.bs.collapse', function() {
                setTimeout(makeAllTruncatedElementsClickable, 300);
            });
        });
        
        // Auto-Map functionality
        const autoMapBtn = document.getElementById('auto-map-btn');
        const autoMapOverlay = document.getElementById('auto-map-overlay');

        autoMapBtn.addEventListener('click', function() {
            // Show overlay with animation
            autoMapOverlay.style.display = 'flex';

            // Remove any previous mapping classes
            document.querySelectorAll('.field-mapping-select').forEach(select => {
                select.classList.remove('mapping-match-exact', 'mapping-match-similar', 'mapping-match-none');
            });

            // Collect all necessary data
            const tableColumns = Array.from(document.querySelectorAll('.mapping-row')).map(row => {
                return {
                    element: row,
                    column: row.getAttribute('data-table-column'),
                    select: row.querySelector('.csv-field-select')
                };
            });

            const csvHeaders = Array.from(document.querySelectorAll('.csv-option')).map(option => {
                return {
                    index: option.value,
                    header: option.getAttribute('data-csv-header'),
                    element: option
                };
            });

            // Timeout to simulate processing
            setTimeout(() => {
                // Track CSV columns already mapped with exact matches
                const exactlyMappedCsvIndices = new Set();
                const mappings = [];

                // First pass: find all exact matches
                tableColumns.forEach(tableCol => {
                    // Step 1: Look for exact matches (case insensitive)
                    const exactMatches = csvHeaders.filter(csv =>
                        csv.header.toLowerCase() === tableCol.column.toLowerCase());

                    // If there is an exact match
                    if (exactMatches.length > 0) {
                        const matchIndex = exactMatches[0].index;
                        tableCol.select.value = matchIndex;
                        mappings.push({
                            element: tableCol.select,
                            type: 'exact'
                        });
                        
                        // Check if this is a required field
                        const isRequired = tableCol.select.classList.contains('required-field-select');
                        if (isRequired) {
                            tableCol.select.classList.remove('is-invalid');
                            const row = tableCol.select.closest('.mapping-row');
                            if (row) {
                                row.classList.remove('has-invalid-field');
                            }
                        }
                        
                        // Add this CSV column to the exactly mapped ones
                        exactlyMappedCsvIndices.add(matchIndex);
                    }
                });

                // Second pass: look for similar matches only for columns not yet mapped
                tableColumns.forEach(tableCol => {
                    // Skip if this table column already has an exact match
                    if (mappings.some(m => m.element === tableCol.select)) {
                        return;
                    }

                    // Step 2: Look for matches with similarity score
                    const similarityScores = csvHeaders
                        .filter(csv => !exactlyMappedCsvIndices.has(csv.index) || 
                            csv.header.toLowerCase() === tableCol.column.toLowerCase())
                        .map(csv => {
                            // Normalize column names
                            const tableColName = tableCol.column.toLowerCase();
                            const csvHeaderName = csv.header.toLowerCase();
                            
                            // Translation pairs (both ways)
                            const translationPairs = {
                                'abstract': ['sommario', 'riassunto', 'estratto'],
                                'author': ['autore', 'autori', 'scrittore'],
                                'title': ['titolo'],
                                'subtitle': ['sottotitolo'],
                                'description': ['descrizione'],
                                'italian': ['italiano', 'ita', 'it'],
                                'english': ['inglese', 'eng', 'en'],
                                'name': ['nome'],
                                'category': ['categoria'],
                                'tag': ['tag', 'etichetta'],
                                'image': ['immagine', 'img'],
                                'content': ['contenuto'],
                                'body': ['corpo', 'testo'],
                                'date': ['data'],
                                'status': ['stato'],
                                'active': ['attivo'],
                                'published': ['pubblicato'],
                                'slug': ['slug', 'permalink'],
                                'url': ['url', 'link'],
                                'meta': ['meta'],
                            };
                            
                            // Create object for scoring
                            const score = {
                                stringDistance: 0,
                                translation: 0,
                                partMatch: 0,
                                total: 0
                            };
                            
                            // Calculate string similarity (Levenshtein)
                            function levenshteinDistance(a, b) {
                                if (!a || !b) return Math.max(a ? a.length : 0, b ? b.length : 0);
                                
                                const matrix = [];
                                for (let i = 0; i <= b.length; i++) matrix[i] = [i];
                                for (let j = 0; j <= a.length; j++) matrix[0][j] = j;
                                
                                for (let i = 1; i <= b.length; i++) {
                                    for (let j = 1; j <= a.length; j++) {
                                        const cost = a[j-1] === b[i-1] ? 0 : 1;
                                        matrix[i][j] = Math.min(
                                            matrix[i-1][j] + 1,
                                            matrix[i][j-1] + 1,
                                            matrix[i-1][j-1] + cost
                                        );
                                    }
                                }
                                
                                return matrix[b.length][a.length];
                            }
                            
                            function similarity(a, b) {
                                if (!a || !b) return 0;
                                const maxLen = Math.max(a.length, b.length);
                                if (maxLen === 0) return 100;
                                return (1 - levenshteinDistance(a, b) / maxLen) * 100;
                            }
                            
                            // Check overall string similarity for typos
                            const fullSimilarity = similarity(tableColName, csvHeaderName);
                            if (fullSimilarity > 85) score.stringDistance = 40;
                            else if (fullSimilarity > 75) score.stringDistance = 25;
                            
                            // Split into parts
                            const tableParts = tableColName.split(/[_\s-]|(?=[A-Z])/).filter(p => p.length >= 2);
                            const csvParts = csvHeaderName.split(/[_\s-]|(?=[A-Z])/).filter(p => p.length >= 2);
                            
                            // Check for translations
                            for (const tablePart of tableParts) {
                                for (const [eng, translations] of Object.entries(translationPairs)) {
                                    // Check if table part is English and CSV has translation
                                    if (tablePart === eng) {
                                        for (const csvPart of csvParts) {
                                            if (translations.includes(csvPart)) {
                                                score.translation += 15;
                                                break;
                                            }
                                        }
                                    }
                                    
                                    // Check if table part is translation and CSV has English
                                    if (translations.includes(tablePart)) {
                                        for (const csvPart of csvParts) {
                                            if (csvPart === eng) {
                                                score.translation += 15;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                            
                            // Check part matching
                            for (const tablePart of tableParts) {
                                // Exact part match
                                if (csvParts.includes(tablePart)) {
                                    score.partMatch += 5;
                                    continue;
                                }
                                
                                // Part similarity
                                for (const csvPart of csvParts) {
                                    const partSimilarity = similarity(tablePart, csvPart);
                                    if (partSimilarity > 85) score.partMatch += 4;
                                    else if (partSimilarity > 75) score.partMatch += 2;
                                }
                            }
                            
                            // Calculate total score
                            score.total = score.stringDistance + score.translation + score.partMatch;
                            
                            return {
                                csv,
                                score: score.total
                            };
                        });

                    // Sort by similarity score
                    similarityScores.sort((a, b) => b.score - a.score);

                    // If there's at least one match with positive score
                    if (similarityScores.length > 0 && similarityScores[0].score > 0) {
                        tableCol.select.value = similarityScores[0].csv.index;
                        mappings.push({
                            element: tableCol.select,
                            type: 'similar'
                        });
                        return;
                    }

                    // No match found
                    mappings.push({
                        element: tableCol.select,
                        type: 'none'
                    });
                });

                // Hide the overlay after 2 seconds and then apply classes for animations
                setTimeout(() => {
                    autoMapOverlay.style.display = 'none';

                    // Apply the classes for animations after the overlay is gone
                    setTimeout(() => {
                        mappings.forEach(mapping => {
                            // Find the indicator associated with this select
                            const row = mapping.element.closest('.mapping-row');
                            const column = row.getAttribute('data-table-column');
                            const indicator = row.querySelector('.mapping-type-indicator');

                            if (mapping.type === 'exact') {
                                mapping.element.classList.add('mapping-match-exact');
                                if (indicator) {
                                    indicator.innerHTML = "<i class='la la-check'></i> {{ trans('backpack::import.exact_match') }}";
                                    indicator.className = 'mapping-type-indicator mapping-type-exact';
                                    indicator.classList.add('show');

                                    // Hide after 3 seconds but keep visible on hover
                                    setTimeout(() => {
                                        indicator.classList.add('hide-after-delay');
                                        indicator.classList.remove('show');
                                    }, 3000);
                                }
                            } else if (mapping.type === 'similar') {
                                mapping.element.classList.add('mapping-match-similar');
                                if (indicator) {
                                    indicator.innerHTML = "<i class='la la-question'></i> {{ trans('backpack::import.similar_match') }}";
                                    indicator.className = 'mapping-type-indicator mapping-type-similar';
                                    indicator.classList.add('show');

                                    // Hide after 3 seconds but keep visible on hover
                                    setTimeout(() => {
                                        indicator.classList.add('hide-after-delay');
                                        indicator.classList.remove('show');
                                    }, 3000);
                                }
                            } else {
                                mapping.element.classList.add('mapping-match-none');
                                if (indicator) {
                                    indicator.innerHTML = "<i class='la la-times'></i> {{ trans('backpack::import.no_match') }}";
                                    indicator.className = 'mapping-type-indicator mapping-type-none';
                                    indicator.classList.add('show');

                                    // Hide after 3 seconds but keep visible on hover
                                    setTimeout(() => {
                                        indicator.classList.add('hide-after-delay');
                                        indicator.classList.remove('show');
                                    }, 3000);
                                }
                            }
                        });
                        
                        // Reinitialize tooltips for truncated elements
                        setTimeout(makeAllTruncatedElementsClickable, 100);
                        
                        // Validate all fields to update button state
                        validateRequiredFields();
                    }, 50); // A small delay to make sure the overlay is gone
                }, 2000);
            }, 1000);
        });

        // Add event handling for change on select boxes
        document.querySelectorAll('.csv-field-select').forEach(select => {
            select.addEventListener('change', function() {
                // Remove all style classes for the match
                this.classList.remove('mapping-match-exact', 'mapping-match-similar', 'mapping-match-none');

                // Hide the matching box and mark as user modified
                const row = this.closest('.mapping-row');
                if (row) {
                    const indicator = row.querySelector('.mapping-type-indicator');
                    if (indicator) {
                        indicator.classList.remove('show');
                        indicator.classList.add('hide-after-delay', 'user-modified');
                    }
                }
            });
        });

        // Function to validate required fields and update the status of the submit button
        function validateRequiredFields() {
            let requiredFields = document.querySelectorAll('.required-field-select');
            let missingRequiredFields = [];
            let isValid = true;
            
            requiredFields.forEach(function(field) {
                const row = field.closest('.mapping-row');
                
                // Field is invalid only when "Do not import" is selected (empty value)
                if (!field.value) {
                    field.classList.add('is-invalid');
                    row.classList.add('has-invalid-field');
                    
                    // Get the field name from the mapping row
                    let fieldName = row.querySelector('.table-column .fw-medium').textContent.trim();
                    missingRequiredFields.push(fieldName);
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                    row.classList.remove('has-invalid-field');
                }
            });
            
            // Update the submit button status
            const submitButton = document.getElementById('start-import');
            if (!isValid) {
                submitButton.classList.add('disabled-import-btn');
                submitButton.disabled = true;
                // Add a tooltip to the button
                submitButton.setAttribute('data-bs-toggle', 'tooltip');
                submitButton.setAttribute('data-bs-placement', 'top');
                submitButton.setAttribute('title', "{{ trans('backpack::import.required_fields_tooltip') }}");
            } else {
                submitButton.classList.remove('disabled-import-btn');
                submitButton.disabled = false;
                submitButton.removeAttribute('data-bs-toggle');
                submitButton.removeAttribute('title');
            }
            
            return { isValid, missingRequiredFields };
        }
        
        // Add event listener to each required field to validate on change
        document.querySelectorAll('.required-field-select').forEach(select => {
            select.addEventListener('change', function() {
                const row = this.closest('.mapping-row');
                
                // Remove the invalid styling when a valid option is selected
                if (this.value) {
                    this.classList.remove('is-invalid');
                    row.classList.remove('has-invalid-field');
                } else {
                    this.classList.add('is-invalid');
                    row.classList.add('has-invalid-field');
                }
                
                validateRequiredFields();
            });
            
            // Trigger the change event to initialize the validation state
            select.dispatchEvent(new Event('change'));
        });
        
        // Verify required fields before form submission
        document.getElementById('import-mapping-form').addEventListener('submit', function(e) {
            const validation = validateRequiredFields();
            
            if (!validation.isValid) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: "{{ trans('backpack::import.required_fields_missing') }}",
                    html: "{{ trans('backpack::import.required_fields_message') }}<br><br><ul><li>" + validation.missingRequiredFields.join('</li><li>') + '</li></ul>',
                    confirmButtonText: "{{ trans('backpack::crud.ok') }}",
                    confirmButtonColor: '#d33'
                });
                return false;
            }
            
            return true;
        });

        // Initialize tooltips on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Funzione per aggiornare il log delle operazioni
        function updateOperationLog(op) {
            // Crea un ID univoco per questa operazione in base a tipo e ID
            const operationUniqueId = op.action + '_' + (op.id || '') + '_row' + op.row;
            
            // Verifica se questa operazione è già stata registrata
            if (processedOperationIds.has(operationUniqueId)) {
                return; // Ignora operazioni duplicate
            }
            
            // Registra questa operazione come elaborata
            processedOperationIds.add(operationUniqueId);
            
            let logMessage = '';
            let operationTitle = '';
            let operationDetails = '';
            let opIcon = 'la-info-circle';
            let logClass = '';
            let iconClass = 'text-primary';
            
            // Costruisci messaggio per il log compatto
            logMessage = `<div class="log-entry py-1 border-bottom" data-op-id="${operationUniqueId}">
                <span class="text-muted me-1">${new Date().toLocaleTimeString('it-IT', {hour: '2-digit', minute:'2-digit', second:'2-digit'})}</span>`;
            
            // Imposta titolo, dettagli e icona in base all'azione
            if (op.action === 'insert') {
                logClass = 'text-success';
                iconClass = 'text-success';
                opIcon = 'la-plus-circle';
                operationTitle = "{{ trans('backpack::import.record_inserted') }}".replace(':primary_key', op.primary_key).replace(':primary_key_value', op.primary_key_value);
                
                if (op.field && op.value) {
                    operationDetails = "{{ trans('backpack::import.processing_value') }}"
                        .replace(':value', op.value)
                        .replace(':field', op.field);
                }
                
                logMessage += `<span class="${logClass}"><i class="la la-plus-circle me-1"></i> ${operationTitle}</span>`;
            } 
            else if (op.action === 'update') {
                logClass = 'text-primary';
                iconClass = 'text-primary';
                opIcon = 'la-sync';
                operationTitle = "{{ trans('backpack::import.record_updated') }}".replace(':primary_key', op.primary_key).replace(':primary_key_value', op.primary_key_value);
                
                if (op.field && op.value) {
                    operationDetails = "{{ trans('backpack::import.processing_value') }}"
                        .replace(':value', op.value)
                        .replace(':field', op.field);
                }
                
                logMessage += `<span class="${logClass}"><i class="la la-sync me-1"></i> ${operationTitle}</span>`;
            } 
            else if (op.action === 'skip') {
                logClass = 'text-warning';
                iconClass = 'text-warning';
                opIcon = 'la-ban';
                operationTitle = "{{ trans('backpack::import.record_skipped') }}";
                
                if (op.reason === 'update_only_mode') {
                    operationDetails = "{{ trans('backpack::import.update_only_reason') }}";
                }
                
                logMessage += `<span class="${logClass}"><i class="la la-ban me-1"></i> ${operationTitle}</span>`;
            }
            
            // Aggiungi informazioni sulla riga al messaggio di log
            const rowText = "{{ trans('backpack::import.row_processing') }}".replace(':row', op.row);
            logMessage += ` <span class="text-muted">- ${rowText}</span>`;
            
            // Aggiungi messaggio se la password è stata auto-generata
            if (op.password_auto_generated) {
                logMessage += ` <span class="text-success"><i class="la la-key me-1"></i> Password auto-generata e hashata</span>`;
            }
            
            logMessage += `</div>`;
            
            // Aggiorna il box dell'operazione corrente
            $('#current-op-icon').removeClass().addClass(`la ${opIcon} fs-2 ${iconClass}`);
            $('#current-op-title').html(operationTitle);
            $('#current-op-details').html(operationDetails);
            
            // Aggiungi la voce di log e scorri automaticamente in basso
            const logElement = $('#operation-log');
            logElement.prepend(logMessage); // Prepend per avere le voci più recenti in alto
            
            // Aggiungi effetto pulse
            const currentOpBox = $('#current-operation-box');
            currentOpBox.removeClass('pulse-animation');
            void currentOpBox[0].offsetWidth; // Trigger reflow
            currentOpBox.addClass('pulse-animation');
        }
    });
</script>
@endpush