@php
use App\Support\Backpack\FilterHelper;
use Illuminate\Support\Str;

// Get filter configuration using helper (unchanged backend contract)
$filterConfig = FilterHelper::getFilterConfiguration($crud);
extract($filterConfig);

$activeFilters = FilterHelper::getActiveFilters($columns);

/*
|--------------------------------------------------------------------------
| Build plain-language pills for every active filter
|--------------------------------------------------------------------------
| Each pill: ['label' => ..., 'value' => ..., 'remove' => removal URL]
| Param names are NOT changed: removal URLs are built with request()->except()
*/
$pills = [];
$currentUrl = request()->url();

$buildRemoveUrl = function (array $exceptKeys) use ($currentUrl) {
    $params = request()->except(array_merge(['page'], $exceptKeys));
    $params['persistent-table'] = '1';
    return $currentUrl . '?' . http_build_query($params);
};

foreach ($activeFilters as $key => $value) {
    // Skip UI-only leftovers
    if (str_ends_with($key, '_type') || $key === 'persistent-table') {
        continue;
    }

    // 1) Relation count filter (relname_count_op)
    if (str_ends_with($key, '_count_op')) {
        $relName = str_replace('_count_op', '', $key);
        $pills[] = [
            'label' => FilterHelper::cleanFieldName($relName),
            'value' => FilterHelper::getFilterDisplayValue($key, $value, $columns, $crud),
            'remove' => $buildRemoveUrl([$relName.'_count_op', $relName.'_count', $relName.'_filter_mode']),
            'kind' => 'relation',
            'cluster' => 'rel:'.$relName,
        ];
        continue;
    }

    // 2) HasMany relation field filter (relname_rel_field[...])
    $relFieldCtx = FilterHelper::parseHasManyRelationFieldFilterKey($key, $relationsWithFieldFilters);
    if ($relFieldCtx) {
        $pills[] = [
            'label' => FilterHelper::getHasManyRelationFieldFilterLabel($relFieldCtx),
            'value' => FilterHelper::getHasManyRelationFieldFilterDisplayValue($key, $value, $relFieldCtx),
            'remove' => $currentUrl . '?' . http_build_query(array_merge(
                FilterHelper::getHasManyRelationFieldFilterRemoveParams($key, $relFieldCtx),
                ['persistent-table' => '1']
            )),
            'kind' => 'relation',
            'cluster' => 'rel:'.$relFieldCtx['relation_name'],
        ];
        continue;
    }

    // 3) HasMany search / presence keys (relname_field, relname_field_not_empty, ...)
    $matchedHasMany = null;
    $baseKey = preg_replace('/_(not_empty|empty)$/', '', $key);
    foreach ($hasManyRelations as $rel) {
        foreach (($rel['searchable_keys'] ?? []) as $keyInfo) {
            if ($baseKey === $rel['name'].'_'.$keyInfo['field']) {
                $matchedHasMany = $rel;
                break 2;
            }
        }
    }
    if ($matchedHasMany) {
        $relLabel = FilterHelper::cleanFieldName($matchedHasMany['name']);
        if (str_ends_with($key, '_not_empty')) {
            $pillValue = trans('backpack::filters.has_related');
        } elseif (str_ends_with($key, '_empty')) {
            $pillValue = trans('backpack::filters.has_not_related');
        } else {
            $relModelClass = $matchedHasMany['model'] ?? ('App\\Models\\'.Str::studly($matchedHasMany['name']));
            $pillValue = class_exists($relModelClass)
                ? FilterHelper::getHasManyFilterDisplayValue($relModelClass, $value)
                : (string) $value;
        }
        $pills[] = [
            'label' => $relLabel,
            'value' => $pillValue,
            'remove' => $buildRemoveUrl([$baseKey, $baseKey.'_not_empty', $baseKey.'_empty', $matchedHasMany['name'].'_filter_mode']),
            'kind' => 'relation',
            'cluster' => 'rel:'.$matchedHasMany['name'],
        ];
        continue;
    }

    // 4) not_empty / empty on plain columns or belongsTo
    if (str_ends_with($key, '_not_empty') || str_ends_with($key, '_empty')) {
        $col = $columns->firstWhere('name', $baseKey);
        $label = $col['label'] ?? FilterHelper::cleanFieldName($baseKey, $baseKey === $primaryKey);
        $pills[] = [
            'label' => $label,
            'value' => str_ends_with($key, '_not_empty')
                ? trans('backpack::filters.not_empty')
                : trans('backpack::filters.empty'),
            'remove' => $buildRemoveUrl([$baseKey.'_not_empty', $baseKey.'_empty']),
            'kind' => 'field',
        ];
        continue;
    }

    // 5) Date range marker from getActiveFilters (key === value === baseField)
    if ($key === $value && (request()->filled($key.'_from') || request()->filled($key.'_to'))) {
        // getFilterDisplayValue returns "Label: da X - Y" already
        $pills[] = [
            'label' => '',
            'value' => FilterHelper::getFilterDisplayValue($key, $value, $columns, $crud),
            'remove' => $buildRemoveUrl([$key, $key.'_from', $key.'_to', $key.'_type']),
            'kind' => 'field',
        ];
        continue;
    }

    // 6) Plain value / select / boolean / belongsTo filters
    $col = $columns->firstWhere('name', $key === 'page_filter' ? 'page' : $key);
    $label = $col['label'] ?? null;
    if (! $label) {
        $relatedCtx = FilterHelper::resolveRelatedFilterContext($crud, $key);
        $label = $relatedCtx['label'] ?? FilterHelper::cleanFieldName($key, $key === $primaryKey);
    }
    $pills[] = [
        'label' => $label,
        'value' => FilterHelper::getFilterDisplayValue($key, $value, $columns, $crud),
        'remove' => $buildRemoveUrl([$key, $key.'_from', $key.'_to', $key.'_type']),
        'kind' => FilterHelper::isForeignKeyColumn($key, $tableName, $primaryKey) ? 'relation' : 'field',
    ];
}

$resetUrl = $currentUrl . '?persistent-table=1';

// Group HasMany relation pills that share AND semantics into visual clusters
$clusters = [];
foreach ($pills as $pill) {
    if (! empty($pill['cluster'])) {
        $clusters[$pill['cluster']][] = $pill;
    }
}

