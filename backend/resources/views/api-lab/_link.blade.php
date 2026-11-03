{{-- One catalog entry: needs $endpoint and $active. --}}
@php
    $catalogSearch = strtolower(implode(' ', [
        $endpoint['catalog_title'] ?? '',
        $endpoint['route_name'] ?? '',
        $endpoint['uri'] ?? '',
        $endpoint['default_method'] ?? '',
        $endpoint['controller'] ?? '',
    ]));
    $linkTitle = trim(($endpoint['default_method'] ?? '').' '.($endpoint['uri'] ?? ''));
    if (!empty($endpoint['controller'])) {
        $linkTitle .= ' — '.$endpoint['controller'];
    }
    $linkTitle .= ' ('.$endpoint['route_name'].')';
@endphp
<a href="{{ route('api-lab.index', ['key' => $endpoint['key']]) }}"
   class="api-lab-link @if(($active['key'] ?? '') === $endpoint['key']) is-active @endif"
   data-endpoint-key="{{ $endpoint['key'] }}"
   data-search="{{ $catalogSearch }}"
   @if(($active['key'] ?? '') === $endpoint['key']) aria-current="page" @endif
   title="{{ $linkTitle }}">
    <div class="api-lab-link__head">
        <span class="method method--{{ strtolower($endpoint['default_method']) }}">{{ $endpoint['default_method'] }}</span>
        <span class="api-lab-link__title">{{ $endpoint['catalog_title'] }}</span>
    </div>
    <div class="api-lab-link__path">
        <code class="api-lab-link__uri">{{ $endpoint['uri'] }}</code>
    </div>
    <div class="api-lab-link__meta">
        <span class="api-lab-link__route" title="Laravel route name">{{ $endpoint['route_name'] }}</span>
        @foreach($endpoint['auth_hints'] ?? [] as $hint)
            <span class="api-lab-link__tag">{{ $hint }}</span>
        @endforeach
    </div>
</a>
