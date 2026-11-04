@php
use App\Support\Backpack\PresetFilterHandler;

// Quick filter pills of the current CRUD (see PresetFilterHandler::presetsForModel()).
$modelInstance = is_string($crud->model) ? new $crud->model : $crud->model;
$presets = PresetFilterHandler::presetsForModel($modelInstance);
$activePreset = PresetFilterHandler::getActivePreset();
$currentUrl = request()->url();
@endphp

@if(!empty($presets))
<div class="hb-preset-row">
    <span class="hb-preset-label">
        <i class="la la-bolt"></i>
        Filtri rapidi
    </span>
    <div class="hb-preset-pills">
    @foreach($presets as $key => $preset)
        @php
        $isActive = $activePreset === $key;
        $pillUrl = $currentUrl . '?' . http_build_query(['preset' => $key]);
        @endphp
        <a href="{{ $pillUrl }}"
           class="hb-preset-pill {{ $isActive ? 'hb-preset-pill--active' : '' }}">
            {{ $preset['label'] }}
        </a>
    @endforeach
    </div>
</div>
@endif
