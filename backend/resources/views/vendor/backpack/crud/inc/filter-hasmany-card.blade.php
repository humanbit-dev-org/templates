@php
use App\Support\Backpack\FilterHelper;
use Illuminate\Support\Str;
@endphp

@if(isset($relation['searchable_keys']))
@php
$relationLabel = ucfirst(str_replace('_', ' ', $relation['name']));
$firstKey   = $relation['searchable_keys'][0];
$filterKey  = $relation['name'].'_'.$firstKey['field'];
$currentValue = request()->get($filterKey);
$countOp  = request()->get($relation['name'].'_count_op');
$countVal = request()->get($relation['name'].'_count');

$hasManyRelationInfo = FilterHelper::getRelationFilterInfo(null, $crud, true, $relation);
$searchableKeysLabel = FilterHelper::getHasManySearchableKeysLabel($relation);
$placeholderText = trans('backpack::filters.relation_search_placeholder', [
    'keys' => strtolower($searchableKeysLabel),
    'related' => strtolower($hasManyRelationInfo['related_table_singular']),
    'current' => strtolower($hasManyRelationInfo['current_table_singular']),
]);

$displayValue = $currentValue;
if ($currentValue) {
    $relatedModelClass = $relation['model'] ?? ('App\Models\\'.Str::studly($relation['name']));
    if (class_exists($relatedModelClass)) {
        $displayValue = FilterHelper::getHasManyFilterDisplayValue($relatedModelClass, $currentValue);
    }
}

$searchActive   = request()->filled($filterKey);
$countActive    = $countOp !== null && $countOp !== '' && $countVal !== null && $countVal !== '' && is_numeric($countVal);
$presenceActive = request()->get($filterKey.'_not_empty') === '1' || request()->get($filterKey.'_empty') === '1';

$fieldsTabCount = 0;
$criteriaChips = [];
foreach ($relation['filterable_fields'] ?? [] as $fieldInfo) {
    if (FilterHelper::hasActiveHasManyRelationFieldFilter($relation, $fieldInfo)) {
        $fieldsTabCount++;
        $relFieldParam = \App\Http\Controllers\Admin\Support\RelationSearchOperation::relationFieldParamPrefix(
            $relation['name'],
            $fieldInfo['field']
        );
        $fieldLabel = $fieldInfo['label'] ?? FilterHelper::cleanFieldName($fieldInfo['field']);
        $fieldValue = request()->get($relFieldParam);
        if ($fieldValue !== null && $fieldValue !== '') {
            $criteriaChips[] = $fieldLabel.': '.$fieldValue;
        } else {
            $criteriaChips[] = $fieldLabel;
        }
    }
}

$activeFilterCount = FilterHelper::countHasManyRelationActiveFilters($relation);
$rowActive = $activeFilterCount > 0;

if ($searchActive) {
    $criteriaChips[] = trans('backpack::filters.relation_mode_search').': '.$displayValue;
}
if ($countActive) {
    $countOpLabels = [
        'eq' => trans('backpack::filters.count_exactly'),
        'gt' => trans('backpack::filters.count_more_than'),
        'lt' => trans('backpack::filters.count_less_than'),
    ];
    $criteriaChips[] = trans('backpack::filters.relation_mode_count').': '.($countOpLabels[$countOp] ?? $countOp).' '.$countVal;
}
if ($presenceActive) {
    $criteriaChips[] = request()->get($filterKey.'_not_empty') === '1'
        ? trans('backpack::filters.has_related')
        : trans('backpack::filters.has_not_related');
}

$openMode = null;
if ($fieldsTabCount > 0) {
    $openMode = 'fields';
} elseif ($countActive) {
    $openMode = 'count';
} elseif ($presenceActive) {
    $openMode = 'presence';
} elseif ($searchActive) {
    $openMode = 'search';
}

$cardSummary = !empty($criteriaChips)
    ? implode(' · ', $criteriaChips)
    : trans('backpack::filters.hasmany_relation_hint_short', ['related' => strtolower($relationLabel)]);

$frowGroup = $frowGroup ?? trans('backpack::filters.relations');
@endphp

