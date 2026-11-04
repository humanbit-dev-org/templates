<div class="api-sandbox-tryit" data-tryit>
    <h4 class="api-sandbox-subtitle">Prova</h4>

    @if ($endpoint['method'] !== 'GET')
        <p class="api-sandbox-tryit-note">
            Le richieste di scrittura vengono eseguite in una transazione e annullate:
            nessun dato viene modificato.
        </p>
    @endif

    <p class="api-sandbox-tryit-note">
        Ogni pulsante esegue una chiamata reale su <code>/sandbox/*</code> con dati
        casuali presi dal database di test.
    </p>

    <div class="api-sandbox-tryit-buttons">
        @foreach ($endpoint['operations'] ?? [] as $operation)
            <button type="button" class="api-sandbox-tryit-btn" data-sample-op="{{ $operation['key'] }}">
                {{ $operation['label'] }}
            </button>
        @endforeach
    </div>

    <div class="api-sandbox-tryit-result" data-tryit-result hidden></div>
</div>
