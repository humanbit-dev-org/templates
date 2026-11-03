@php
    // Limits shared with StoreAdminSupportRequest, so the client-side checks
    // reject the same files the server would reject.
    $supportMaxScreenshots = (int) config('backpack.support.max_screenshots', 3);
    $supportScreenshotMaxKb = (int) config('backpack.support.screenshot_max_kb', 5120);
    $supportScreenshotMaxMb = round($supportScreenshotMaxKb / 1024, 1);
@endphp

{{-- Round floating button: the headset icon identifies it as support, the label lives in the tooltip. --}}
<button
    type="button"
    class="hb-support-fab"
    id="hbSupportFab"
    title="{{ trans('backpack::support.fab_title') }}"
    aria-label="{{ trans('backpack::support.fab_title') }}"
    data-bs-toggle="modal"
    data-bs-target="#hbSupportModal"
>
    <i class="la la-headset" aria-hidden="true"></i>
</button>

<div class="modal fade" id="hbSupportModal" tabindex="-1" aria-labelledby="hbSupportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="hbSupportModalLabel">{{ trans('backpack::support.modal_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ trans('backpack::crud.cancel') }}"></button>
            </div>
            <form id="hbSupportForm" novalidate>
                @csrf
                <div class="modal-body">
                    <p class="hb-support-hint mb-3">{{ trans('backpack::support.modal_hint') }}</p>
                    <div class="mb-3">
                        <label for="hbSupportCategory" class="form-label">{{ trans('backpack::support.subject') }}</label>
                        <select class="form-select" id="hbSupportCategory" name="category" required>
                            <option value="" selected disabled>{{ trans('backpack::support.subject_placeholder') }}</option>
                            @foreach (config('backpack.support.categories', []) as $category)
                                <option value="{{ $category }}">{{ trans('backpack::support.categories.'.$category) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="hbSupportMessage" class="form-label">{{ trans('backpack::support.message') }}</label>
                        <textarea
                            class="form-control"
                            id="hbSupportMessage"
                            name="message"
                            rows="5"
                            maxlength="5000"
                            required
                            placeholder="{{ trans('backpack::support.message_placeholder') }}"
                        ></textarea>
                    </div>
                    <div class="mb-0">
                        <label for="hbSupportScreenshots" class="form-label">{{ trans('backpack::support.screenshots') }}</label>
                        <input
                            type="file"
                            class="d-none"
                            id="hbSupportScreenshots"
                            accept="image/png,image/jpeg,image/webp"
                            multiple
                        >
                        <div class="hb-support-shots" id="hbSupportShots">
                            <button type="button" class="hb-support-shots__add" id="hbSupportShotsAdd">
                                <i class="la la-camera" aria-hidden="true"></i>
                                <span>{{ trans('backpack::support.screenshots_add') }}</span>
                            </button>
                        </div>
                        <p class="hb-support-hint mt-2">
                            {{ trans('backpack::support.screenshots_hint', ['max' => $supportMaxScreenshots, 'size' => $supportScreenshotMaxMb]) }}
                        </p>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        {{ trans('backpack::support.cancel') }}
                    </button>
                    <button type="submit" class="btn btn-primary" id="hbSupportSubmit">
                        <i class="la la-paper-plane me-1" aria-hidden="true"></i>
                        {{ trans('backpack::support.send') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('after_scripts')
<script>
(function () {
    const form = document.getElementById('hbSupportForm');
    const submitBtn = document.getElementById('hbSupportSubmit');
    const modalEl = document.getElementById('hbSupportModal');
    const messageEl = document.getElementById('hbSupportMessage');
    const fileInput = document.getElementById('hbSupportScreenshots');
    const shotsEl = document.getElementById('hbSupportShots');
    const addShotBtn = document.getElementById('hbSupportShotsAdd');

    if (!form || !submitBtn) {
        return;
    }

    const limits = {
        maxFiles: @json($supportMaxScreenshots),
        maxBytes: @json($supportScreenshotMaxKb * 1024),
        types: ['image/png', 'image/jpeg', 'image/webp'],
    };

    const labels = {
        send: @json(trans('backpack::support.send')),
        sending: @json(trans('backpack::support.sending')),
        sent: @json(trans('backpack::support.sent')),
        error: @json(trans('backpack::support.error')),
        remove: @json(trans('backpack::support.screenshots_remove')),
        tooMany: @json(trans('backpack::support.screenshots_too_many', ['max' => $supportMaxScreenshots])),
        tooLarge: @json(trans('backpack::support.screenshots_too_large', ['size' => $supportScreenshotMaxMb])),
        invalidType: @json(trans('backpack::support.screenshots_invalid_type')),
    };

    // Screenshots selected or pasted so far. Kept outside the file input
    // because pasted images cannot be added to an <input type="file">.
    let screenshots = [];

    // In-flight guard: stops a second submit (double click, Enter pressed
    // twice) from starting while the first request is still running.
    let sending = false;

    // Shows a toast through Noty when Backpack loaded it, else falls back to alert().
    function notify(type, text) {
        if (typeof window.Noty === 'function') {
            new Noty({ type: type, text: text }).show();
        } else if (type === 'error') {
            alert(text);
        }
    }

    // Validates and appends files, rejecting the ones the server would refuse.
    function addScreenshots(files) {
        for (const file of files) {
            if (screenshots.length >= limits.maxFiles) {
                notify('warning', labels.tooMany);
                break;
            }
            if (!limits.types.includes(file.type)) {
                notify('warning', labels.invalidType);
                continue;
            }
            if (file.size > limits.maxBytes) {
                notify('warning', labels.tooLarge);
                continue;
            }
            screenshots.push(file);
        }
        renderScreenshots();
    }

    // Redraws the thumbnails and hides the "add" tile once the limit is reached.
    function renderScreenshots() {
        shotsEl.querySelectorAll('.hb-support-shots__item').forEach(function (item) {
            URL.revokeObjectURL(item.dataset.url);
            item.remove();
        });

        screenshots.forEach(function (file, index) {
            const url = URL.createObjectURL(file);
            const item = document.createElement('div');
            item.className = 'hb-support-shots__item';
            item.dataset.url = url;

            const img = document.createElement('img');
            img.src = url;
            img.alt = file.name;

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'hb-support-shots__remove';
            removeBtn.title = labels.remove;
            removeBtn.setAttribute('aria-label', labels.remove);
            removeBtn.innerHTML = '<i class="la la-times" aria-hidden="true"></i>';
            removeBtn.addEventListener('click', function () {
                screenshots.splice(index, 1);
                renderScreenshots();
            });

            item.append(img, removeBtn);
            shotsEl.insertBefore(item, addShotBtn);
        });

        addShotBtn.classList.toggle('d-none', screenshots.length >= limits.maxFiles);
    }

    function resetForm() {
        form.reset();
        screenshots = [];
        renderScreenshots();
    }

    addShotBtn.addEventListener('click', function () {
        fileInput.click();
    });

    fileInput.addEventListener('change', function () {
        addScreenshots(Array.from(fileInput.files));
        // Clear the input so selecting the same file again still fires "change".
        fileInput.value = '';
    });

    // Ctrl+V of an image inside the modal adds it as a screenshot; plain text
    // pastes are left untouched.
    modalEl.addEventListener('paste', function (event) {
        const images = Array.from(event.clipboardData?.files || []).filter(function (file) {
            return file.type.startsWith('image/');
        });
        if (images.length === 0) {
            return;
        }
        event.preventDefault();
        addScreenshots(images);
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (sending) {
            return;
        }

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        sending = true;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + labels.sending;

        // Multipart body: needed to carry the screenshot files.
        const payload = new FormData();
        payload.append('category', form.category.value);
        payload.append('message', messageEl.value.trim());
        payload.append('page_url', window.location.href);
        screenshots.forEach(function (file) {
            payload.append('screenshots[]', file, file.name);
        });

        try {
            const response = await fetch(@json(route('support.store')), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? form.querySelector('[name="_token"]')?.value,
                },
                body: payload,
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                // Validation errors: show the first one, it is the most specific message.
                const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                notify('error', firstError || data.message || labels.error);
                return;
            }

            resetForm();
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            notify('success', data.message || labels.sent);
        } catch (error) {
            notify('error', labels.error);
        } finally {
            sending = false;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="la la-paper-plane me-1" aria-hidden="true"></i>' + labels.send;
        }
    });
})();
</script>
@endpush
