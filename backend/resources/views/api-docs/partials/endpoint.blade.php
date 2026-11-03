<article class="api-sandbox-card api-sandbox-endpoint" id="{{ $endpoint['id'] }}"
    @if (!empty($endpoint['operations']) && collect($endpoint['operations'])->every(fn ($op) => $op['conguaglio_only'] ?? false)) data-conguaglio-only @endif>
    <h3 class="api-sandbox-endpoint-title">{{ $endpoint['title'] }}</h3>
    <p class="api-sandbox-path">
        <span class="api-sandbox-method method-{{ strtolower($endpoint['method']) }}">{{ $endpoint['method'] }}</span>
        <code class="api-sandbox-path-code">{{ $endpoint['path'] }}</code>
    </p>
    <p class="api-sandbox-description">{{ $endpoint['description'] }}</p>

    @include('api-docs.partials.params-table', ['title' => 'Parametri del percorso', 'params' => $endpoint['path_params'] ?? []])
    @include('api-docs.partials.params-table', ['title' => 'Corpo della richiesta', 'params' => $endpoint['body_params'] ?? []])

    @if (!empty($endpoint['responses']))
        <div class="api-sandbox-responses">
            <h4 class="api-sandbox-subtitle">Risposte</h4>
            @foreach ($endpoint['responses'] as $response)
                <div class="api-sandbox-response">
                    <p>
                        <span class="api-sandbox-status status-{{ substr((string) $response['status'], 0, 1) }}xx">{{ $response['status'] }}</span>
                        {{ $response['description'] }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif

    @if ($sandboxEnabled ?? false)
        @include('api-docs.partials.try-it', ['endpoint' => $endpoint])
    @endif
</article>
