@php
    use App\Services\Translate\TranslateVariableService;

    $variablesByModel = TranslateVariableService::getRegistryGroupedByModel();
    $currentLocale = app()->getLocale();
    $labelKey = 'label_' . $currentLocale;
    $modelLabels = [
        'user'     => 'User',
        'delibera' => 'Delibera',
        'tiraggio' => 'Tiraggio',
        'frontend' => 'Frontend',
    ];

    $editorId     = 'richtext-editor-' . $field['name'];
    $hiddenId     = $field['name'];
    $initialValue = old($field['name'], $entry->{$field['name']} ?? $field['default'] ?? '');
@endphp

@once
    @push('crud_fields_styles')
        <link rel="stylesheet" href="https://cdn.quilljs.com/1.3.7/quill.snow.css">
        <style>
            /* ── Editor sizing & resize ─────────────────────────────── */
            .richtext-quill-wrap .ql-container { background: #fff; }
            .richtext-quill-wrap .ql-editor {
                min-height: 100px;
                resize: vertical;
                overflow-y: auto;
            }

            /* ── Compact variable buttons (tighter than base theme) ─── */
            .translate-vars-picker .translate-var-btn.btn {
                padding: 0.12rem 0.38rem;
                font-size: 0.76rem;
                line-height: 1.2;
            }
            .translate-vars-picker .translate-var-btn .translate-var-name {
                font-size: 0.73rem;
            }

            /* ── Custom-variable row separator ──────────────────────── */
            .translate-var-custom-row {
                margin-top: .45rem;
                padding-top: .35rem;
                border-top: 1px solid #ccd7ed;
            }
        </style>
    @endpush

    @push('crud_fields_scripts')
        <script>
            /* ── Global helpers ─────────────────────────────────────── */
            window.__ql_queue   = window.__ql_queue   || [];
            window.__ql_ready   = window.__ql_ready   || false;
            window.__ql_editors = window.__ql_editors || {};

            /**
             * Strip Quill's <p> wrappers so stored HTML contains only inline /
             * list tags. Paragraph boundaries become <br>.
             *
             *   "<p>Hello</p>"                    → "Hello"
             *   "<p>A</p><p>B</p>"                → "A<br>B"
             *   "<p>A</p><p><br></p><p>B</p>"     → "A<br><br>B"
             *   "<p><strong>B</strong> x</p>"     → "<strong>B</strong> x"
             *   "<ul><li>item</li></ul>"           → "<ul><li>item</li></ul>"
             */
            window.normalizeQuillHtml = function (html) {
                if (!html || html.trim() === '<p><br></p>') { return ''; }
                return html
                    .replace(/<p>([\s\S]*?)<\/p>/g, function (_, inner) {
                        // Empty paragraph: Quill stores <p><br></p> as a blank line.
                        // The inner <br> already IS the line break — don't add a second one.
                        if (inner === '<br>') return '<br>';
                        return inner + '<br>';
                    })
                    // Quill uses &nbsp; (U+00A0) to preserve paragraph-leading/trailing spaces.
                    // Normalise to regular spaces to avoid phantom double-space on reload.
                    .replace(/&nbsp;/g, ' ')
                    .replace(/<br>$/, '')
                    .trim();
            };

            function __ql_init_all() {
                /* ── Extended Link blot: adds target + aria-label support ── */
                const BaseLinkBlot = Quill.import('formats/link');

                class ExtendedLink extends BaseLinkBlot {
                    static create(value) {
                        const node = super.create(
                            typeof value === 'object' ? (value.href || '') : value
                        );
                        if (typeof value === 'object') {
                            if (value.target) {
                                node.setAttribute('target', value.target);
                                if (value.target === '_blank') {
                                    node.setAttribute('rel', 'noopener noreferrer');
                                }
                            } else {
                                node.removeAttribute('target');
                                node.removeAttribute('rel');
                            }
                            if (value.ariaLabel) {
                                node.setAttribute('aria-label', value.ariaLabel);
                            } else {
                                node.removeAttribute('aria-label');
                            }
                        }
                        return node;
                    }

                    static formats(node) {
                        return {
                            href:      node.getAttribute('href'),
                            target:    node.getAttribute('target')    || null,
                            ariaLabel: node.getAttribute('aria-label') || null,
                        };
                    }
                }

                ExtendedLink.blotName = 'link';
                ExtendedLink.tagName  = 'A';
                Quill.register(ExtendedLink, true);

                window.__ql_ready = true;
                window.__ql_queue.forEach(fn => fn());
                window.__ql_queue = [];
            }

            /* ── Shared click handler for variable / custom-var buttons ── */
            if (!window.__ql_insert_listener) {
                window.__ql_insert_listener = true;

                document.addEventListener('click', function (event) {
                    const btn = event.target.closest('.translate-var-btn');
                    if (!btn) { return; }

                    event.preventDefault();

                    const target  = btn.dataset.target;
                    let   varText = btn.dataset.var;

                    if (btn.dataset.custom === '1') {
                        // Custom vars always belong to the frontend model — only ask for the field name.
                        const field = (window.prompt('Nome del campo frontend (es. tiraggio_percentuale_trattenuta):') || '').trim().replace(/[^a-zA-Z0-9_]/g, '_');
                        if (!field) { return; }
                        varText = '{$frontend.' + field + '}';
                    }

                    if (!varText) { return; }

                    const quill = window.__ql_editors[target];
                    if (quill) {
                        // Use the last saved range: clicking outside the editor clears focus
                        // and getSelection() returns null, so we rely on the range saved by
                        // the selection-change handler below.
                        const range = quill._lastRange || { index: quill.getLength() - 1, length: 0 };

                        // Replace selected text (if any), then insert the variable.
                        if (range.length > 0) {
                            quill.deleteText(range.index, range.length, 'user');
                        }
                        quill.insertText(range.index, varText, 'user');
                        quill.setSelection(range.index + varText.length, 0);
                    }
                });
            }
        </script>
        <script src="https://cdn.quilljs.com/1.3.7/quill.min.js" onload="__ql_init_all()"></script>
    @endpush
@endonce

@include('crud::fields.inc.wrapper_start')

    <label>{!! $field['label'] !!}</label>

    {{-- Variable picker ────────────────────────────────────────────── --}}
    @if(! empty($variablesByModel))
        <div class="translate-vars-picker mb-2">
            <details class="translate-vars-accordion">
                <summary class="translate-vars-summary">
                    <i class="la la-tags"></i>
                    {{ __('translate.vars_picker_hint') }}
                </summary>

                <div class="translate-vars-body">
                    @foreach($variablesByModel as $modelKey => $variables)
                        <div class="translate-vars-group">
                            <span class="translate-vars-group-label">
                                {{ $modelLabels[$modelKey] ?? ucfirst($modelKey) }}
                            </span>

                            <div class="translate-vars-group-list">
                                @foreach($variables as $variableKey => $variableDefinition)
                                    <button
                                        type="button"
                                        class="btn btn-xs translate-var-btn"
                                        data-var="{{ '{$' . $variableKey . '}' }}"
                                        data-target="{{ $field['name'] }}"
                                        title="{{ '{$' . $variableKey . '}' }}@if(! empty($variableDefinition['description'])) — {{ $variableDefinition['description'] }}@endif"
                                    >
                                        <span class="translate-var-name">
                                            {{ $variableDefinition[$labelKey] ?? $variableDefinition['label_it'] ?? $variableKey }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    {{-- Custom variable: full-width row below the group grid --}}
                    <div class="translate-var-custom-row">
                        <button
                            type="button"
                            class="btn btn-xs translate-var-btn"
                            data-custom="1"
                            data-target="{{ $field['name'] }}"
                            title="Inserisci una variabile custom (es. {$frontend.tiraggio_percentuale_trattenuta})"
                        >
                            <i class="la la-plus-circle"></i>
                            <span class="translate-var-name">Variabile custom…</span>
                        </button>
                    </div>
                </div>
            </details>
        </div>
    @endif

    {{-- Quill editor ───────────────────────────────────────────────── --}}
    <div class="richtext-quill-wrap">
        <div id="{{ $editorId }}"></div>
    </div>

    {{-- Hidden field submitted with the form --}}
    <textarea
        name="{{ $field['name'] }}"
        id="{{ $hiddenId }}"
        style="display:none"
        @class(['form-control', 'is-invalid' => $errors->has($field['name'])])
    >{{ $initialValue }}</textarea>

    @include('crud::fields.inc.translatable_icon')

    @error($field['name'])
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror

    @if(session()->has('translate_warnings.' . $field['name']))
        <div class="text-warning small mt-1">
            <i class="la la-exclamation-triangle"></i> {{ session('translate_warnings.' . $field['name']) }}
        </div>
    @endif

    @if(! empty($field['hint']))
        <p class="help-block">{{ $field['hint'] }}</p>
    @endif

@include('crud::fields.inc.wrapper_end')

@push('crud_fields_scripts')
    <script>
        (function () {
            const editorId  = {{ Js::from($editorId) }};
            const hiddenId  = {{ Js::from($hiddenId) }};
            const initValue = {{ Js::from($initialValue) }};

            function init() {
                const container = document.getElementById(editorId);
                const hidden    = document.getElementById(hiddenId);

                if (!container || !hidden) { return; }
                if (container.__quill_initialized) { return; }
                container.__quill_initialized = true;

                const quill = new Quill(container, {
                    theme: 'snow',
                    formats: ['bold', 'italic', 'underline', 'strike', 'link', 'list'],
                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline', 'strike'],
                            ['link'],
                            [{ list: 'ordered' }, { list: 'bullet' }],
                            ['clean'],
                        ],
                    },
                });

                /* ── Custom link dialog (URL + target blank + aria-label) ── */
                quill.getModule('toolbar').addHandler('link', function (value) {
                    if (!value) {
                        quill.format('link', false);
                        return;
                    }

                    const range = quill.getSelection();
                    if (!range || range.length === 0) {
                        alert('Seleziona il testo da collegare prima di inserire il link.');
                        return;
                    }

                    const url = (window.prompt('URL del link:') || '').trim();
                    if (!url) { return; }

                    const targetBlank = window.confirm('Aprire in nuova scheda? (target="_blank")');
                    const ariaLabel   = (window.prompt('Aria-label accessibilità (opzionale):') || '').trim();

                    quill.format('link', {
                        href:      url,
                        target:    targetBlank ? '_blank' : null,
                        ariaLabel: ariaLabel   || null,
                    });
                });

                /* ── Load existing value ─────────────────────────────── */
                if (initValue && initValue.trim()) {
                    quill.clipboard.dangerouslyPasteHTML(0, initValue.trim());
                }

                /* ── Sync to hidden textarea on every change ─────────── */
                quill.on('text-change', function () {
                    hidden.value = normalizeQuillHtml(quill.root.innerHTML);
                    hidden.dispatchEvent(new Event('change', { bubbles: true }));
                });

                /* ── Persist last selection so variable buttons can use it ── */
                quill.on('selection-change', function (range) {
                    if (range) { quill._lastRange = range; }
                });

                window.__ql_editors[hiddenId] = quill;
            }

            if (window.__ql_ready) {
                init();
            } else {
                window.__ql_queue = window.__ql_queue || [];
                window.__ql_queue.push(init);
            }
        }());
    </script>
@endpush