$pillRenderItems = [];
$seenClusters = [];
foreach ($pills as $pill) {
    if (! empty($pill['cluster'])) {
        if (isset($seenClusters[$pill['cluster']])) {
            continue;
        }
        $seenClusters[$pill['cluster']] = true;
        $clusterPills = $clusters[$pill['cluster']];
        if (count($clusterPills) > 1) {
            $pillRenderItems[] = ['type' => 'cluster', 'pills' => $clusterPills];
        } else {
            $pillRenderItems[] = ['type' => 'single', 'pill' => $clusterPills[0]];
        }
        continue;
    }

    $pillRenderItems[] = ['type' => 'single', 'pill' => $pill];
}

// Group labels reuse existing translation keys
$groupValue    = trans('backpack::filters.value_filters');
$groupStatus   = trans('backpack::filters.status');
$groupSelect   = trans('backpack::filters.select_filters');
$groupDate     = trans('backpack::filters.date_filters');
$groupFile     = trans('backpack::filters.uploaded_files');
$groupRelation = trans('backpack::filters.relations');
$groupAdvanced = trans('backpack::filters.advanced_filters');
@endphp

{{-- Active filters: hidden when a preset is active (redundant with preset pills) --}}
{{-- @if(count($pills) && !\App\Support\Backpack\PresetFilterHandler::getActivePreset())
<div class="hb-active-strip" aria-label="{{ trans('backpack::filters.active_filters') }}">
  <span class="hb-active-strip-title">{{ trans('backpack::filters.active_filters') }}</span>
  <div class="hb-active-pills">
    @foreach($pillRenderItems as $item)
    @if($item['type'] === 'cluster')
    <span class="hb-pill-chain" role="group">
      @foreach($item['pills'] as $pill)
      @php
      $segmentLabel = $pill['label'];
      if (!$loop->first && str_contains($segmentLabel, ' · ')) {
          $segmentLabel = explode(' · ', $segmentLabel, 2)[1];
      }
      @endphp
      @if(!$loop->first)
      <span class="hb-pill-and" aria-hidden="true">AND</span>
      @endif
      <a href="{{ $pill['remove'] }}" class="hb-pill hb-pill--relation"
         title="{{ trans('backpack::filters.remove_filter') }}" aria-label="{{ trans('backpack::filters.remove_filter') }}">
        @if($segmentLabel !== '')
        <span class="hb-pill-label">{{ $segmentLabel }}</span>
        @endif
        <span class="hb-pill-value">{{ Str::limit($pill['value'], 48) }}</span>
        <span class="hb-pill-x" aria-hidden="true">×</span>
      </a>
      @endforeach
    </span>
    @else
    @php $pill = $item['pill']; @endphp
    <a href="{{ $pill['remove'] }}" class="hb-pill {{ $pill['kind'] === 'relation' ? 'hb-pill--relation' : '' }}"
       title="{{ trans('backpack::filters.remove_filter') }}" aria-label="{{ trans('backpack::filters.remove_filter') }}">
      @if($pill['label'] !== '')
      <span class="hb-pill-label">{{ $pill['label'] }}</span>
      @endif
      <span class="hb-pill-value">{{ Str::limit($pill['value'], 48) }}</span>
      <span class="hb-pill-x" aria-hidden="true">×</span>
    </a>
    @endif
    @endforeach
  </div>
</div>
@endif --}}

