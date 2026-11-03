@if ($crud->hasAccess('delete') || $crud->hasAccess('update'))
<div id="bulk-operations" class="hb-bulk-toolbar" aria-live="polite">
    <div class="hb-bulk-toolbar__inner">
        <div class="hb-bulk-toolbar__start">
            <div class="hb-bulk-toolbar__counter">
                <i class="la la-check-square" aria-hidden="true"></i>
                <span class="hb-bulk-counter-text">0 {{ trans('backpack::crud.selected') }}</span>
            </div>
        </div>

        <div class="hb-bulk-toolbar__center">
            <div id="bulk-update-controls" class="hb-bulk-update-controls d-none">
                <span class="hb-bulk-section-label">{{ trans('backpack::crud.bulk_boolean_quick_actions') }}</span>
                <div id="bulk-boolean-actions" class="hb-bulk-boolean-actions"></div>

                <div id="bulk-enum-controls" class="hb-bulk-enum-controls d-none">
                    <span class="hb-bulk-section-label">{{ trans('backpack::crud.bulk_update_field') }}</span>
                    <div id="bulk-enum-fields" class="hb-bulk-enum-fields"></div>
                </div>
            </div>
        </div>

        <div class="hb-bulk-toolbar__end">
            @if ($crud->hasAccess('update'))
                <button type="button" id="bulk-duplicate-btn" class="btn btn-sm btn-primary">
                    <i class="la la-clone" aria-hidden="true"></i>{{ trans('backpack::crud.duplicate') }}
                </button>
            @endif

            @if ($crud->hasAccess('delete'))
                <button type="button" id="bulk-delete-btn" class="btn btn-sm btn-danger">
                    <i class="la la-trash" aria-hidden="true"></i>{{ trans('backpack::crud.delete') }}
                </button>
            @endif

            <button type="button" id="bulk-cancel-btn" class="btn btn-sm btn-outline-secondary">
                <i class="la la-times" aria-hidden="true"></i>{{ trans('backpack::crud.cancel') }}
            </button>
        </div>
    </div>
</div>

