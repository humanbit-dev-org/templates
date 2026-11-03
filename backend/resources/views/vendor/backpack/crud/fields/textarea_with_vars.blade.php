@php
    use App\Services\Translate\TranslateVariableService;

    $variablesByModel = TranslateVariableService::getRegistryGroupedByModel();
    $currentLocale = app()->getLocale();
    $labelKey = 'label_' . $currentLocale;
    $modelLabels = [
        'user' => 'User',
        'delibera' => 'Delibera',
        'tiraggio' => 'Tiraggio',
    ];
@endphp

@include('crud::fields.inc.wrapper_start')

    <label>{!! $field['label'] !!}</label>

    @if(! empty($variablesByModel))
        <div class="translate-vars-picker mb-2 p-2 border rounded bg-light">
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
                </div>
            </details>
        </div>
    @endif

    <textarea
        name="{{ $field['name'] }}"
        id="{{ $field['name'] }}"
        rows="{{ $field['rows'] ?? 5 }}"
        @class(['form-control', 'translate-vars-textarea', 'is-invalid' => $errors->has($field['name'])])
        @if(! empty($field['placeholder'])) placeholder="{{ $field['placeholder'] }}" @endif
        @if(! empty($field['attributes'])) @foreach($field['attributes'] as $attr => $value) {{ $attr }}="{{ $value }}" @endforeach @endif
    >{{ old($field['name'], $entry->{$field['name']} ?? $field['default'] ?? '') }}</textarea>

    @include('crud::fields.inc.translatable_icon')

    @error($field['name'])
        <div class="invalid-feedback">{{ $message }}</div>
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
            'use strict';

            if (window.__translateVarsPickerInitialized === true) {
                return;
            }

            window.__translateVarsPickerInitialized = true;

            function insertAtCursor(textarea, text) {
                const start = textarea.selectionStart ?? textarea.value.length;
                const end = textarea.selectionEnd ?? textarea.value.length;

                textarea.value =
                    textarea.value.substring(0, start) +
                    text +
                    textarea.value.substring(end);

                const cursorPosition = start + text.length;
                textarea.setSelectionRange(cursorPosition, cursorPosition);
                textarea.focus();
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
            }

            document.addEventListener('click', function (event) {
                const button = event.target.closest('.translate-var-btn');
                if (!button) {
                    return;
                }

                event.preventDefault();

                const placeholder = button.dataset.var;
                const target = button.dataset.target;
                const textarea = document.getElementById(target);

                if (!textarea || !placeholder) {
                    return;
                }

                insertAtCursor(textarea, placeholder);
            });
        })();
    </script>
@endpush
