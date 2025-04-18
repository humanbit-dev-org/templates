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
        <!-- CSV File Preview -->
        <div class="accordion mb-3" id="csvPreviewAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#csvPreviewCollapse" aria-expanded="false" aria-controls="csvPreviewCollapse">
                        <i class="la la-table me-1"></i> {{ trans('backpack::import.csv_preview') }}
                    </button>
                </h2>
                <div id="csvPreviewCollapse" class="accordion-collapse collapse" data-bs-parent="#csvPreviewAccordion">
                    <div class="accordion-body p-0">
                        <div class="csv-preview-container" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        @foreach($csvHeaders as $header)
                                        <th class="csv-header">
                                            @if(Str::length($header) > 30 || Str::endsWith(Str::limit($header, 30), '...'))
                                            <span class="truncated-text" data-full-text="{{ $header }}">{{ Str::limit($header, 30) }}</span>
                                            @else
                                            {{ $header }}
                                            @endif
                                        </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @if(isset($csvPreview) && count($csvPreview) > 0)
                                    @foreach($csvPreview as $row)
                                    <tr>
                                        @foreach($row as $value)
                                        <td class="csv-cell">
                                            @if(Str::length($value) > 50 || Str::endsWith(Str::limit($value, 50), '...'))
                                            <span class="truncated-text" data-full-text="{{ $value }}">{{ Str::limit($value, 50) }}</span>
                                            @else
                                            {{ $value }}
                                            @endif
                                        </td>
                                        @endforeach
                                    </tr>
                                    @endforeach
                                    @else
                                    <tr>
                                        <td colspan="{{ count($csvHeaders) }}" class="text-center py-3 text-muted">
                                            <i class="la la-info-circle"></i> {{ trans('backpack::import.no_preview_data') }}
                                        </td>
                                    </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center position-relative">
                                            <span class="mapping-type-indicator" data-column="{{ $column }}"></span>
                                            <select name="column_mapping_reverse[{{ $column }}]" class="form-select field-mapping-select csv-field-select">
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
                        <h4 class="text-primary mb-3">
                            <i class="la la-sync fa-spin me-2"></i>
                            {{ trans('backpack::import.import_in_progress') }}
                        </h4>
                        <div class="progress mb-3">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                        </div>

                        <div class="mt-3">
                            <div class="progress-percentage mb-3 text-center">
                                <span id="progress-text" class="badge bg-primary fs-5">0%</span>
                            </div>
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
                    </div>
                </div>

                <div id="import-results" style="display: none;">
                    <div class="progress-container p-4 border rounded bg-light">
                        <h4 class="text-success mb-3">
                            <i class="la la-check-circle me-2"></i>
                            {{ trans('backpack::import.import_completed') }}
                        </h4>

                        <!-- 100% Progress Bar -->
                        <div class="progress mb-4">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>

                        <div class="mt-3 mb-3 text-center">
                            <span class="badge bg-success fs-5">100%</span>
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
                                <code>/storage/app/import-backups/</code>
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
                        <h4 class="alert-heading">
                            <i class="la la-exclamation-circle me-2"></i>
                            {{ trans('backpack::import.import_error') }}
                        </h4>
                        <p id="error-message" class="mb-3"></p>
                        <div id="error-details" class="border rounded bg-light p-3 mb-3" style="display: none;">
                            <h6 class="text-danger"><i class="la la-bug"></i> Technical Error Details:</h6>
                            <pre id="error-log" class="small text-pre-wrap bg-dark text-light p-2 rounded" style="max-height: 200px; overflow-y: auto;"></pre>
                        </div>
                        <p class="mb-0">
                            {{ trans('backpack::import.backup_created') }}
                            <code>/storage/app/import-backups/</code>
                        </p>
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
    </div>
    <div class="auto-map-animation">
        <i class="la la-magic fa-spin"></i>
        <span>{{ trans('backpack::import.mapping_in_progress') }}</span>
    </div>
</div>
@endsection