@push('after_scripts')
<script>
if (typeof window.hbBulkOperationsInit !== 'function') {
    window.hbBulkOperationsInit = function hbBulkOperationsInit() {
        const bulkBaseRoute = @json(url($crud->route));
        const selectedEntryIds = new Set();
        const masterCheckboxSelector = '.hb-bulk-select-all';
        let booleanFields = [];
        let enumFields = [];

        function escapeHtml(value) {
            return $('<div/>').text(String(value ?? '')).html();
        }

        function normalizeId(value) {
            return String(value ?? '').trim();
        }

        function extractIdFromRoute(route) {
            if (!route || String(route).startsWith('javascript:')) {
                return '';
            }

            try {
                const routePath = new URL(String(route), window.location.origin).pathname.replace(/\/+$/, '');
                const basePath = new URL(String(bulkBaseRoute), window.location.origin).pathname.replace(/\/+$/, '');

                if (!routePath.startsWith(basePath + '/')) {
                    return '';
                }

                const remainder = routePath.substring((basePath + '/').length);
                const segments = remainder.split('/').filter(Boolean);
                return segments.length > 0 ? decodeURIComponent(segments[0]) : '';
            } catch (error) {
                return '';
            }
        }

        function extractEntryId($row) {
            const dataId = normalizeId($row.attr('data-entry-id') || $row.data('entry-id'));
            if (dataId !== '') {
                return dataId;
            }

            const candidates = [
                $row.find('[data-button-type="delete"]').first().data('route'),
                $row.find('[data-button-type="duplicate"]').first().data('route'),
                $row.find('a[href*="/edit"]').first().attr('href'),
                $row.find('a[href*="/show"]').first().attr('href'),
                $row.find('a[href]').first().attr('href'),
            ];

            for (const route of candidates) {
                const extracted = extractIdFromRoute(route);
                if (extracted !== '') {
                    return extracted;
                }
            }

            return '';
        }

        function syncRowSelectionClasses() {
            $('#crudTable tbody tr').each(function() {
                const checkbox = $(this).find('.entry-checkbox');
                const entryId = String(checkbox.data('entry-id') || '');
                if (entryId && selectedEntryIds.has(entryId)) {
                    checkbox.prop('checked', true);
                    $(this).addClass('hb-bulk-selected-row');
                } else {
                    checkbox.prop('checked', false);
                    $(this).removeClass('hb-bulk-selected-row');
                }
            });
        }

        function updateMasterCheckboxState() {
            const $checkboxes = $('#crudTable tbody .entry-checkbox');
            const selectedVisibleCount = $checkboxes.filter(':checked').length;
            const totalVisibleCount = $checkboxes.length;
            const $masters = $(masterCheckboxSelector);

            if (!$masters.length) {
                return;
            }

            const isChecked = totalVisibleCount > 0 && selectedVisibleCount === totalVisibleCount;
            const isIndeterminate = selectedVisibleCount > 0 && selectedVisibleCount < totalVisibleCount;

            $masters.each(function() {
                $(this).prop('checked', isChecked);
                $(this).prop('indeterminate', isIndeterminate);
            });
        }

        function updateToolbarState() {
            const selectedCount = selectedEntryIds.size;
            const $toolbar = $('#bulk-operations');
            const label = selectedCount + ' {{ trans('backpack::crud.selected') }}';
            $('.hb-bulk-counter-text').text(label);

            if (selectedCount > 0) {
                $toolbar.addClass('is-active');
                $('body').addClass('hb-bulk-open');
            } else {
                $toolbar.removeClass('is-active');
                $('body').removeClass('hb-bulk-open');
            }
        }

        function getVisibleHeaderFirstCell() {
            if (typeof crud !== 'undefined' && crud.table && typeof crud.table.column === 'function') {
                const headerNode = crud.table.column(0).header();
                if (headerNode) {
                    return $(headerNode);
                }
            }

            const $scrollHeadCell = $('#crudTable_wrapper .dataTables_scrollHead thead tr:first-child th:first-child');
            if ($scrollHeadCell.length) {
                return $scrollHeadCell;
            }

            return $('#crudTable thead tr:first-child th:first-child');
        }

        function ensureSelectAllCheckbox() {
            const $targetHeaderCell = getVisibleHeaderFirstCell();
            if (!$targetHeaderCell.length) {
                return;
            }

            let $existingWrap = $('.hb-select-all-wrap').first();
            if (!$existingWrap.length) {
                $targetHeaderCell.prepend(
                    '<span class="hb-select-all-wrap"><input type="checkbox" class="form-check-input hb-bulk-select-all" aria-label="Select all rows"></span>'
                );
                $existingWrap = $targetHeaderCell.find('.hb-select-all-wrap').first();
            }

            if ($existingWrap.length && !$existingWrap.parent().is($targetHeaderCell)) {
                $targetHeaderCell.prepend($existingWrap);
            }
        }

        function ensureRowCheckboxes() {
            $('#crudTable tbody tr').each(function() {
                const $row = $(this);
                const entryId = extractEntryId($row);
                if (entryId === '') {
                    return;
                }

                const $firstCell = $row.find('td:first-child');
                if (!$firstCell.length) {
                    return;
                }

                $firstCell.find('.crud_bulk_actions_checkbox').remove();

                const checkboxId = 'entry-checkbox-' + escapeHtml(entryId);
                if (!$firstCell.find('.entry-checkbox').length) {
                    $firstCell.prepend(
                        '<span class="hb-row-checkbox-wrap">' +
                            '<input type="checkbox" class="form-check-input entry-checkbox" id="' + checkboxId + '" data-entry-id="' + escapeHtml(entryId) + '">' +
                        '</span>'
                    );
                } else {
                    $firstCell.find('.entry-checkbox').attr('data-entry-id', entryId);
                }
            });
        }

        function ensureBulkCheckboxes() {
            ensureSelectAllCheckbox();
            ensureRowCheckboxes();
            ensureCheckboxEventGuards();
            pruneSelectionToCurrentPage();
            syncRowSelectionClasses();
            updateMasterCheckboxState();
            updateToolbarState();
        }

        function ensureCheckboxEventGuards() {
            const wrapper = document.querySelector('#crudTable_wrapper');
            if (!wrapper || wrapper.dataset.hbBulkGuardBound === '1') {
                return;
            }

            const guardHandler = function(event) {
                const target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }

                if (
                    target.closest('.hb-select-all-wrap') ||
                    target.closest('.hb-bulk-select-all')
                ) {
                    event.stopPropagation();
                }
            };

            wrapper.addEventListener('mousedown', guardHandler, true);
            wrapper.addEventListener('click', guardHandler, true);
            wrapper.dataset.hbBulkGuardBound = '1';
        }

        function pruneSelectionToCurrentPage() {
            const visibleIds = new Set();
            $('#crudTable tbody .entry-checkbox').each(function() {
                const entryId = String($(this).data('entry-id') || '');
                if (entryId !== '') {
                    visibleIds.add(entryId);
                }
            });

            Array.from(selectedEntryIds).forEach(function(entryId) {
                if (!visibleIds.has(entryId)) {
                    selectedEntryIds.delete(entryId);
                }
            });
        }

        function clearSelectionAndRefresh() {
            selectedEntryIds.clear();
            syncRowSelectionClasses();
            updateMasterCheckboxState();
            updateToolbarState();
        }

        function formatSummaryDetails(summary, details) {
            const parts = [];
            if (summary) {
                parts.push(
                    '{{ trans('backpack::crud.bulk_summary_ok') }}: ' + (summary.ok ?? 0),
                    '{{ trans('backpack::crud.bulk_summary_skipped') }}: ' + (summary.skipped ?? 0),
                    '{{ trans('backpack::crud.bulk_summary_errors') }}: ' + (summary.errors ?? 0)
                );
            }

            const detailsPreview = Array.isArray(details) ? details.slice(0, 5) : [];
            const detailsHtml = detailsPreview.map(function(item) {
                const id = escapeHtml(item.id ?? '-');
                const reason = escapeHtml(item.reason ?? '-');
                return '<li>#' + id + ': ' + reason + '</li>';
            }).join('');

            return {
                text: parts.join(' | '),
                detailsHtml: detailsHtml,
            };
        }

        function showResultNotification(result, successTitle, errorTitle) {
            const summaryData = formatSummaryDetails(result.summary, result.details);
            const hasSuccess = Number(result.summary?.ok ?? 0) > 0;
            const hasErrors = Number(result.summary?.errors ?? 0) > 0;
            const hasSkips = Number(result.summary?.skipped ?? 0) > 0;
            const type = hasErrors || hasSkips ? (hasSuccess ? 'warning' : 'error') : 'success';
            const title = type === 'success' ? successTitle : errorTitle;
            const detailsBlock = summaryData.detailsHtml
                ? '<ul class="mb-0 ps-3 mt-2">' + summaryData.detailsHtml + '</ul>'
                : '';

            new Noty({
                type: type,
                text: '<strong>' + title + '</strong><br>' + escapeHtml(summaryData.text || (result.message || '')) + detailsBlock,
                timeout: 6000,
            }).show();
        }

        function drawTableWithoutReset() {
            if (typeof crud === 'undefined' || !crud.table) {
                return;
            }

            crud.table.draw(false);
        }

        function runBulkAction(options) {
            pruneSelectionToCurrentPage();
            updateToolbarState();
            if (selectedEntryIds.size === 0) {
                return;
            }

            swal({
                title: options.title,
                text: options.text,
                icon: options.icon,
                dangerMode: options.dangerMode || false,
                buttons: {
                    cancel: {
                        text: "{{ trans('backpack::crud.cancel') }}",
                        visible: true,
                        className: "bg-secondary",
                        closeModal: true,
                    },
                    confirm: {
                        text: options.confirmText,
                        visible: true,
                        className: options.confirmClass,
                    },
                },
            }).then(function(confirmed) {
                if (!confirmed) {
                    return;
                }

                $.ajax({
                    url: options.url,
                    type: 'POST',
                    data: Object.assign({
                        ids: Array.from(selectedEntryIds),
                        _token: '{{ csrf_token() }}',
                    }, options.payload || {}),
                    success: function(result) {
                        showResultNotification(result, options.successTitle, options.errorTitle);
                        if (Number(result.summary?.ok ?? 0) > 0) {
                            clearSelectionAndRefresh();
                            drawTableWithoutReset();
                        }
                    },
                    error: function(xhr) {
                        const message = xhr.responseJSON?.message || '{{ trans('backpack::crud.bulk_generic_error') }}';
                        swal({
                            title: options.errorTitle,
                            text: message,
                            icon: 'error',
                            timer: 5000,
                            buttons: false,
                        });
                    },
                });
            });
        }

        function renderBooleanActionButtons() {
            const $container = $('#bulk-boolean-actions');
            $container.empty();

            if (!Array.isArray(booleanFields) || booleanFields.length === 0) {
                return;
            }

            const sortedFields = booleanFields
                .slice()
                .sort(function(fieldA, fieldB) {
                    return String(fieldA.label || '').localeCompare(String(fieldB.label || ''), undefined, { sensitivity: 'base' });
                });

            sortedFields.forEach(function(field) {
                const trueOption = Array.isArray(field.options) ? field.options.find(function(option) {
                    return String(option.value) === '1';
                }) : null;
                const falseOption = Array.isArray(field.options) ? field.options.find(function(option) {
                    return String(option.value) === '0';
                }) : null;

                if (!trueOption || !falseOption) {
                    return;
                }

                const trueLabel = '{{ trans('backpack::crud.bulk_activate_field') }}'
                    .replace(':field', field.label);
                const falseLabel = '{{ trans('backpack::crud.bulk_deactivate_field') }}'
                    .replace(':field', field.label);

                const customTrueLabel = field.action_labels && field.action_labels['1'] ? String(field.action_labels['1']) : '';
                const customFalseLabel = field.action_labels && field.action_labels['0'] ? String(field.action_labels['0']) : '';
                const finalTrueLabel = customTrueLabel || trueLabel;
                const finalFalseLabel = customFalseLabel || falseLabel;

                const onIconClass = field.action_icons && field.action_icons['1'] ? String(field.action_icons['1']) : 'la la-toggle-on';
                const offIconClass = field.action_icons && field.action_icons['0'] ? String(field.action_icons['0']) : 'la la-toggle-off';

                const normalizeActionColor = function(color) {
                    const normalized = color ? String(color).toLowerCase() : '';
                    const allowed = ['danger', 'warning', 'success', 'info'];
                    return allowed.includes(normalized) ? normalized : '';
                };

                const actionColors = field.action_colors && typeof field.action_colors === 'object' ? field.action_colors : null;
                const onColor = normalizeActionColor(actionColors ? actionColors['1'] : '');
                const offColor = normalizeActionColor(actionColors ? actionColors['0'] : '');

                const onBtnColorClass = onColor ? 'btn-outline-' + onColor : 'btn-outline-primary';
                const offBtnColorClass = offColor ? 'btn-outline-' + offColor : 'btn-outline-primary';
                const offExtraClass = offColor ? '' : ' hb-bulk-boolean-action--deactivate';
                const $pair = $('<div class="hb-bulk-field-group"></div>');

                $pair.append(
                    '<button type="button" class="btn btn-sm ' + onBtnColorClass + ' hb-bulk-boolean-action" data-field="' + escapeHtml(field.name) + '" data-value="1">' +
                        '<i class="' + escapeHtml(onIconClass) + '" aria-hidden="true"></i>' + escapeHtml(finalTrueLabel) +
                    '</button>'
                );
                $pair.append(
                    '<button type="button" class="btn btn-sm ' + offBtnColorClass + ' hb-bulk-boolean-action' + offExtraClass + '" data-field="' + escapeHtml(field.name) + '" data-value="0">' +
                        '<i class="' + escapeHtml(offIconClass) + '" aria-hidden="true"></i>' + escapeHtml(finalFalseLabel) +
                    '</button>'
                );

                $container.append($pair);
            });
        }

        function renderEnumControls() {
            const $controls = $('#bulk-enum-controls');
            const $fieldsContainer = $('#bulk-enum-fields');

            $fieldsContainer.empty();

            if (!Array.isArray(enumFields) || enumFields.length === 0) {
                $controls.addClass('d-none');
                return;
            }

            const emptyValueLabel = '{{ trans('backpack::crud.bulk_update_value') }}';

            enumFields
                .slice()
                .sort(function(fieldA, fieldB) {
                    return String(fieldA.label || '').localeCompare(String(fieldB.label || ''), undefined, { sensitivity: 'base' });
                })
                .forEach(function(field) {
                    const applyLabel = field.apply_label ? String(field.apply_label) : '{{ trans('backpack::crud.bulk_update_apply') }}';
                    const applyIcon = field.apply_icon ? String(field.apply_icon) : 'la la-check';
                    const normalizeApplyColor = function(color) {
                        const normalized = color ? String(color).toLowerCase() : '';
                        const allowed = ['danger', 'warning', 'success', 'info'];
                        return allowed.includes(normalized) ? normalized : '';
                    };
                    const applyColor = normalizeApplyColor(field.apply_color);
                    const applyBtnColorClass = applyColor ? 'btn-outline-' + applyColor : 'btn-outline-primary';

                    const optionsHtml = Array.isArray(field.options) ? field.options.map(function(option) {
                        return (
                            '<option value="' + escapeHtml(option.value) + '">' +
                                escapeHtml(option.label) +
                            '</option>'
                        );
                    }).join('') : '';

                    $fieldsContainer.append(
                        '<div class="hb-bulk-enum-field-row hb-bulk-enum-row-item" data-field="' + escapeHtml(field.name) + '">' +
                            '<div class="hb-bulk-enum-field-label">' + escapeHtml(field.label) + '</div>' +
                                '<select class="hb-bulk-enum-value ' + applyBtnColorClass + '" data-field="' + escapeHtml(field.name) + '">' +
                                '<option value="">' + escapeHtml(emptyValueLabel) + '</option>' +
                                optionsHtml +
                            '</select>' +
                            '<button type="button" class="btn btn-sm ' + applyBtnColorClass + ' hb-bulk-boolean-action hb-bulk-enum-apply" data-field="' + escapeHtml(field.name) + '" disabled>' +
                                '<i class="' + escapeHtml(applyIcon) + '"></i>' + escapeHtml(applyLabel) +
                            '</button>' +
                        '</div>'
                    );
                });

            $(document).off('change.hbBulkEnumValue').on('change.hbBulkEnumValue', '.hb-bulk-enum-value', function() {
                const $row = $(this).closest('.hb-bulk-enum-row-item');
                const value = String($(this).val() || '');
                $row.find('.hb-bulk-enum-apply').prop('disabled', !value);
            });

            $(document).off('click.hbBulkEnumApply').on('click.hbBulkEnumApply', '.hb-bulk-enum-apply', function() {
                const field = String($(this).data('field') || '');
                const $row = $(this).closest('.hb-bulk-enum-row-item');
                const value = String($row.find('.hb-bulk-enum-value').val() || '');

                if (!field || !value) {
                    return;
                }

                runBulkAction({
                    title: "{{ trans('backpack::base.notice') }}",
                    text: "{{ trans('backpack::crud.bulk_update_confirm') }}",
                    icon: 'info',
                    confirmText: "{{ trans('backpack::crud.bulk_update_apply') }}",
                    confirmClass: 'bg-primary',
                    url: "{{ url($crud->route.'/bulk-update') }}",
                    payload: {
                        field: field,
                        value: value,
                    },
                    successTitle: "{{ trans('backpack::crud.bulk_update_confirmation_title') }}",
                    errorTitle: "{{ trans('backpack::crud.bulk_update_confirmation_not_title') }}",
                });
            });

            $controls.removeClass('d-none');
        }

        function loadBulkFields() {
            $.ajax({
                url: "{{ url($crud->route.'/bulk-fields') }}",
                type: 'GET',
                success: function(response) {
                    const fields = Array.isArray(response.fields) ? response.fields : [];
                    booleanFields = fields.filter(function(field) {
                        return field && field.type === 'boolean';
                    });
                    enumFields = fields.filter(function(field) {
                        return field && field.type === 'enum';
                    });
                    renderBooleanActionButtons();
                    renderEnumControls();

                    if ($('#bulk-boolean-actions').children().length > 0 || enumFields.length > 0) {
                        $('#bulk-update-controls').removeClass('d-none');
                    } else {
                        $('#bulk-update-controls').addClass('d-none');
                    }
                },
            });
        }

        function bindEvents() {
            $(document).off('mousedown.hbBulkSelectAll click.hbBulkSelectAllWrap', '.hb-select-all-wrap, ' + masterCheckboxSelector)
                .on('mousedown.hbBulkSelectAll click.hbBulkSelectAllWrap', '.hb-select-all-wrap, ' + masterCheckboxSelector, function(event) {
                    event.stopPropagation();
                });

            $(document).off('mousedown.hbBulkEntryCheckbox click.hbBulkEntryCheckbox', '.entry-checkbox')
                .on('mousedown.hbBulkEntryCheckbox click.hbBulkEntryCheckbox', '.entry-checkbox', function(event) {
                    event.stopPropagation();
                });

            $(document).off('click.hbBulkSelectAll', masterCheckboxSelector).on('click.hbBulkSelectAll', masterCheckboxSelector, function(event) {
                event.stopPropagation();
                if (this.indeterminate) {
                    this.indeterminate = false;
                }
            });

            $(document).off('change.hbBulkSelectAll', masterCheckboxSelector).on('change.hbBulkSelectAll', masterCheckboxSelector, function(event) {
                event.stopPropagation();
                pruneSelectionToCurrentPage();
                const isChecked = $(this).is(':checked');
                $('#crudTable tbody .entry-checkbox').each(function() {
                    const entryId = String($(this).data('entry-id') || '');
                    if (!entryId) {
                        return;
                    }

                    if (isChecked) {
                        selectedEntryIds.add(entryId);
                    } else {
                        selectedEntryIds.delete(entryId);
                    }
                });

                syncRowSelectionClasses();
                updateMasterCheckboxState();
                updateToolbarState();
            });

            $(document).off('change.hbBulkEntryCheckbox', '.entry-checkbox').on('change.hbBulkEntryCheckbox', '.entry-checkbox', function(event) {
                event.stopPropagation();
                const entryId = String($(this).data('entry-id') || '');
                if (!entryId) {
                    return;
                }

                if ($(this).is(':checked')) {
                    selectedEntryIds.add(entryId);
                } else {
                    selectedEntryIds.delete(entryId);
                }

                syncRowSelectionClasses();
                updateMasterCheckboxState();
                updateToolbarState();
            });

            $('#bulk-delete-btn').off('click.hbBulk').on('click.hbBulk', function() {
                runBulkAction({
                    title: "{{ trans('backpack::base.warning') }}",
                    text: "{{ trans('backpack::crud.bulk_delete_confirm') }}",
                    icon: 'warning',
                    dangerMode: true,
                    confirmText: "{{ trans('backpack::crud.delete') }}",
                    confirmClass: 'bg-danger',
                    url: "{{ url($crud->route.'/bulk-delete') }}",
                    successTitle: "{{ trans('backpack::crud.bulk_delete_confirmation_title') }}",
                    errorTitle: "{{ trans('backpack::crud.delete_confirmation_not_title') }}",
                });
            });

            $('#bulk-duplicate-btn').off('click.hbBulk').on('click.hbBulk', function() {
                runBulkAction({
                    title: "{{ trans('backpack::base.notice') }}",
                    text: "{{ trans('backpack::crud.bulk_duplicate_confirm') }}",
                    icon: 'info',
                    confirmText: "{{ trans('backpack::crud.duplicate') }}",
                    confirmClass: 'bg-primary',
                    url: "{{ url($crud->route.'/bulk-duplicate') }}",
                    successTitle: "{{ trans('backpack::crud.bulk_duplicate_confirmation_title') }}",
                    errorTitle: "{{ trans('backpack::crud.duplicate_confirmation_not_title') }}",
                });
            });

            $('#bulk-cancel-btn').off('click.hbBulk').on('click.hbBulk', function() {
                clearSelectionAndRefresh();
            });

            $(document).off('click.hbBulkBooleanAction', '.hb-bulk-boolean-action').on('click.hbBulkBooleanAction', '.hb-bulk-boolean-action', function() {
                const rawField = $(this).data('field');
                const rawValue = $(this).data('value');
                const field = rawField === undefined || rawField === null ? '' : String(rawField);
                const value = rawValue === undefined || rawValue === null ? '' : String(rawValue);
                if (!field || value === '' || selectedEntryIds.size === 0) {
                    return;
                }

                runBulkAction({
                    title: "{{ trans('backpack::base.notice') }}",
                    text: "{{ trans('backpack::crud.bulk_update_confirm') }}",
                    icon: 'info',
                    confirmText: "{{ trans('backpack::crud.bulk_update_apply') }}",
                    confirmClass: 'bg-primary',
                    url: "{{ url($crud->route.'/bulk-update') }}",
                    payload: {
                        field: field,
                        value: value,
                    },
                    successTitle: "{{ trans('backpack::crud.bulk_update_confirmation_title') }}",
                    errorTitle: "{{ trans('backpack::crud.bulk_update_confirmation_not_title') }}",
                });
            });
        }

        function attachToolbarToBody() {
            const $toolbar = $('#bulk-operations');
            if (!$toolbar.length) {
                return;
            }

            if (!$toolbar.parent().is('body')) {
                $toolbar.appendTo('body');
            }
        }

        bindEvents();
        loadBulkFields();
        attachToolbarToBody();
        ensureBulkCheckboxes();

        if (typeof crud !== 'undefined') {
            crud.addFunctionToDataTablesDrawEventQueue('hbBulkOperationsEnsure');
        }

        window.hbBulkOperationsEnsure = function() {
            attachToolbarToBody();
            ensureBulkCheckboxes();
        };

        $('#crudTable').off('page.hbBulkSelection length.hbBulkSelection').on('page.hbBulkSelection length.hbBulkSelection', function() {
            if (typeof window.hbBulkOperationsEnsure === 'function') {
                window.hbBulkOperationsEnsure();
            }
        });
    };

    $(document).ready(function() {
        window.hbBulkOperationsInit();
    });
}
</script>
@endpush
@endif
