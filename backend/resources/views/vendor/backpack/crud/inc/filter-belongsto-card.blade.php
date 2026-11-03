@php
use App\Support\Backpack\FilterHelper;

$name = $column['name'];
$relationMeta = $singularRelationsByFk[$name] ?? null;
$currentValue = request()->get($name);
$rowActive = FilterHelper::countBelongsToRelationActiveFilters($name, $relationMeta) > 0;

$relationLabel = $column['label'] ?? FilterHelper::cleanFieldName($name);
$relationInfo = FilterHelper::getRelationFilterInfo($column, $crud, false);
$placeholderText = $relationInfo['placeholder'] ?? (
    'cerca '.strtolower($relationInfo['primary_key'])
    .' di '.strtolower($relationInfo['related_table_singular'])
);

// Built only when a select can show them: over 50 related rows it is the
// autocomplete, and building them for every user was what exhausted memory.
$relationOptions = FilterHelper::getRelationOptionsForSelect($column, $crud, 50);
$useSelect = $relationOptions !== null && count($relationOptions) <= 50;

$displayValue = $currentValue;
if ($currentValue) {
    $displayValue = FilterHelper::getFilterDisplayValue($name, $currentValue, collect([$column]), $crud);
}

$frowGroup = $frowGroup ?? trans('backpack::filters.relations');
@endphp

{{-- BelongsTo: rendered as a standard filter row, no nested accordion --}}
<div class="hb-frow hb-frow--relation {{ $rowActive ? 'is-active' : 'd-none' }}"
     data-frow-key="{{ $name }}"
     data-frow-label="{{ $relationLabel }}"
     data-frow-group="{{ $frowGroup }}">

  <div class="hb-frow-head">
    <label class="hb-frow-label" for="{{ $name }}">
      <span class="hb-frow-relmark" title="{{ trans('backpack::filters.belongsto_relation_hint_short', ['related' => strtolower($relationLabel)]) }}">
        <i class="la la-link"></i>
      </span>
      {{ $relationLabel }}
    </label>
    <div class="hb-frow-tools">
      <div class="hb-seg" role="group">
        <input type="radio" class="btn-check" name="{{ $name }}_empty_filter" id="{{ $name }}_all" value=""
               {{ !request()->get($name.'_not_empty') && !request()->get($name.'_empty') ? 'checked' : '' }}>
        <label class="hb-seg-btn" for="{{ $name }}_all">{{ trans('backpack::filters.all') }}</label>

        <input type="radio" class="btn-check" name="{{ $name }}_empty_filter" id="{{ $name }}_not_empty" value="not_empty"
               {{ request()->get($name.'_not_empty') == '1' ? 'checked' : '' }}>
        <label class="hb-seg-btn hb-seg-btn--yes" for="{{ $name }}_not_empty">{{ trans('backpack::filters.not_empty') }}</label>

        <input type="radio" class="btn-check" name="{{ $name }}_empty_filter" id="{{ $name }}_empty" value="empty"
               {{ request()->get($name.'_empty') == '1' ? 'checked' : '' }}>
        <label class="hb-seg-btn hb-seg-btn--no" for="{{ $name }}_empty">{{ trans('backpack::filters.empty') }}</label>
      </div>
      <button type="button" class="hb-frow-remove" aria-label="{{ trans('backpack::filters.remove_filter') }}">×</button>
    </div>
  </div>

  <p class="hb-presence-hint d-none">{{ trans('backpack::filters.presence_value_disabled') }}</p>
  <div class="hb-frow-body position-relative">
    @if($useSelect)
    <select name="{{ $name }}" id="{{ $name }}" class="form-select form-select-sm">
      <option value="">{{ trans('backpack::filters.all') }}</option>
      @foreach ($relationOptions as $optValue => $optLabel)
      <option value="{{ $optValue }}" {{ $currentValue == $optValue ? 'selected' : '' }}>{{ $optLabel }}</option>
      @endforeach
    </select>
    @else
    <input type="text"
           class="form-control form-control-sm hb-autocomplete-display"
           data-relation-column="{{ $name }}"
           data-table="{{ $crud->model->getTable() }}"
           id="{{ $name }}_display"
           value="{{ $displayValue }}"
           placeholder="{{ $placeholderText }}">
    <input type="hidden" name="{{ $name }}" id="{{ $name }}" value="{{ $currentValue }}">
    <div class="autocomplete-suggestions" id="autocomplete-{{ $name }}_display"></div>
    @endif

    @if($relationMeta && !empty($relationMeta['filterable_fields']))
    <div class="hb-relfields mt-3">
      <p class="hb-relfields-title mb-2">{{ trans('backpack::filters.relation_mode_fields') }}</p>
      @foreach($relationMeta['filterable_fields'] as $fieldInfo)
      @include('crud::inc.filter-relation-field', ['relation' => $relationMeta, 'fieldInfo' => $fieldInfo])
      @endforeach
    </div>
    @endif
  </div>
</div>