@push('after_scripts')
<style>
    /* Style for highlighting the backup alert */
    .alert-highlight {
        animation: highlight-pulse 1.5s ease-in-out;
    }
    
    @keyframes highlight-pulse {
        0% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.5); }
        70% { box-shadow: 0 0 0 10px rgba(0, 123, 255, 0); }
        100% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0); }
    }
    
    /* Style improvements for backup filename */
    #backup-filename, #error-backup-filename {
        font-weight: bold;
        background-color: rgba(0, 0, 0, 0.05);
        padding: 2px 5px;
        border-radius: 3px;
        display: inline-block;
    }
</style>
<script>
    $(document).ready(function() {
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
        document.querySelectorAll('.csv-preview-container, .table-responsive').forEach(container => {
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

                // Skip elements in the mapping table (not in the CSV preview)
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

            // Search in relevant containers ONLY IN THE CSV PREVIEW
            document.querySelectorAll('.csv-preview-container .csv-cell').forEach(container => {
                if (!container.querySelector('.truncated-text')) {
                    findTextNodes(container);
                }
            });

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

        // Initial setting of select fields to exact matching fields (if they exist)
        function setInitialExactMatches() {
            // Get all mapping rows
            const tableColumns = Array.from(document.querySelectorAll('.mapping-row')).map(row => {
                return {
                    element: row,
                    column: row.getAttribute('data-table-column'),
                    select: row.querySelector('.csv-field-select'),
                    indicator: row.querySelector('.mapping-type-indicator')
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
                }
            });
        }

        // Run the function at page startup
        setTimeout(setInitialExactMatches, 500);

        // Highlight the corresponding field when a value is selected in the unique_field field
        if (uniqueField) {
            uniqueField.addEventListener('change', function() {
                // Add focus effect to the unique_field field
                this.classList.add('unique-field-focus');
                setTimeout(() => {
                    this.classList.remove('unique-field-focus');
                }, 2000);

                // Remove any previous highlights
                document.querySelectorAll('.fw-medium.highlighted-field').forEach(element => {
                    element.classList.remove('highlighted-field');
                });

                // Get the selected value
                const selectedField = this.value;

                if (selectedField) {
                    // Find the row in the table that corresponds to the selected field
                    const matchingRow = document.querySelector(`.mapping-row[data-table-column="${selectedField}"]`);
                    if (matchingRow) {
                        // Highlight only the column name text
                        const textElement = matchingRow.querySelector('.table-column .fw-medium');
                        if (textElement) {
                            textElement.classList.add('highlighted-field');
                            // Scroll the view to show the highlighted text
                            textElement.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                        }
                    }
                }
            });
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
                $('#csvPreviewAccordion').hide();
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
                    success: function(response) {
                        // If the response is a success, start monitoring the import status
                        if (response.success) {
                            monitorImportProgress(response.import_id);
                        } else if (response.status === 'success') {
                            // If we have a response with "success" status but without the "success" flag
                            // it means the import is already completed
                            // Hide import elements
                            $('#csvPreviewAccordion').hide();
                            $('.unique-field-card').hide();
                            $('#auto-map-btn').hide(); // Correct: use ID instead of class
                            $('.card-header').hide(); // Hide the card header completely
                            $('.import-card').find('h3').hide(); // Hide the "Column Mapping" title
                            $('.import-card .card-header').hide(); // Hide the card header
                            $('#import-mapping-form').hide();
                            $('#import-progress').hide();
                            $('#import-results').show();

                            // Update final statistics with the received data
                            $('#result-total-rows').text(response.total || 0);
                            $('#result-created-rows').text(response.created || 0);
                            $('#result-updated-rows').text(response.updated || 0);
                            $('#result-skipped-rows').text(response.skipped || 0);

                            // Try all possible backup filename keys and log everything
                            console.log('Complete data object:', response);
                            
                            // Update the backup message to show only directory, not filename
                            $('#backup-filename').closest('.alert').find('span').html(
                                '{{ trans("backpack::import.backup_created") }} <code>/storage/app/import-backups/</code>'
                            );
                            
                            // Make backup container visible with a highlight effect
                            $('#backup-filename').closest('.alert').addClass('alert-highlight');
                            setTimeout(() => {
                                $('#backup-filename').closest('.alert').removeClass('alert-highlight');
                            }, 2000);

                        } else {
                            // Show error with complete response details
                            showImportError(response.message || "{{ trans('backpack::import.import_error') }}", response);
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
                                errorDetails.parsedResponse = JSON.parse(xhr.responseText);
                            }
                        } catch (e) {
                            // If it's not JSON, use the raw text
                            errorDetails.parseError = e.message;
                        }

                        // Error handling with details
                        showImportError(xhr.responseJSON?.message || "{{ trans('backpack::import.import_error') }}", errorDetails);
                    }
                });
            });
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
                $('.progress-bar').css('width', progressPercentage + '%');
                $('#progress-text').text(progressPercentage + '%');

                // Update statistics
                $('#total-rows').text(data.processed);
                $('#created-rows').text(data.created || 0);
                $('#updated-rows').text(data.updated || 0);
                $('#skipped-rows').text(data.skipped || 0);

                // If import is completed
                if (data.status === 'completed') {
                    // Hide import elements
                    $('#csvPreviewAccordion').hide();
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

                    // Try all possible backup filename keys and log everything
                    console.log('Complete data object:', data);
                    
                    // Update the backup message to show only directory, not filename
                    $('#backup-filename').closest('.alert').find('span').html(
                        '{{ trans("backpack::import.backup_created") }} <code>/storage/app/import-backups/</code>'
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
                    $('#csvPreviewAccordion').hide();
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

                    // Try all possible backup filename keys and log everything
                    console.log('Complete data object:', data);
                    
                    // Update the backup message to show only directory, not filename
                    $('#backup-filename').closest('.alert').find('span').html(
                        '{{ trans("backpack::import.backup_created") }} <code>/storage/app/import-backups/</code>'
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
            $('#import-error').show();
            $('#error-message').text(message);

            // If there are additional details, show them in the details area
            if (details) {
                $('#error-details').show();
                let logText = typeof details === 'object' ? JSON.stringify(details, null, 2) : details.toString();
                $('#error-log').text(logText);
            } else {
                $('#error-details').hide();
            }
        }

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
                        // Filter out CSV columns already exactly mapped, unless they are identical to this one
                        .filter(csv => !exactlyMappedCsvIndices.has(csv.index) ||
                            csv.header.toLowerCase() === tableCol.column.toLowerCase())
                        .map(csv => {
                            // Normalize column names
                            const tableColName = tableCol.column.toLowerCase();
                            const csvHeaderName = csv.header.toLowerCase();

                            // Create an object for detailed scores (for debugging)
                            const scoreDetails = {
                                initial: 0,
                                partMatching: 0,
                                prefixMatching: 0,
                                suffixMatching: 0,
                                languageMatching: 0,
                                penalties: 0,
                                total: 0
                            };

                            // Complete map of language abbreviations
                            const languageMap = {
                                'en': 'english',
                                'eng': 'english',
                                'english': 'english',
                                'it': 'italian',
                                'ita': 'italian',
                                'italian': 'italian',
                                'italiano': 'italian',
                                'fr': 'french',
                                'fra': 'french',
                                'french': 'french',
                                'es': 'spanish',
                                'esp': 'spanish',
                                'spa': 'spanish',
                                'spanish': 'spanish',
                                'de': 'german',
                                'deu': 'german',
                                'ger': 'german',
                                'german': 'german'
                            };

                            // Split column names into parts
                            const tableParts = tableColName.split(/[_\s-]|(?=[A-Z])/).filter(p => p.length >= 2);
                            const csvParts = csvHeaderName.split(/[_\s-]|(?=[A-Z])/).filter(p => p.length >= 2);

                            // STEP 1: Check if both contain language abbr. and if they match
                            let tableLanguage = null;
                            let csvLanguage = null;

                            // Correctly recognize language codes only when isolated or in key positions
                            const isValidLanguagePart = (part, parts, index) => {
                                // If it's a part isolated by dash or underscore, it's probably a language code
                                if (parts.length > 1 && (index === 0 || index === parts.length - 1)) {
                                    return true;
                                }

                                // For 2-character abbreviations, be more cautious
                                if (part.length <= 2) {
                                    // Check if the original name contains "_en" or "en_" or similar
                                    const originalName = index === 0 ? tableColName : csvHeaderName;
                                    return originalName.includes(`_${part}`) ||
                                        originalName.includes(`${part}_`) ||
                                        originalName.endsWith(`_${part}`);
                                }

                                // For longer abbreviations like "eng", "ita", etc. we're less restrictive
                                return true;
                            };

                            // Check the last part for language suffix (more common)
                            if (tableParts.length > 0 && csvParts.length > 0) {
                                const tableLastIndex = tableParts.length - 1;
                                const csvLastIndex = csvParts.length - 1;
                                const tableLastPart = tableParts[tableLastIndex];
                                const csvLastPart = csvParts[csvLastIndex];

                                if (languageMap[tableLastPart] && isValidLanguagePart(tableLastPart, tableParts, tableLastIndex)) {
                                    tableLanguage = languageMap[tableLastPart];
                                }

                                if (languageMap[csvLastPart] && isValidLanguagePart(csvLastPart, csvParts, csvLastIndex)) {
                                    csvLanguage = languageMap[csvLastPart];
                                }
                            }

                            // If not found in suffix, search anywhere with validation
                            if (!tableLanguage) {
                                for (let i = 0; i < tableParts.length; i++) {
                                    const part = tableParts[i];
                                    if (languageMap[part] && isValidLanguagePart(part, tableParts, i)) {
                                        tableLanguage = languageMap[part];
                                        break;
                                    }
                                }
                            }

                            if (!csvLanguage) {
                                for (let i = 0; i < csvParts.length; i++) {
                                    const part = csvParts[i];
                                    if (languageMap[part] && isValidLanguagePart(part, csvParts, i)) {
                                        csvLanguage = languageMap[part];
                                        break;
                                    }
                                }
                            }

                            // Calculate language score
                            // 1. Both columns have language and match: VERY high score
                            if (tableLanguage && csvLanguage && tableLanguage === csvLanguage) {
                                scoreDetails.languageMatching = 20; // High score for language match
                            }
                            // 2. Both columns have language but do NOT match: penalty
                            else if (tableLanguage && csvLanguage && tableLanguage !== csvLanguage) {
                                scoreDetails.penalties -= 30; // Severe penalty if different languages
                            }
                            // 3. Only one column has language: no score/penalty

                            // STEP 2: Check part matching (excluding language parts)
                            // Filter language parts more precisely
                            const tableNonLangParts = tableParts.filter((part, index) =>
                                !(languageMap[part] && isValidLanguagePart(part, tableParts, index)));
                            const csvNonLangParts = csvParts.filter((part, index) =>
                                !(languageMap[part] && isValidLanguagePart(part, csvParts, index)));

                            // If a non-language part matches exactly
                            for (const tablePart of tableNonLangParts) {
                                if (csvNonLangParts.includes(tablePart)) {
                                    scoreDetails.partMatching += 5 + Math.min(2, tablePart.length / 2);
                                }

                                // Matching the beginning part of a word
                                for (const csvPart of csvNonLangParts) {
                                    if (csvPart.startsWith(tablePart) || tablePart.startsWith(csvPart)) {
                                        scoreDetails.partMatching += 3;
                                    }
                                }
                            }

                            // STEP 3: Check if prefixes (initial parts) match
                            if (tableNonLangParts.length > 0 && csvNonLangParts.length > 0) {
                                if (tableNonLangParts[0] === csvNonLangParts[0]) {
                                    scoreDetails.prefixMatching = 10; // High boost for first part match
                                }
                            }

                            // STEP 4: Check other common patterns
                            // If first and last (non-language) parts match but different length
                            if (tableNonLangParts.length >= 2 && csvNonLangParts.length >= 2) {
                                const tableFirstNonLang = tableNonLangParts[0];
                                const tableLastNonLang = tableNonLangParts[tableNonLangParts.length - 1];
                                const csvFirstNonLang = csvNonLangParts[0];
                                const csvLastNonLang = csvNonLangParts[csvNonLangParts.length - 1];

                                if (tableFirstNonLang === csvFirstNonLang &&
                                    tableLastNonLang === csvLastNonLang) {
                                    scoreDetails.partMatching += 8;
                                }
                            }

                            // Penalize more if number of non-language parts is very different
                            const nonLangPartsDiff = Math.abs(tableNonLangParts.length - csvNonLangParts.length);
                            if (nonLangPartsDiff > 1) {
                                scoreDetails.penalties -= nonLangPartsDiff * 2;
                            }

                            // Calculate total score
                            scoreDetails.total = scoreDetails.initial +
                                scoreDetails.partMatching +
                                scoreDetails.prefixMatching +
                                scoreDetails.suffixMatching +
                                scoreDetails.languageMatching +
                                scoreDetails.penalties;

                            return {
                                csv,
                                score: scoreDetails.total
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

        // Highlight the unique field selected in the mapping table
        function highlightUniqueFieldInTable() {
            // Remove existing highlighting
            document.querySelectorAll('.mapping-row.unique-field-row').forEach(row => {
                row.classList.remove('unique-field-row');
            });

            // Get the selected value in the unique_field field
            const uniqueFieldSelect = document.getElementById('unique_field');
            if (!uniqueFieldSelect || !uniqueFieldSelect.value) return;

            // Find the corresponding row in the mapping table
            const mappingRow = document.querySelector(`.mapping-row[data-table-column="${uniqueFieldSelect.value}"]`);
            if (mappingRow) {
                // Highlight the row
                mappingRow.classList.add('unique-field-row');

                // Ensure the row is visible (optional)
                setTimeout(() => {
                    mappingRow.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }, 100);
            }
        }

        // Listen for changes in the unique_field select
        const uniqueFieldSelect = document.getElementById('unique_field');
        if (uniqueFieldSelect) {
            uniqueFieldSelect.addEventListener('change', highlightUniqueFieldInTable);

            // Also check at the beginning if there is already a selected value
            setTimeout(highlightUniqueFieldInTable, 500);
        }

        setTimeout(function() {
            setInitialExactMatches();
            $(document).on('change', '#unique_field', function() {
                // Add focus effect to the unique_field field
                $(this).addClass('unique-field-focus');
                setTimeout(() => {
                    $(this).removeClass('unique-field-focus');
                }, 2000);

                // Remove previous highlighting
                $('.fw-medium.highlighted-field').removeClass('highlighted-field');

                // Get the selected value
                let selectedValue = $(this).val();

                if (selectedValue) {
                    // Find the corresponding mapping row
                    let targetRow = $(`.mapping-row[data-table-column='${selectedValue}']`);

                    // Highlight only the column text instead of the entire cell
                    if (targetRow.length) {
                        targetRow.find('.table-column .fw-medium').addClass('highlighted-field');

                        // Scroll the view to the highlighted text
                        $('html, body').animate({
                            scrollTop: targetRow.find('.table-column .fw-medium').offset().top - 200
                        }, 500);
                    }
                }
            });
        }, 500);

        document.querySelector('#csvPreviewAccordion .accordion-button').addEventListener('click', function() {
            // Small delay to ensure the content is visible
            setTimeout(makeAllTruncatedElementsClickable, 300);
        });

        // Re-run the truncated text detection when a collapse type element is opened
        document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(collapseButton => {
            collapseButton.addEventListener('shown.bs.collapse', function() {
                setTimeout(makeAllTruncatedElementsClickable, 300);
            });
        });
    });
</script>
@endpush