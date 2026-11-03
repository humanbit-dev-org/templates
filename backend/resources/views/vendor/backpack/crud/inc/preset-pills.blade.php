@php
use App\Support\Backpack\PresetFilterHandler;

$modelInstance = is_string($crud->model) ? new $crud->model : $crud->model;
$presets = PresetFilterHandler::presetsForModel($modelInstance);
$activePreset = PresetFilterHandler::getActivePreset();
$activeBanca = PresetFilterHandler::getActiveBancaAbi();
$banche = PresetFilterHandler::getBancheOptions();
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
        $params = ['preset' => $key];
        if ($activeBanca && ($preset['banche'] ?? false)) {
            $params['banca_abi'] = $activeBanca;
        }
        $pillUrl = $currentUrl . '?' . http_build_query($params);
        @endphp
        <a href="{{ $pillUrl }}"
           class="hb-preset-pill {{ $isActive ? 'hb-preset-pill--active' : '' }}">
            {{ $preset['label'] }}
        </a>
    @endforeach

    @if($activePreset)
        @if($presets[$activePreset]['banche'] ?? false)
        <select class="form-select form-select-sm hb-preset-banca-select"
                onchange="window.location.href = this.value;">
            <option value="{{ $currentUrl . '?' . http_build_query(['preset' => $activePreset]) }}">Tutte le banche</option>
            @foreach($banche as $abi => $nome)
                @php
                $bancaUrl = $currentUrl . '?' . http_build_query(['preset' => $activePreset, 'banca_abi' => $abi]);
                @endphp
                <option value="{{ $bancaUrl }}" {{ $activeBanca === (string) $abi ? 'selected' : '' }}">
                    {{ $nome }}
                </option>
            @endforeach
        </select>
        @endif
    @endif
    </div>
</div>
@endif