<!-- ============================================================ -->
<!-- Filter Panel — composable rule-builder redesign              -->
<!-- ============================================================ -->
<div class="collapse" id="filterPanel">
  <form action="" method="GET" id="filterForm" class="hb-filters mt-2" autocomplete="off">

    {{-- ── Toolbar: add-filter + search ─────────────────────── --}}
    <div class="hb-filters-toolbar">
      <div class="hb-addfilter">
        <button type="button" class="hb-addfilter-btn" id="hbAddFilterBtn"
                aria-haspopup="true" aria-expanded="false" aria-controls="hbAddFilterPop">
          <i class="la la-plus"></i> {{ trans('backpack::filters.add_filter') }}
        </button>

        {{-- Command-palette style popover, populated by JS from the rows below --}}
        <div class="hb-addfilter-pop d-none" id="hbAddFilterPop" role="menu">
          <input type="text" class="hb-addfilter-search" id="hbAddFilterSearch"
                 placeholder="{{ trans('backpack::filters.filter_search_placeholder') }}">
          <div class="hb-addfilter-list" id="hbAddFilterList"></div>
          <div class="hb-addfilter-empty d-none" id="hbAddFilterEmpty">
            {{ trans('backpack::filters.no_filter_found') }}
          </div>
        </div>
      </div>

      <button type="submit" class="hb-search-btn ms-auto">
        <i class="la la-search"></i> {{ trans('backpack::crud.search') }}
      </button>
    </div>

    {{-- ── Filter rows: compact grid + relation grid (separate to avoid layout holes) ── --}}
    <div class="hb-frows-wrap">
    <div class="hb-frows hb-frows--compact" id="hbFilterRows">

      {{-- TEXT / VALUE FILTERS ------------------------------------------------ --}}
      @foreach ($textColumns as $column)
      @php
      $name = $column['name'];
      $isPk = $name === $primaryKey;
      $label = $column['label'] ?? FilterHelper::cleanFieldName($name, $isPk);
      $inputType = FilterHelper::getInputType($column, $tableName);
      $isNumberField = $inputType === 'number';
      $step = $isNumberField ? FilterHelper::getNumberStep($column, $tableName) : null;
      $isPercentage = FilterHelper::isPercentageColumn($name);
      $filterValue = $isNumberField
          ? FilterHelper::formatNumberFilterValue(request()->get($name))
          : request()->get($name);
      $rowActive = request()->filled($name)
          || request()->get($name.'_not_empty') === '1'
          || request()->get($name.'_empty') === '1';
      @endphp
      <div class="hb-frow {{ $rowActive ? 'is-active' : 'd-none' }}"
           data-frow-key="{{ $name }}" data-frow-label="{{ $label }}" data-frow-group="{{ $groupValue }}">
        <div class="hb-frow-head">
          <label class="hb-frow-label" for="{{ $name }}">{{ $label }}</label>
          <div class="hb-frow-tools">
            <div class="hb-seg" role="group">
              <input type="radio" class="btn-check" name="{{ $name }}_empty_filter" id="{{ $name }}_all" value=""
                     {{ !request()->get($name.'_not_empty') && !request()->get($name.'_empty') ? 'checked' : '' }}>
              <label class="hb-seg-btn" for="{{ $name }}_all">{{ trans('backpack::filters.all') }}</label>

              <input type="radio" class="btn-check" name="{{ $name }}_empty_filter" id="{{ $name }}_not_empty" value="not_empty"
                     {{ request()->get($name.'_not_empty') == '1' ? 'checked' : '' }}>
              <label class="hb-seg-btn" for="{{ $name }}_not_empty">{{ trans('backpack::filters.not_empty') }}</label>

              <input type="radio" class="btn-check" name="{{ $name }}_empty_filter" id="{{ $name }}_empty" value="empty"
                     {{ request()->get($name.'_empty') == '1' ? 'checked' : '' }}>
              <label class="hb-seg-btn" for="{{ $name }}_empty">{{ trans('backpack::filters.empty') }}</label>
            </div>
            <button type="button" class="hb-frow-remove" aria-label="{{ trans('backpack::filters.remove_filter') }}">×</button>
          </div>
        </div>
        <p class="hb-presence-hint d-none">{{ trans('backpack::filters.presence_value_disabled') }}</p>
        <div class="hb-frow-body position-relative">
          @if($isNumberField)
          <div class="input-group input-group-sm">
            <input type="number" name="{{ $name }}" id="{{ $name }}"
                   value="{{ $filterValue }}"
                   class="form-control form-control-sm hb-filter-number"
                   step="{{ $step }}"
                   @if($isPercentage) min="1" max="100" @endif
                   aria-label="{{ $label }}">
            @if($isPercentage)
            <span class="input-group-text">%</span>
            @endif
          </div>
          @else
          <input type="{{ $inputType }}" name="{{ $name }}" id="{{ $name }}"
                 value="{{ $filterValue }}"
                 class="form-control form-control-sm hb-autocomplete-value"
                 data-column="{{ $name }}" data-table="{{ $tableName }}"
                 data-autocomplete-url="{{ backpack_url('autocomplete-values') }}"
                 placeholder="{{ trans('backpack::filters.filter_by_field', ['field' => strtolower($label)]) }}">
          <div class="autocomplete-suggestions" id="autocomplete-{{ $name }}"></div>
          @endif
        </div>
      </div>
      @endforeach

      {{-- BOOLEAN / STATUS FILTERS -------------------------------------------- --}}
      @foreach ($booleanColumns as $column)
      @php
      $name = $column['name'];
      $label = $column['label'] ?? FilterHelper::cleanFieldName($name);
      $rowActive = request()->get($name) !== null && request()->get($name) !== '';
      @endphp
      <div class="hb-frow hb-frow--inline {{ $rowActive ? 'is-active' : 'd-none' }}"
           data-frow-key="{{ $name }}" data-frow-label="{{ $label }}" data-frow-group="{{ $groupStatus }}">
        <div class="hb-frow-head">
          <span class="hb-frow-label">{{ $label }}</span>
          <div class="hb-frow-tools">
            <div class="hb-seg" role="group">
              <input type="radio" class="btn-check" name="{{ $name }}" id="{{ $name }}_all" value=""
                     {{ request()->get($name) === null || request()->get($name) === '' ? 'checked' : '' }}>
              <label class="hb-seg-btn" for="{{ $name }}_all">{{ trans('backpack::filters.all') }}</label>

              <input type="radio" class="btn-check" name="{{ $name }}" id="{{ $name }}_yes" value="1"
                     {{ request()->get($name) == '1' ? 'checked' : '' }}>
              <label class="hb-seg-btn hb-seg-btn--yes" for="{{ $name }}_yes">{{ trans('backpack::filters.yes') }}</label>

              <input type="radio" class="btn-check" name="{{ $name }}" id="{{ $name }}_no" value="0"
                     {{ request()->get($name) == '0' ? 'checked' : '' }}>
              <label class="hb-seg-btn hb-seg-btn--no" for="{{ $name }}_no">{{ trans('backpack::filters.no') }}</label>
            </div>
            <button type="button" class="hb-frow-remove" aria-label="{{ trans('backpack::filters.remove_filter') }}">×</button>
          </div>
        </div>
      </div>
      @endforeach

      {{-- PATH / FILE FILTERS --------------------------------------------------- --}}
      @foreach ($pathFields as $column)
      @php
      $name = $column['name'];
      $label = ucfirst(str_replace('_path', '', $name));
      $rowActive = request()->get($name) == '1';
      @endphp
      <div class="hb-frow hb-frow--inline {{ $rowActive ? 'is-active' : 'd-none' }}"
           data-frow-key="{{ $name }}" data-frow-label="{{ $label }}" data-frow-group="{{ $groupFile }}">
        <div class="hb-frow-head">
          <span class="hb-frow-label">{{ $label }}</span>
          <div class="hb-frow-tools">
            <div class="form-check form-switch m-0">
              <input type="checkbox" class="form-check-input" name="{{ $name }}" value="1"
                     id="{{ $name }}" {{ $rowActive ? 'checked' : '' }}>
              <label class="form-check-label hb-frow-switch-label" for="{{ $name }}">{{ trans('backpack::filters.with_file') }}</label>
            </div>
            <button type="button" class="hb-frow-remove" aria-label="{{ trans('backpack::filters.remove_filter') }}">×</button>
          </div>
        </div>
      </div>
      @endforeach

      {{-- SELECT / ENUM FILTERS ------------------------------------------------- --}}
      @foreach ($selectColumns as $column)
      @php
      $name = $column['name'];
      $label = $column['label'] ?? ucfirst($name);
      $options = FilterHelper::getSelectOptions($column, $tableName);
      $currentValue = request()->get($name);
      $rowActive = $currentValue !== null && $currentValue !== '';
      @endphp
      <div class="hb-frow {{ $rowActive ? 'is-active' : 'd-none' }}"
           data-frow-key="{{ $name }}" data-frow-label="{{ $label }}" data-frow-group="{{ $groupSelect }}">
        <div class="hb-frow-head">
          <label class="hb-frow-label" for="{{ $name }}">{{ $label }}</label>
          <div class="hb-frow-tools">
            <button type="button" class="hb-frow-remove" aria-label="{{ trans('backpack::filters.remove_filter') }}">×</button>
          </div>
        </div>
        <div class="hb-frow-body">
          <select name="{{ $name }}" id="{{ $name }}" class="form-select form-select-sm">
            <option value="">{{ trans('backpack::filters.all') }}</option>
            @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" {{ $currentValue == $optValue ? 'selected' : '' }}>{{ $optLabel }}</option>
            @endforeach
          </select>
        </div>
      </div>
      @endforeach

      {{-- DATE FILTERS ----------------------------------------------------------- --}}
      @foreach ($dateColumns->merge($advancedDateColumns) as $column)
      @php
      $name = $column['name'];
      $isAdvanced = in_array($name, $advancedFields);
      $label = $column['label'] ?? ucfirst(FilterHelper::cleanFieldName($name));
      $hasRange = request()->filled($name.'_from') || request()->filled($name.'_to');
      $rowActive = request()->filled($name) || $hasRange;
      @endphp
      <div class="hb-frow hb-frow--date {{ $rowActive ? 'is-active' : 'd-none' }}"
           data-frow-key="{{ $name }}" data-frow-label="{{ $label }}"
           data-frow-group="{{ $isAdvanced ? $groupAdvanced : $groupDate }}">
        <div class="hb-frow-head">
          <span class="hb-frow-label">{{ $label }}</span>
          <div class="hb-frow-tools">
            <div class="hb-seg" role="group">
              <input type="radio" class="btn-check hb-date-type" name="{{ $name }}_type" id="{{ $name }}_exact" value="exact" {{ !$hasRange ? 'checked' : '' }}>
              <label class="hb-seg-btn" for="{{ $name }}_exact">{{ trans('backpack::filters.exact_date') }}</label>

              <input type="radio" class="btn-check hb-date-type" name="{{ $name }}_type" id="{{ $name }}_range" value="range" {{ $hasRange ? 'checked' : '' }}>
              <label class="hb-seg-btn" for="{{ $name }}_range">{{ trans('backpack::filters.date_range') }}</label>
            </div>
            <button type="button" class="hb-frow-remove" aria-label="{{ trans('backpack::filters.remove_filter') }}">×</button>
          </div>
        </div>
        <div class="hb-frow-body">
          <div id="{{ $name }}_exact_section" class="hb-date-section" style="{{ $hasRange ? 'display:none;' : '' }}">
            <input type="date" name="{{ $name }}" id="{{ $name }}"
                   value="{{ request()->get($name) }}" class="form-control form-control-sm">
          </div>
          <div id="{{ $name }}_range_section" class="hb-date-section hb-date-range" style="{{ !$hasRange ? 'display:none;' : '' }}">
            <input type="date" name="{{ $name }}_from" id="{{ $name }}_from"
                   value="{{ request()->get($name.'_from') }}" class="form-control form-control-sm"
                   aria-label="{{ trans('backpack::filters.start_date') }}">
            <span class="hb-date-sep">→</span>
            <input type="date" name="{{ $name }}_to" id="{{ $name }}_to"
                   value="{{ request()->get($name.'_to') }}" class="form-control form-control-sm"
                   aria-label="{{ trans('backpack::filters.end_date') }}">
          </div>
        </div>
      </div>
      @endforeach

      {{-- ADVANCED TEXT FILTERS --------------------------------------------------- --}}
      @foreach ($advancedTextColumns as $column)
      @php
      $name = $column['name'];
      $label = $column['label'] ?? FilterHelper::cleanFieldName($name);
      $inputType = FilterHelper::getInputType($column, $tableName);
      $isNumberField = $inputType === 'number';
      $step = $isNumberField ? FilterHelper::getNumberStep($column, $tableName) : null;
      $isPercentage = FilterHelper::isPercentageColumn($name);
      $filterValue = $isNumberField
          ? FilterHelper::formatNumberFilterValue(request()->get($name))
          : request()->get($name);
      $rowActive = request()->filled($name)
          || request()->get($name.'_not_empty') === '1'
          || request()->get($name.'_empty') === '1';
      @endphp
      <div class="hb-frow {{ $rowActive ? 'is-active' : 'd-none' }}"
           data-frow-key="{{ $name }}" data-frow-label="{{ $label }}" data-frow-group="{{ $groupAdvanced }}">
        <div class="hb-frow-head">
          <label class="hb-frow-label" for="{{ $name }}">{{ $label }}</label>
          <div class="hb-frow-tools">
            <div class="hb-seg" role="group">
              <input type="radio" class="btn-check" name="{{ $name }}_empty_filter" id="{{ $name }}_all" value=""
                     {{ !request()->get($name.'_not_empty') && !request()->get($name.'_empty') ? 'checked' : '' }}>
              <label class="hb-seg-btn" for="{{ $name }}_all">{{ trans('backpack::filters.all') }}</label>
              <input type="radio" class="btn-check" name="{{ $name }}_empty_filter" id="{{ $name }}_not_empty" value="not_empty"
                     {{ request()->get($name.'_not_empty') == '1' ? 'checked' : '' }}>
              <label class="hb-seg-btn" for="{{ $name }}_not_empty">{{ trans('backpack::filters.not_empty') }}</label>
              <input type="radio" class="btn-check" name="{{ $name }}_empty_filter" id="{{ $name }}_empty" value="empty"
                     {{ request()->get($name.'_empty') == '1' ? 'checked' : '' }}>
              <label class="hb-seg-btn" for="{{ $name }}_empty">{{ trans('backpack::filters.empty') }}</label>
            </div>
            <button type="button" class="hb-frow-remove" aria-label="{{ trans('backpack::filters.remove_filter') }}">×</button>
          </div>
        </div>
        <p class="hb-presence-hint d-none">{{ trans('backpack::filters.presence_value_disabled') }}</p>
        <div class="hb-frow-body position-relative">
          @if($isNumberField)
          <div class="input-group input-group-sm">
            <input type="number" name="{{ $name }}" id="{{ $name }}"
                   value="{{ $filterValue }}"
                   class="form-control form-control-sm hb-filter-number"
                   step="{{ $step }}"
                   @if($isPercentage) min="1" max="100" @endif
                   aria-label="{{ $label }}">
            @if($isPercentage)
            <span class="input-group-text">%</span>
            @endif
          </div>
          @else
          <input type="{{ $inputType }}" name="{{ $name }}" id="{{ $name }}"
                 value="{{ $filterValue }}"
                 class="form-control form-control-sm hb-autocomplete-value"
                 data-column="{{ $name }}" data-table="{{ $tableName }}"
                 data-autocomplete-url="{{ backpack_url('autocomplete-values') }}"
                 placeholder="{{ trans('backpack::filters.filter_by_field', ['field' => strtolower($label)]) }}">
          <div class="autocomplete-suggestions" id="autocomplete-{{ $name }}"></div>
          @endif
        </div>
      </div>
      @endforeach

      {{-- BELONGSTO RELATION FILTERS (normal + advanced) --------------------------- --}}
      @foreach ($relationColumns->merge($advancedRelationColumns) as $column)
      @include('crud::inc.filter-belongsto-card', [
          'column' => $column,
          'crud' => $crud,
          'singularRelationsByFk' => $singularRelationsByFk,
          'frowGroup' => in_array($column['name'], $advancedFields) ? $groupAdvanced : $groupRelation,
      ])
      @endforeach

    </div>

    <div class="hb-frows hb-frows--relations" id="hbFilterRowsRelations">
      {{-- HASMANY RELATION CARDS ----------------------------------------------------- --}}
      @foreach ($hasManyRelations as $relation)
      @include('crud::inc.filter-hasmany-card', [
          'relation' => $relation,
          'crud' => $crud,
          'frowGroup' => $groupRelation,
      ])
      @endforeach
    </div>
    </div>

    <div class="hb-filters-footer">
      <button type="submit" class="hb-search-btn">
        <i class="la la-search"></i> {{ trans('backpack::crud.search') }}
      </button>
    </div>

    <input type="hidden" name="persistent-table" value="1">
  </form>
