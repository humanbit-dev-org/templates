@php
    $isDeveloper = strtolower(optional(backpack_auth()->user()->backpackRole)->name ?? '') === 'developer';
    $disabledClass = $isDeveloper ? '' : 'disabled-action';
@endphp
@if ($crud->hasAccess('update', $entry))
@if (!$crud->model->translationEnabled())

{{-- Single duplicate button --}}
<a href="javascript:void(0)" onclick="duplicateEntry(this)" bp-button="duplicate" data-route="{{ url($crud->route.'/'.$entry->getKey().'/duplicate') }}" class="btn btn-sm btn-link p-0 {{ $disabledClass }}" data-button-type="duplicate" data-disabled="{{ $isDeveloper ? 'false' : 'true' }}" title="{{ $isDeveloper ? trans('backpack::crud.duplicate') : 'Solo sviluppatori possono duplicare' }}">
    <i class="la la-clone btn-actions"></i>
</a>

@endif


{{-- Button Javascript --}}
{{-- - used right away in AJAX operations (ex: List) --}}
{{-- - pushed to the end of the page, after jQuery is loaded, for non-AJAX operations (ex: Show) --}}
@push('after_scripts') @if (request()->ajax()) @endpush @endif
<script>
    if (typeof duplicateEntry !== 'function') {
        $("[data-button-type=duplicate]").unbind('click');

        function duplicateEntry(button) {
            // Check if button is disabled
            if ($(button).attr('data-disabled') === 'true') {
                return false;
            }
            const route = $(button).attr('data-route');

            swal({
                title: "{!! trans('backpack::base.notice') !!}",
		        text: "{!! trans('backpack::crud.duplicate_confirm') !!}",
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
                        url: route,
                        type: 'GET',
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Redraw the table to include the new entry
                                if (typeof crud !== 'undefined' && typeof crud.table !== 'undefined') {
                                    crud.table.draw(false);
                                }

                                // Listen for the draw event (when the table is redrawn)
                                crud.table.one('draw', function(e) {
                                    let table = $(e.currentTarget);

                                    table.find('tbody tr').each(function() {
                                        let row = $(this);
                                        let val = row.find('td:first-child span').text().trim();

                                        if (val == response.new_entry_id) {
                                            let even = false;
                                            if (row.hasClass('even')) {
                                                even = true;
                                                row.removeClass('even');
                                                row.addClass('odd');
                                            }
                                            row.addClass('duplicated');
                                            setTimeout(function() {
                                                if (even) {
                                                    row.addClass('even');
                                                    row.removeClass('odd');
                                                }
                                                row.removeClass('duplicated ');
                                            }, 6000);
                                        }
                                    });
                                });


                                // Show success notification
                                new Noty({
                                    type: "success",
                                    text:  "{!! '<strong>'.trans('backpack::crud.duplicate_confirmation_title').'</strong><br>'.trans('backpack::crud.duplicate_confirmation_message') !!}"
                                }).show();
                            } else {
                                swal({
                                    title: "{!! trans('backpack::crud.duplicate_confirmation_not_title') !!}",
	                                text: "{!! trans('backpack::crud.duplicate_confirmation_not_message') !!}",
                                    icon: "error",
                                    timer: 4000,
                                    buttons: false,
                                });
                            }
                        },
                        error: function() {
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
    }





    // make it so that the function above is run after each DataTable draw event
    // crud.addFunctionToDataTablesDrawEventQueue('deleteEntry');
</script>
@if (!request()->ajax()) @endpush @endif @endif