<div class="hb-frow hb-frow--card {{ $rowActive ? 'is-active' : 'd-none' }}"
     data-frow-key="rel:{{ $relation['name'] }}"
     data-frow-label="{{ $relationLabel }}"
     data-frow-group="{{ $frowGroup }}">

  <div class="hb-relcard {{ $rowActive ? 'is-filtering' : '' }}" data-relation-card="{{ $relation['name'] }}">

    <div class="hb-relcard-head">
      <div class="hb-relcard-title-wrap">
        <span class="hb-relcard-title">{{ $relationLabel }}</span>
        @if(count($criteriaChips) > 0)
        <div class="hb-relcard-chips">
          @foreach($criteriaChips as $chip)
          <span class="hb-relcard-chip">{{ $chip }}</span>
          @if(!$loop->last)
          <span class="hb-relcard-chip-and">AND</span>
          @endif
          @endforeach
        </div>
        @else
        <span class="hb-relcard-summary">{{ $cardSummary }}</span>
        @endif
        <p class="hb-relcard-hint">{{ trans('backpack::filters.relation_combine_hint') }}</p>
      </div>
      <div class="hb-relcard-head-actions">
        <button type="button" class="hb-frow-remove" aria-label="{{ trans('backpack::filters.remove_filter') }}">×</button>
      </div>
    </div>

    <div class="hb-relcard-sections">

      <div class="hb-relsection {{ $openMode === 'search' ? 'is-open' : '' }} {{ $searchActive ? 'is-active-criteria' : '' }}" data-mode="search">
        <button type="button" class="hb-relsection-head" aria-expanded="{{ $openMode === 'search' ? 'true' : 'false' }}">
          <span>{{ trans('backpack::filters.relation_mode_search') }}</span>
          <span class="hb-relsection-dot {{ $searchActive ? '' : 'd-none' }}"></span>
          <i class="la la-angle-down hb-relsection-chevron"></i>
        </button>
        <div class="hb-relsection-body hb-relpanel" data-mode="search">
          <div class="position-relative">
            <input type="text"
                   class="form-control form-control-sm hb-autocomplete-display hb-autocomplete-hasmany"
                   data-relation="{{ $relation['name'] }}"
                   data-model="{{ is_string($crud->model) ? $crud->model : get_class($crud->model) }}"
                   data-searchable-keys="{{ base64_encode(json_encode($relation['searchable_keys'])) }}"
                   id="{{ $filterKey }}_display"
                   value="{{ $displayValue }}"
                   placeholder="{{ $placeholderText }}">
            <input type="hidden" name="{{ $filterKey }}" id="{{ $filterKey }}" value="{{ $currentValue }}">
            <div class="autocomplete-suggestions" id="autocomplete-{{ $filterKey }}_display"></div>
          </div>
        </div>
      </div>

      <div class="hb-relsection {{ $openMode === 'count' ? 'is-open' : '' }} {{ $countActive ? 'is-active-criteria' : '' }}" data-mode="count">
        <button type="button" class="hb-relsection-head" aria-expanded="{{ $openMode === 'count' ? 'true' : 'false' }}">
          <span>{{ trans('backpack::filters.relation_mode_count') }}</span>
          <span class="hb-relsection-dot {{ $countActive ? '' : 'd-none' }}"></span>
          <i class="la la-angle-down hb-relsection-chevron"></i>
        </button>
        <div class="hb-relsection-body hb-relpanel" data-mode="count">
          <div class="hb-relcount">
            <select name="{{ $relation['name'] }}_count_op" class="form-select form-select-sm">
              <option value="eq" {{ ($countOp ?? '') == 'eq' ? 'selected' : '' }}>{{ trans('backpack::filters.count_exactly') }}</option>
              <option value="gt" {{ ($countOp ?? '') == 'gt' ? 'selected' : '' }}>{{ trans('backpack::filters.count_more_than') }}</option>
              <option value="lt" {{ ($countOp ?? '') == 'lt' ? 'selected' : '' }}>{{ trans('backpack::filters.count_less_than') }}</option>
            </select>
            <input type="number" name="{{ $relation['name'] }}_count" min="0" step="1"
                   value="{{ $countVal ?? '' }}" class="form-control form-control-sm" placeholder="n">
            <span class="hb-relcount-hint">{{ strtolower($relationLabel) }}</span>
          </div>
        </div>
      </div>

      <div class="hb-relsection {{ $openMode === 'presence' ? 'is-open' : '' }} {{ $presenceActive ? 'is-active-criteria' : '' }}" data-mode="presence">
        <button type="button" class="hb-relsection-head" aria-expanded="{{ $openMode === 'presence' ? 'true' : 'false' }}">
          <span>{{ trans('backpack::filters.relation_mode_presence') }}</span>
          <span class="hb-relsection-dot {{ $presenceActive ? '' : 'd-none' }}"></span>
          <i class="la la-angle-down hb-relsection-chevron"></i>
        </button>
        <div class="hb-relsection-body hb-relpanel" data-mode="presence">
          <div class="hb-seg" role="group">
            <input type="radio" class="btn-check" name="{{ $filterKey }}_empty_filter"
                   id="{{ $filterKey }}_presence_all" value=""
                   {{ !request()->get($filterKey.'_not_empty') && !request()->get($filterKey.'_empty') ? 'checked' : '' }}>
            <label class="hb-seg-btn" for="{{ $filterKey }}_presence_all">{{ trans('backpack::filters.all') }}</label>

            <input type="radio" class="btn-check" name="{{ $filterKey }}_empty_filter"
                   id="{{ $filterKey }}_presence_not_empty" value="not_empty"
                   {{ request()->get($filterKey.'_not_empty') == '1' ? 'checked' : '' }}>
            <label class="hb-seg-btn hb-seg-btn--yes" for="{{ $filterKey }}_presence_not_empty">{{ trans('backpack::filters.has_related') }}</label>

            <input type="radio" class="btn-check" name="{{ $filterKey }}_empty_filter"
                   id="{{ $filterKey }}_presence_empty" value="empty"
                   {{ request()->get($filterKey.'_empty') == '1' ? 'checked' : '' }}>
            <label class="hb-seg-btn hb-seg-btn--no" for="{{ $filterKey }}_presence_empty">{{ trans('backpack::filters.has_not_related') }}</label>
          </div>
        </div>
      </div>

      @if(!empty($relation['filterable_fields']))
      <div class="hb-relsection {{ $openMode === 'fields' ? 'is-open' : '' }} {{ $fieldsTabCount > 0 ? 'is-active-criteria' : '' }}" data-mode="fields">
        <button type="button" class="hb-relsection-head" aria-expanded="{{ $openMode === 'fields' ? 'true' : 'false' }}">
          <span>{{ trans('backpack::filters.relation_mode_fields') }}</span>
          <span class="hb-relsection-dot {{ $fieldsTabCount > 0 ? '' : 'd-none' }}"></span>
          <i class="la la-angle-down hb-relsection-chevron"></i>
        </button>
        <div class="hb-relsection-body hb-relpanel" data-mode="fields">
          <div class="hb-relfields">
            @foreach($relation['filterable_fields'] as $fieldInfo)
            @include('crud::inc.filter-relation-field', ['relation' => $relation, 'fieldInfo' => $fieldInfo])
            @endforeach
          </div>
        </div>
      </div>
      @endif

    </div>
  </div>
</div>
@endif