</div>

<script>
/* ==========================================================================
 * Filter panel — composable rule-builder behaviour
 * Keeps the exact same GET parameter contract as the legacy panel.
 * ========================================================================== */
(function () {
    'use strict';

    const form = document.getElementById('filterForm');
    if (!form) return;

    const filterRowsRoot = form;
    const layoutWrap = document.querySelector('.hb-frows-wrap');
    const addBtn   = document.getElementById('hbAddFilterBtn');
    const pop      = document.getElementById('hbAddFilterPop');
    const popList  = document.getElementById('hbAddFilterList');
    const popInput = document.getElementById('hbAddFilterSearch');
    const popEmpty = document.getElementById('hbAddFilterEmpty');
    const groupRelation = @json($groupRelation);
    const groupAdvanced = @json($groupAdvanced);

    const GRID_COLS = 6;
    const ROW_UNIT = 4;
    const MOBILE_BREAKPOINT = 640;
    const masonrySupported = typeof CSS !== 'undefined'
        && CSS.supports('(grid-template-rows: masonry)');

    let packRaf = 0;
    const rowObservers = new WeakMap();

    function syncSearchVisibility() {
        const hasRows = filterRowsRoot.querySelectorAll('.hb-frow:not(.d-none)').length > 0;
        form.classList.toggle('has-active-rows', hasRows);
    }

    function parseGapPx(wrap) {
        const gap = getComputedStyle(wrap).gap || '0.5rem';
        if (gap.endsWith('px')) return parseFloat(gap);
        if (gap.endsWith('rem')) return parseFloat(gap) * 16;
        return 8;
    }

    function getGridCols(wrap) {
        return wrap.clientWidth <= MOBILE_BREAKPOINT ? 1 : GRID_COLS;
    }

    function getColSpan(item, cols) {
        if (cols === 1) return 1;
        const override = parseInt(item.dataset.frowCols, 10);
        if (!Number.isNaN(override) && override > 0) {
            return Math.min(override, cols);
        }
        if (item.classList.contains('hb-frow--inline')) return 1;
        if (item.classList.contains('hb-frow--card')) return cols;
        if (item.classList.contains('hb-frow--date')) return 3;
        if (item.classList.contains('hb-frow--relation')) return 2;
        return 1;
    }

    function rowSpanForHeight(height, gapPx) {
        if (height <= 0) return 1;
        let span = 1;
        while (span * ROW_UNIT + Math.max(0, span - 1) * gapPx < height) {
            span++;
        }
        return span;
    }

    function rectsOverlap(a, b) {
        return a.col < b.col + b.colSpan
            && a.col + a.colSpan > b.col
            && a.row < b.row + b.rowSpan
            && a.row + a.rowSpan > b.row;
    }

    function findPlacement(placed, colSpan, rowSpan, cols) {
        for (let row = 0; row < 10000; row++) {
            for (let col = 0; col <= cols - colSpan; col++) {
                const candidate = { row: row, col: col, rowSpan: rowSpan, colSpan: colSpan };
                let blocked = false;
                for (let i = 0; i < placed.length; i++) {
                    if (rectsOverlap(placed[i], candidate)) {
                        blocked = true;
                        break;
                    }
                }
                if (!blocked) return candidate;
            }
        }
        return { row: 0, col: 0, rowSpan: rowSpan, colSpan: colSpan };
    }

    function resetItemLayout(item) {
        item.style.gridColumn = '';
        item.style.gridRow = '';
        item.style.width = '';
    }

    function packFilterRows() {
        syncSearchVisibility();
        if (!layoutWrap) return;

        const items = Array.from(layoutWrap.querySelectorAll('.hb-frow:not(.d-none)'));
        const cols = getGridCols(layoutWrap);
        const gapPx = parseGapPx(layoutWrap);

        items.forEach(resetItemLayout);
        layoutWrap.style.gridTemplateRows = '';

        if (!items.length) {
            layoutWrap.classList.remove('is-layout-ready');
            return;
        }

        if (masonrySupported) {
            items.forEach(function (item) {
                const colSpan = getColSpan(item, cols);
                item.style.gridColumn = 'span ' + colSpan;
                item.style.gridRow = 'auto';
            });
            layoutWrap.classList.add('is-layout-ready');
            observeFilterRows(items);
            return;
        }

        /* Measure each item at its target column width (stacked in one column). */
        items.forEach(function (item) {
            const colSpan = getColSpan(item, cols);
            item.style.gridColumn = '1 / span ' + colSpan;
            item.style.gridRow = 'auto';
        });
        layoutWrap.offsetHeight;

        const measurements = items.map(function (item) {
            const colSpan = getColSpan(item, cols);
            const rowSpan = rowSpanForHeight(item.offsetHeight, gapPx);
            resetItemLayout(item);
            return { item: item, colSpan: colSpan, rowSpan: rowSpan };
        });

        const placed = [];

        measurements.forEach(function (entry) {
            const slot = findPlacement(placed, entry.colSpan, entry.rowSpan, cols);
            placed.push(slot);

            entry.item.style.gridColumn = (slot.col + 1) + ' / span ' + slot.colSpan;
            entry.item.style.gridRow = (slot.row + 1) + ' / span ' + slot.rowSpan;
        });

        /* Trim trailing empty grid rows created during placement search. */
        const maxRow = placed.reduce(function (max, slot) {
            return Math.max(max, slot.row + slot.rowSpan);
        }, 0);
        layoutWrap.style.gridTemplateRows = 'repeat(' + maxRow + ', ' + ROW_UNIT + 'px)';

        layoutWrap.classList.add('is-layout-ready');
        observeFilterRows(items);
    }

    function observeFilterRows(items) {
        if (typeof ResizeObserver === 'undefined') return;
        items.forEach(function (item) {
            if (rowObservers.has(item)) return;
            const observer = new ResizeObserver(schedulePackFilterRows);
            observer.observe(item);
            rowObservers.set(item, observer);
        });
    }

    function schedulePackFilterRows() {
        if (packRaf) cancelAnimationFrame(packRaf);
        packRaf = requestAnimationFrame(function () {
            packRaf = 0;
            packFilterRows();
        });
    }

    /* ----------------------------------------------------------------------
     * 1. Build the add-filter index from the server-rendered rows
     * -------------------------------------------------------------------- */
    const rows = Array.from(filterRowsRoot.querySelectorAll('[data-frow-key]'));
    const groupOrder = [];
    const itemsByKey = {};

    rows.forEach(function (row) {
        const group = row.dataset.frowGroup;
        if (!groupOrder.includes(group)) groupOrder.push(group);
    });

    function renderPopList() {
        popList.innerHTML = '';
        groupOrder.forEach(function (group) {
            const groupRows = rows.filter(r => r.dataset.frowGroup === group);
            if (!groupRows.length) return;

            const block = document.createElement('div');
            block.className = 'hb-addfilter-group-block';

            const head = document.createElement('div');
            head.className = 'hb-addfilter-group';
            if (group === groupRelation) {
                head.classList.add('hb-addfilter-group--relation');
            } else if (group === groupAdvanced) {
                head.classList.add('hb-addfilter-group--advanced');
            }
            head.textContent = group;
            block.appendChild(head);

            const itemsWrap = document.createElement('div');
            itemsWrap.className = 'hb-addfilter-group-items';

            groupRows.forEach(function (row) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'hb-addfilter-item';
                item.dataset.key = row.dataset.frowKey;
                item.dataset.search = (row.dataset.frowLabel + ' ' + group).toLowerCase();
                item.textContent = row.dataset.frowLabel;
                if (!row.classList.contains('d-none')) item.classList.add('is-added');
                item.addEventListener('click', function () { addRow(row, item); });
                itemsWrap.appendChild(item);
                itemsByKey[row.dataset.frowKey] = item;
            });

            block.appendChild(itemsWrap);
            popList.appendChild(block);
        });
    }
    renderPopList();

    /* ----------------------------------------------------------------------
     * 2. Popover open/close + search
     * -------------------------------------------------------------------- */
    function openPop() {
        pop.classList.remove('d-none');
        addBtn.setAttribute('aria-expanded', 'true');
        popInput.value = '';
        filterPopItems('');
        popInput.focus();
    }
    function closePop() {
        pop.classList.add('d-none');
        addBtn.setAttribute('aria-expanded', 'false');
    }
    addBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        pop.classList.contains('d-none') ? openPop() : closePop();
    });
    document.addEventListener('click', function (e) {
        if (!pop.contains(e.target) && e.target !== addBtn) closePop();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closePop();
    });

    function filterPopItems(term) {
        term = term.toLowerCase().trim();
        let visible = 0;
        popList.querySelectorAll('.hb-addfilter-item').forEach(function (item) {
            const show = !term || item.dataset.search.includes(term);
            item.classList.toggle('d-none', !show);
            if (show && !item.classList.contains('is-added')) visible++;
        });
        // Hide group blocks with no visible items
        popList.querySelectorAll('.hb-addfilter-group-block').forEach(function (block) {
            const any = block.querySelector('.hb-addfilter-item:not(.d-none)');
            block.classList.toggle('d-none', !any);
        });
        popEmpty.classList.toggle('d-none', visible > 0);
    }
    popInput.addEventListener('input', function () { filterPopItems(this.value); });

    /* ----------------------------------------------------------------------
     * 3. Add / remove rows
     * -------------------------------------------------------------------- */
    function addRow(row, item) {
        row.classList.remove('d-none');
        row.classList.add('is-added-now');
        if (item) item.classList.add('is-added');
        closePop();
        const firstInput = row.querySelector('input:not([type=radio]):not([type=hidden]):not([type=checkbox]), select');
        if (firstInput) firstInput.focus();
        row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        schedulePackFilterRows();
    }

    function clearRow(row) {
        row.querySelectorAll('input[type=text], input[type=number], input[type=email], input[type=url], input[type=date], input[type=hidden]:not([name="persistent-table"])')
            .forEach(function (i) { i.value = ''; });
        row.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
        row.querySelectorAll('input[type=checkbox]').forEach(function (c) { c.checked = false; });
        // Radios: re-check the "all/empty value" option of each group
        const radioGroups = {};
        row.querySelectorAll('input[type=radio]').forEach(function (r) { (radioGroups[r.name] ||= []).push(r); });
        Object.values(radioGroups).forEach(function (group) {
            const def = group.find(r => r.value === '') || group.find(r => r.value === 'exact');
            group.forEach(r => { r.checked = (r === def); });
        });
        row.querySelectorAll('.hb-relsection').forEach(updateSectionState);
        syncPresenceValueRow(row);
    }

    filterRowsRoot.addEventListener('click', function (e) {
        const btn = e.target.closest('.hb-frow-remove');
        if (!btn) return;
        const row = btn.closest('[data-frow-key]');
        clearRow(row);
        row.classList.add('d-none');
        const item = itemsByKey[row.dataset.frowKey];
        if (item) item.classList.remove('is-added');
        schedulePackFilterRows();
    });

    /* ----------------------------------------------------------------------
     * 4. Relation card sections — stacked, combinable (AND)
     * -------------------------------------------------------------------- */
    function panelHasValue(panel) {
        return Array.from(panel.querySelectorAll('input:not([type=radio]), select')).some(function (el) {
            if (el.disabled || el.classList.contains('hb-autocomplete-display')) return false;
            if (el.name && el.name.endsWith('_count_op')) return false;
            if (el.type === 'checkbox') return el.checked;
            if (el.tagName === 'SELECT') return el.value.trim() !== '';
            return String(el.value).trim() !== '';
        });
    }

    function updateSectionState(section) {
        const panel = section.querySelector('.hb-relpanel');
        if (!panel) return;
        const hasValue = panelHasValue(panel);
        section.querySelector('.hb-relsection-dot')?.classList.toggle('d-none', !hasValue);
        section.classList.toggle('is-active-criteria', hasValue);
    }

    filterRowsRoot.querySelectorAll('.hb-relsection-head').forEach(function (head) {
        head.addEventListener('click', function () {
            const section = head.closest('.hb-relsection');
            const card = section.closest('.hb-relcard');
            const willOpen = !section.classList.contains('is-open');

            card.querySelectorAll('.hb-relsection').forEach(function (s) {
                s.classList.remove('is-open');
                s.querySelector('.hb-relsection-head')?.setAttribute('aria-expanded', 'false');
            });

            if (willOpen) {
                section.classList.add('is-open');
                head.setAttribute('aria-expanded', 'true');
            }
            schedulePackFilterRows();
        });
    });

    filterRowsRoot.querySelectorAll('.hb-relsection').forEach(updateSectionState);

    filterRowsRoot.addEventListener('input', function (e) {
        const section = e.target.closest('.hb-relsection');
        if (section) updateSectionState(section);
    });
    filterRowsRoot.addEventListener('change', function (e) {
        const section = e.target.closest('.hb-relsection');
        if (section) updateSectionState(section);
    });

    /* ----------------------------------------------------------------------
     * 4b. Presence vs value mutual exclusion (BelongsTo + text columns)
     * -------------------------------------------------------------------- */
    function clearRowValueInputs(body) {
        body.querySelectorAll('input[type=text], input[type=number], input[type=email], input[type=url], input[type=date], input[type=hidden]')
            .forEach(function (i) { i.value = ''; });
        body.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
        const display = body.querySelector('.hb-autocomplete-display');
        if (display) display.value = '';
    }

    function syncPresenceValueRow(row) {
        const emptyRadio = row.querySelector('input[name$="_empty_filter"]:checked');
        const body = row.querySelector('.hb-frow-body');
        const hint = row.querySelector('.hb-presence-hint');
        if (!emptyRadio || !body) return;

        const isPresence = emptyRadio.value === 'not_empty' || emptyRadio.value === 'empty';
        body.classList.toggle('is-disabled', isPresence);
        body.querySelectorAll('input, select').forEach(function (el) {
            el.disabled = isPresence;
        });
        if (isPresence) clearRowValueInputs(body);
        if (hint) hint.classList.toggle('d-none', !isPresence);
    }

    filterRowsRoot.querySelectorAll('.hb-frow').forEach(function (row) {
        if (!row.querySelector('input[name$="_empty_filter"]')) return;

        row.querySelectorAll('input[name$="_empty_filter"]').forEach(function (radio) {
            radio.addEventListener('change', function () { syncPresenceValueRow(row); });
        });

        row.querySelectorAll('.hb-frow-body select, .hb-frow-body input:not([type=radio])').forEach(function (el) {
            el.addEventListener('change', function () {
                const hasValue = (el.tagName === 'SELECT' && el.value !== '')
                    || (el.type === 'hidden' && el.value !== '')
                    || (el.type !== 'hidden' && String(el.value).trim() !== '');
                if (!hasValue) return;
                const allRadio = row.querySelector('input[name$="_empty_filter"][value=""]');
                if (allRadio) {
                    allRadio.checked = true;
                    syncPresenceValueRow(row);
                }
            });
        });

        syncPresenceValueRow(row);
    });

    /* ----------------------------------------------------------------------
     * 5. Date exact/range toggle
     * -------------------------------------------------------------------- */
    filterRowsRoot.querySelectorAll('.hb-date-type').forEach(function (radio) {
        radio.addEventListener('change', function () {
            const field = this.name.replace('_type', '');
            const exact = document.getElementById(field + '_exact_section');
            const range = document.getElementById(field + '_range_section');
            if (!exact || !range) return;
            exact.style.display = this.value === 'exact' ? '' : 'none';
            range.style.display = this.value === 'range' ? '' : 'none';
        });
    });

    /* ----------------------------------------------------------------------
     * 6. Form submission — same GET parameter contract as legacy panel
     * -------------------------------------------------------------------- */
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        // Reset DataTables pagination state
        try {
            const dtKey = 'DataTables_crudTable_' + window.location.pathname;
            const state = JSON.parse(localStorage.getItem(dtKey) || 'null');
            if (state) { state.start = 0; localStorage.setItem(dtKey, JSON.stringify(state)); }
        } catch (err) { /* non-blocking */ }

        const fd = new FormData(form);
        const params = new URLSearchParams();
        params.append('persistent-table', '1');

        // a) empty_filter radio groups → field_not_empty / field_empty
        const emptyHandled = new Set();
        form.querySelectorAll('input[name$="_empty_filter"]:checked').forEach(function (radio) {
            const field = radio.name.replace('_empty_filter', '');
            emptyHandled.add(field);
            if (radio.value === 'not_empty') params.set(field + '_not_empty', '1');
            else if (radio.value === 'empty') params.set(field + '_empty', '1');
        });

        // b) all other fields
        for (const [key, raw] of fd.entries()) {
            let value = String(raw).trim();
            const numberInput = form.querySelector('[name="' + CSS.escape(key) + '"].hb-filter-number');
            if (numberInput) {
                value = value.replace(',', '.');
            }
            if (!value) continue;
            if (key === 'persistent-table' || key === 'last_filter_section') continue;
            if (key.endsWith('_empty_filter') || key.endsWith('_type') || key.endsWith('_filter_mode')) continue;
            // skip the visible half of autocompletes (hidden input carries the value)
            if (form.querySelector('[name="' + CSS.escape(key) + '"]')?.classList.contains('hb-autocomplete-display')) continue;
            // skip count operator without a count value
            if (key.endsWith('_count_op')) {
                const countVal = fd.get(key.replace('_count_op', '_count'));
                if (!countVal || !String(countVal).trim()) continue;
            }
            // skip raw value when an empty/not_empty filter is active on the field
            const base = key.replace(/_(from|to)$/, '');
            if (emptyHandled.has(key) && (params.has(key + '_not_empty') || params.has(key + '_empty'))) continue;
            if (params.has(base + '_not_empty') || params.has(base + '_empty')) continue;
            params.append(key, value);
        }

        window.location.href = window.location.pathname + '?' + params.toString();
    });

    /* ----------------------------------------------------------------------
     * 7. Autocomplete (vanilla, unified for value + relation inputs)
     * -------------------------------------------------------------------- */
    function debounce(fn, ms) {
        let t; return function () { clearTimeout(t); const a = arguments, c = this; t = setTimeout(() => fn.apply(c, a), ms); };
    }

    function showSuggestions(container, items, onPick) {
        container.innerHTML = '';
        if (!items.length) { container.style.display = 'none'; return; }
        items.forEach(function (it) {
            const div = document.createElement('div');
            div.className = 'hb-suggestion';
            div.textContent = it.label;
            div.addEventListener('mousedown', function (e) { e.preventDefault(); onPick(it); container.style.display = 'none'; });
            container.appendChild(div);
        });
        container.style.display = 'block';
    }

    // 7a. Plain value autocomplete
    filterRowsRoot.querySelectorAll('.hb-autocomplete-value').forEach(function (input) {
        const box = document.getElementById('autocomplete-' + (input.id || input.dataset.column));
        if (!box) return;
        const run = debounce(function () {
            const term = input.value.trim();
            if (term.length < 4) { box.style.display = 'none'; return; }
            fetch(input.dataset.autocompleteUrl + '?' + new URLSearchParams({ term: term, column: input.dataset.column, table: input.dataset.table }))
                .then(r => r.ok ? r.json() : [])
                .then(function (data) {
                    showSuggestions(box, (data || []).map(v => ({ label: String(v), value: String(v) })), function (it) { input.value = it.value; });
                })
                .catch(() => { box.style.display = 'none'; });
        }, 300);
        input.addEventListener('input', run);
        input.addEventListener('blur', () => setTimeout(() => { box.style.display = 'none'; }, 150));
    });

    // 7b. Relation autocomplete (belongsTo + hasMany search)
    filterRowsRoot.querySelectorAll('.hb-autocomplete-display').forEach(function (input) {
        const hidden = document.getElementById(input.id.replace('_display', ''));
        const box = document.getElementById('autocomplete-' + input.id);
        if (!hidden || !box) return;
        const baseUrl = '{{ backpack_url("autocomplete-relation-values") }}';

        function clearCommittedPick() {
            delete input.dataset.autocompleteValue;
            delete input.dataset.autocompleteLabel;
        }

        function commitPick(it) {
            hidden.value = it.value;
            input.value = it.label;
            input.dataset.autocompleteValue = it.value;
            input.dataset.autocompleteLabel = it.label;
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
            box.style.display = 'none';
        }

        // Restore commit state after page reload (hidden carries GET param, display shows label)
        if (hidden.value && input.value.trim()) {
            input.dataset.autocompleteValue = hidden.value;
            input.dataset.autocompleteLabel = input.value.trim();
        }

        const run = debounce(function () {
            const term = input.value.trim();
            if (!term) {
                hidden.value = '';
                clearCommittedPick();
                box.style.display = 'none';
                return;
            }

            // Keep hidden ID when display still shows a committed pick (do not clear on debounced re-run)
            if (input.dataset.autocompleteLabel === term && input.dataset.autocompleteValue) {
                hidden.value = input.dataset.autocompleteValue;
                return;
            }

            clearCommittedPick();
            hidden.value = '';

            const qs = new URLSearchParams({ term: term });
            if (input.classList.contains('hb-autocomplete-hasmany')) {
                qs.append('is_hasmany', '1');
                qs.append('relation', input.dataset.relation);
                qs.append('model', input.dataset.model);
                qs.append('searchable_keys', input.dataset.searchableKeys);
            } else {
                qs.append('relation_column', input.dataset.relationColumn);
                qs.append('table', input.dataset.table);
            }
            fetch(baseUrl + '?' + qs.toString())
                .then(r => r.ok ? r.json() : [])
                .then(function (data) {
                    if (!Array.isArray(data)) { box.style.display = 'none'; return; }
                    showSuggestions(box, data.map(it => ({ label: it.label || String(it.value), value: String(it.value) })), commitPick);
                })
                .catch(() => { box.style.display = 'none'; });
        }, 300);

        input.addEventListener('input', function () {
            if (input.dataset.autocompleteLabel && input.value.trim() !== input.dataset.autocompleteLabel) {
                clearCommittedPick();
                hidden.value = '';
            }
            run();
        });
        input.addEventListener('blur', () => setTimeout(() => { box.style.display = 'none'; }, 150));
    });

    /* ----------------------------------------------------------------------
     * 8. Filter panel toggle sync
     * -------------------------------------------------------------------- */
    const filterPanel = document.getElementById('filterPanel');
    const filterToggle = document.getElementById('hbFilterToggleBtn');

    function syncFilterToggle(open) {
        if (!filterToggle) return;
        filterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        filterToggle.classList.toggle('is-open', open);
        const chevron = filterToggle.querySelector('.hb-filter-toggle-chevron');
        if (chevron) {
            chevron.classList.toggle('la-angle-up', open);
            chevron.classList.toggle('la-angle-down', !open);
        }
    }

    if (filterPanel) {
        filterPanel.addEventListener('shown.bs.collapse', function () {
            syncFilterToggle(true);
            schedulePackFilterRows();
        });
        filterPanel.addEventListener('hidden.bs.collapse', function () { syncFilterToggle(false); });
    }

    if (typeof ResizeObserver !== 'undefined' && layoutWrap) {
        new ResizeObserver(schedulePackFilterRows).observe(layoutWrap);
    }
    window.addEventListener('resize', schedulePackFilterRows);
    schedulePackFilterRows();
})();
</script>