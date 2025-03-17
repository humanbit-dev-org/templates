@if ($crud->hasAccess('update', $entry))
@if (!$crud->model->translationEnabled())

{{-- Option buttons with Accept and Reject --}}
@if ((!isset($entry->status) || empty($entry->status)) && $entry->type == 'request')
<button type="button" class="btn btn-success p-1" onclick="handleEntry(this, 'accept')" data-route="{{ url($crud->route.'/'.$entry->getKey().'/accept') }}">
    <i class="la la-check"></i>
</button>
<button type="button" class="btn btn-danger p-1" onclick="handleEntry(this, 'reject')" data-route="{{ url($crud->route.'/'.$entry->getKey().'/reject') }}">
    <i class="la la-times"></i>
</button>
@endif

@endif

{{-- Button Javascript --}}
@push('after_scripts') @if (request()->ajax()) @endpush @endif
<script>
    if (typeof handleEntry !== 'function') {
        function handleEntry(elem, action) {
            const route = $(elem).data('route');
            const entryId = route.split('/').slice(-2)[0];

            if (action === 'reject') {
                // Box di conferma per il rifiuto con textarea
                swal({
                    title: "{!! trans('backpack::base.notice') !!}",
                    text: "{!! trans('backpack::crud.contact_reject_confirm') !!}",
                    content: {
                        element: "textarea",
                        attributes: {
                            placeholder: "{!! trans('backpack::crud.contact_reject_reason_placeholder') !!}",
                            className: "form-control"
                        },
                    },
                    icon: "warning",
                    buttons: {
                        cancel: {
                            text: "{!! trans('backpack::crud.cancel') !!}",
                            value: null,
                            visible: true,
                            className: "bg-secondary",
                            closeModal: true,
                        },
                        confirm: {
                            text: "{!! trans('backpack::crud.contact_reject') !!}",
                            value: true,
                            visible: true,
                            className: "bg-danger",
                        }
                    },
                }).then((value) => {
                    if (value) {
                        const textarea = document.querySelector(".swal-content textarea");
                        const reason = textarea ? textarea.value.trim() : "";

                        if (!reason) {
                            new Noty({
                                type: "error",
                                text: "{!! trans('backpack::crud.contact_reject_reason_required') !!}"
                            }).show();
                            return;
                        }

                        proceedWithAction(route, action, entryId, reason);
                    }
                });
                return;
            }

            // Box di conferma per l'accettazione
            swal({
                title: "{!! trans('backpack::base.notice') !!}",
                text: "{!! trans('backpack::crud.contact_accept_confirm') !!}",
                icon: "info",
                buttons: {
                    cancel: {
                        text: "{!! trans('backpack::crud.cancel') !!}",
                        value: null,
                        visible: true,
                        className: "bg-secondary",
                        closeModal: true,
                    },
                    confirm: {
                        text: "{!! trans('backpack::crud.contact_accept') !!}",
                        value: true,
                        visible: true,
                        className: "bg-success",
                    }
                },
            }).then((value) => {
                if (value) {
                    proceedWithAction(route, action, entryId);
                }
            });
        }

        function proceedWithAction(route, action, entryId, reason = null) {
            const requestData = reason ? {
                reason: reason
            } : {};

            $.ajax({
                url: route,
                type: 'POST',
                data: requestData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        if (typeof crud !== 'undefined' && typeof crud.table !== 'undefined') {
                            crud.table.draw(false);
                        }

                        crud.table.one('draw', function(e) {
                            let table = $(e.currentTarget);
                            table.find('tbody tr').each(function() {
                                let row = $(this);
                                let val = row.find('td:first-child span').text().trim();

                                if (val == entryId) {
                                    row.addClass(action + 'ed');
                                    setTimeout(function() {
                                        row.removeClass(action + 'ed');
                                    }, 6000);
                                }
                            });
                        });

                        new Noty({
                            type: "success",
                            text: action == 'accept' ? "{!! '<strong>'.trans('backpack::crud.contact_accept_confirmation_title').'</strong><br>'.trans('backpack::crud.contact_accept_confirmation_message') !!}" : "{!! '<strong>'.trans('backpack::crud.contact_reject_confirmation_title').'</strong><br>'.trans('backpack::crud.contact_reject_confirmation_message') !!}"
                        }).show();
                    } else {
                        swal({
                            title: action == 'accept' ? "{!! trans('backpack::crud.contact_accept_confirmation_not_title') !!}" : "{!! trans('backpack::crud.contact_reject_confirmation_not_title') !!}",
                            text: action == 'accept' ? "{!! trans('backpack::crud.contact_accept_confirmation_not_message') !!}" : "{!! trans('backpack::crud.contact_reject_confirmation_not_message') !!}",
                            icon: "error",
                            timer: 4000,
                            buttons: false,
                        });
                    }
                },
                error: function() {
                    swal({
                        title: action == 'accept' ? "{!! trans('backpack::crud.contact_accept_confirmation_not_title') !!}" : "{!! trans('backpack::crud.contact_reject_confirmation_not_title') !!}",
                        text: action == 'accept' ? "{!! trans('backpack::crud.contact_accept_confirmation_not_message') !!}" : "{!! trans('backpack::crud.contact_reject_confirmation_not_message') !!}",
                        icon: "error",
                        timer: 4000,
                        buttons: false,
                    });
                }
            });
        }
    }
</script>
@if (!request()->ajax()) @endpush @endif @endif