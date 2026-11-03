@php
use App\Support\Backpack\FilterHelper;
use App\Http\Controllers\Admin\Support\RelationSearchOperation;

$relationName = $relation['name'];
$relFieldParam = RelationSearchOperation::relationFieldParamPrefix($relationName, $fieldInfo['field']);
$relFieldLabel = $fieldInfo['label'] ?? FilterHelper::cleanFieldName($fieldInfo['field']);
$relFieldHasActiveFilter = FilterHelper::hasActiveHasManyRelationFieldFilter($relation, $fieldInfo);
$relFieldType = $fieldInfo['type'] ?? 'text';
$relFieldHasRange = request()->filled($relFieldParam.'_from') || request()->filled($relFieldParam.'_to');
$relTable = (new $relation['model'])->getTable();
$relFieldName = $fieldInfo['field'];
$relStep = $fieldInfo['step'] ?? FilterHelper::getNumberStep(['name' => $relFieldName], $relTable);
$relIsPercentage = $fieldInfo['percentage'] ?? FilterHelper::isPercentageColumn($relFieldName);
$relIsNumberField = $relFieldType === 'number';
$relHasAutocomplete = ! empty($fieldInfo['autocomplete']);
$relCurrentValue = request()->get($relFieldParam);
$relFilterValue = $relIsNumberField
    ? FilterHelper::formatNumberFilterValue($relCurrentValue)
    : $relCurrentValue;
$relFkDisplayValue = $relFieldType === 'fk_autocomplete'
    ? FilterHelper::getForeignKeyFieldDisplayLabel($relTable, $relFieldName, $relCurrentValue)
    : '';
@endphp

<div class="hb-relfield {{ $relFieldType === 'date' ? 'hb-relfield--date' : '' }} {{ $relFieldHasActiveFilter ? 'is-active' : '' }}">
    @if($relFieldType !== 'date')
    <label for="{{ $relFieldParam }}" class="hb-relfield-label">{{ $relFieldLabel }}</label>
    @endif

    @if($relFieldType === 'select')
    <select name="{{ $relFieldParam }}" class="form-select form-select-sm" id="{{ $relFieldParam }}">
        <option value="">{{ trans('backpack::filters.all') }}</option>
        @foreach(($fieldInfo['options'] ?? []) as $optionValue => $optionLabel)
        <option value="{{ $optionValue }}" {{ request()->get($relFieldParam) == $optionValue ? 'selected' : '' }}>{{ $optionLabel }}</option>
        @endforeach
    </select>

    @elseif($relFieldType === 'boolean')
    <select name="{{ $relFieldParam }}" class="form-select form-select-sm" id="{{ $relFieldParam }}">
        <option value="">{{ trans('backpack::filters.all') }}</option>
        <option value="1" {{ request()->get($relFieldParam) === '1' ? 'selected' : '' }}>{{ trans('backpack::filters.yes') }}</option>
        <option value="0" {{ request()->get($relFieldParam) === '0' ? 'selected' : '' }}>{{ trans('backpack::filters.no') }}</option>
    </select>

    @elseif($relFieldType === 'date')
    <div class="hb-relfield-head">
        <label for="{{ $relFieldParam }}" class="hb-relfield-label">{{ $relFieldLabel }}</label>
        <div class="hb-seg hb-relfield-date-type" role="group" aria-label="{{ $relFieldLabel }}">
            <input type="radio" class="btn-check hb-date-type" name="{{ $relFieldParam }}_type"
                   id="{{ $relFieldParam }}_exact" value="exact" {{ !$relFieldHasRange ? 'checked' : '' }}>
            <label class="hb-seg-btn" for="{{ $relFieldParam }}_exact" title="{{ trans('backpack::filters.exact_date') }}">{{ trans('backpack::filters.exact_date_short') }}</label>

            <input type="radio" class="btn-check hb-date-type" name="{{ $relFieldParam }}_type"
                   id="{{ $relFieldParam }}_range" value="range" {{ $relFieldHasRange ? 'checked' : '' }}>
            <label class="hb-seg-btn" for="{{ $relFieldParam }}_range" title="{{ trans('backpack::filters.date_range') }}">{{ trans('backpack::filters.date_range_short') }}</label>
        </div>
    </div>
    <div id="{{ $relFieldParam }}_exact_section" class="hb-date-section" style="{{ $relFieldHasRange ? 'display:none;' : '' }}">
        <input type="date" name="{{ $relFieldParam }}"
               value="{{ request()->get($relFieldParam) }}"
               class="form-control form-control-sm" id="{{ $relFieldParam }}">
    </div>
    <div id="{{ $relFieldParam }}_range_section" class="hb-date-section hb-date-range" style="{{ !$relFieldHasRange ? 'display:none;' : '' }}">
        <input type="date" name="{{ $relFieldParam }}_from"
               value="{{ request()->get($relFieldParam.'_from') }}"
               class="form-control form-control-sm" id="{{ $relFieldParam }}_from"
               aria-label="{{ trans('backpack::filters.from') }}">
        <span class="hb-date-sep">→</span>
        <input type="date" name="{{ $relFieldParam }}_to"
               value="{{ request()->get($relFieldParam.'_to') }}"
               class="form-control form-control-sm" id="{{ $relFieldParam }}_to"
               aria-label="{{ trans('backpack::filters.to') }}">
    </div>

    @elseif($relFieldType === 'fk_autocomplete')
    <div class="position-relative">
        <input type="text"
               class="form-control form-control-sm hb-autocomplete-display"
               data-relation-column="{{ $relFieldName }}"
               data-table="{{ $relTable }}"
               id="{{ $relFieldParam }}_display"
               value="{{ $relFkDisplayValue }}"
               placeholder="{{ trans('backpack::filters.filter_by_field', ['field' => strtolower($relFieldLabel)]) }}"
               autocomplete="off">
        <input type="hidden" name="{{ $relFieldParam }}" id="{{ $relFieldParam }}" value="{{ $relCurrentValue }}">
        <div class="autocomplete-suggestions" id="autocomplete-{{ $relFieldParam }}_display"></div>
    </div>

    @elseif($relIsNumberField)
    <div class="input-group input-group-sm">
        <input type="number"
               name="{{ $relFieldParam }}"
               value="{{ $relFilterValue }}"
               class="form-control form-control-sm hb-filter-number"
               id="{{ $relFieldParam }}"
               step="{{ $relStep }}"
               @if($relIsPercentage) min="1" max="100" @endif>
        @if($relIsPercentage)
        <span class="input-group-text">%</span>
        @endif
    </div>

    @elseif($relHasAutocomplete)
    <div class="position-relative">
        <input type="text"
               name="{{ $relFieldParam }}"
               value="{{ $relFilterValue }}"
               class="form-control form-control-sm hb-autocomplete-value"
               data-column="{{ $relFieldName }}"
               data-table="{{ $relTable }}"
               data-autocomplete-url="{{ backpack_url('autocomplete-values') }}"
               id="{{ $relFieldParam }}"
               autocomplete="off">
        <div class="autocomplete-suggestions" id="autocomplete-{{ $relFieldParam }}"></div>
    </div>

    @else
    <input type="{{ $fieldInfo['input_type'] ?? 'text' }}"
           name="{{ $relFieldParam }}"
           value="{{ $relFilterValue }}"
           class="form-control form-control-sm"
           id="{{ $relFieldParam }}">
    @endif
</div>
