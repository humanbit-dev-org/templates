<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AUTH Simulation</title>
    <link rel="stylesheet" href="{{ asset('static/css/saml_simulation.css') }}">
</head>
<body class="saml-sim-page">
<div class="saml-sim-scanlines" aria-hidden="true"></div>

<div class="saml-sim-shell">
    <header class="saml-sim-header">
        <div class="saml-sim-header__chrome">
            <span class="dot dot-red"></span>
            <span class="dot dot-amber"></span>
            <span class="dot dot-green"></span>
            <span>session://auth-simulation</span>
        </div>
        <div class="saml-sim-header__badge">// RESTRICTED · IdP/OP FAKE</div>
        <h1>AUTH <span>Simulation</span> Console</h1>
        <p>Inserisci uno username o scegli uno scenario per simulare un login SAML con un utente casuale nello stato desiderato.</p>
    </header>

    @if($errors->any())
        <div class="saml-sim-card" style="margin-bottom: 1rem; border-color: rgba(184, 0, 12, 0.45);">
            <div class="saml-sim-card__body">
                <ul style="margin: 0; padding-left: 1.1rem; color: #ffb4b4;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <dl class="saml-sim-operator">
        <div>
            <dt>Operatore Backpack</dt>
            <dd>{{ $operator['email'] ?? '—' }}</dd>
        </div>
        <div>
            <dt>Ruolo</dt>
            <dd>{{ $operator['role'] ?? '—' }}</dd>
        </div>
        <div>
            <dt>Username operatore</dt>
            <dd>{{ $operator['user_id'] ?? '—' }}</dd>
        </div>
    </dl>

    <div class="saml-sim-grid">
        <div class="saml-sim-grid__stack">
            <section class="saml-sim-card">
                <div class="saml-sim-card__head">
                    <span>// assertion_builder</span>
                    <span>manual entry</span>
                </div>
                <div class="saml-sim-card__body">
                    <form method="POST" action="{{ $postAction }}" target="_blank" class="saml-sim-form saml-sim-form--inline" id="saml-manual-form">
                        @csrf
                        <input type="hidden" name="RelayState" value="{{ $relay }}">
                        <input type="hidden" name="scenario" value="manual">

                        <div class="saml-sim-form__row">
                            <div class="saml-sim-form__field">
                                <label for="username">Username</label>
                                <input
                                    id="username"
                                    name="username"
                                    type="text"
                                    value="{{ old('username') }}"
                                    autocomplete="off"
                                    required
                                >
                            </div>
                            <button type="submit" class="saml-sim-btn saml-sim-btn--primary">
                                Genera &amp; invia SAMLResponse
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="saml-sim-card saml-sim-card--log">
                <div class="saml-sim-card__head">
                    <span>// audit_log</span>
                    <span>{{ $logs->total() }} esecuzioni · {{ $logs->perPage() }} per pagina</span>
                </div>
                <div class="saml-sim-card__body">
                    @if($logs->isEmpty())
                        <p class="saml-sim-log-empty">Nessuna simulazione registrata. I log compaiono qui dopo ogni invio.</p>
                    @else
                        <div class="saml-sim-log-table-wrap">
                            <table class="saml-sim-log-table">
                                <thead>
                                    <tr>
                                        <th>Data/ora</th>
                                        <th>Utente simulato</th>
                                        <th>Scenario</th>
                                        <th>Operatore Backpack</th>
                                        <th>Ruolo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($logs as $log)
                                        <tr>
                                            <td><time datetime="{{ $log['created_at'] }}">{{ \Illuminate\Support\Carbon::parse($log['created_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</time></td>
                                            <td><code>{{ $log['simulated_user_id'] }}</code></td>
                                            <td>{{ $log['scenario_label'] }}</td>
                                            <td>
                                                @if(! empty($log['operator_email']))
                                                    {{ $log['operator_email'] }}
                                                    @if(! empty($log['operator_user_id']))
                                                        <br><code>{{ $log['operator_user_id'] }}</code>
                                                    @endif
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>{{ $log['operator_role'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($logs->hasPages())
                            <nav class="saml-sim-pagination" aria-label="Paginazione log">
                                @if($logs->onFirstPage())
                                    <span class="saml-sim-pagination__btn is-disabled" aria-disabled="true">← Prec</span>
                                @else
                                    <a class="saml-sim-pagination__btn" href="{{ $logs->previousPageUrl() }}">← Prec</a>
                                @endif

                                <span class="saml-sim-pagination__info">
                                    Pagina {{ $logs->currentPage() }} di {{ $logs->lastPage() }}
                                </span>

                                @if($logs->hasMorePages())
                                    <a class="saml-sim-pagination__btn" href="{{ $logs->nextPageUrl() }}">Succ →</a>
                                @else
                                    <span class="saml-sim-pagination__btn is-disabled" aria-disabled="true">Succ →</span>
                                @endif
                            </nav>
                        @endif
                    @endif
                </div>
            </section>
        </div>

        <section class="saml-sim-card saml-sim-card--presets">
            <div class="saml-sim-card__head">
                <span>// scenario_presets</span>
                <span>random user</span>
            </div>
            <div class="saml-sim-card__body">
                <div class="saml-sim-protocol-info">
                    <span class="saml-sim-protocol-info__badge">SAML</span>
                    <span>Protocollo attivo: <strong>SAML 2.0</strong></span>
                </div>

                <div class="saml-sim-scenarios">
                    <div class="saml-sim-tabs" role="tablist">
                        <button class="saml-sim-tab is-active" role="tab" data-tab="login" type="button">Login</button>
                        <button class="saml-sim-tab" role="tab" data-tab="error" type="button">Target errore</button>
                    </div>

                    {{-- One form per scenario (SamlSimulationScenarioService::SCENARIOS). --}}
                    <div class="saml-sim-tab-panel is-active" data-panel="login">
                        @foreach($scenarios as $scenario)
                            <form
                                method="POST"
                                action="{{ $postAction }}"
                                target="_blank"
                                class="saml-sim-scenario @if(! $scenario['available']) is-disabled @endif"
                            >
                                @csrf
                                <input type="hidden" name="RelayState" value="{{ $relay }}">
                                <input type="hidden" name="scenario" value="{{ $scenario['key'] }}">

                                <span class="saml-sim-scenario__status status-{{ $scenario['status'] }}">{{ $scenario['status'] }}</span>

                                <div class="saml-sim-scenario__copy">
                                    <strong>{{ $scenario['label'] }}</strong>
                                    <span class="saml-sim-scenario__meta">
                                        @if($scenario['available'])
                                            {{ $scenario['count'] }} utenti
                                        @else
                                            nessun utente
                                        @endif
                                    </span>
                                </div>

                                <button
                                    type="submit"
                                    class="saml-sim-btn saml-sim-btn--ghost"
                                    @disabled(! $scenario['available'])
                                >
                                    Simula
                                </button>
                            </form>
                        @endforeach
                    </div>

                    {{-- Scenarios that must fail (SamlSimulationScenarioService::ERROR_SCENARIOS). --}}
                    <div class="saml-sim-tab-panel" data-panel="error" hidden>
                        @foreach($errorScenarios as $scenario)
                            <form
                                method="POST"
                                action="{{ $postAction }}"
                                target="_blank"
                                class="saml-sim-scenario saml-sim-scenario--error @if(! $scenario['available']) is-disabled @endif"
                            >
                                @csrf
                                <input type="hidden" name="RelayState" value="{{ $relay }}">
                                <input type="hidden" name="scenario" value="{{ $scenario['key'] }}">

                                <span class="saml-sim-scenario__status status-error">{{ $scenario['translate_code'] }}</span>

                                <div class="saml-sim-scenario__copy">
                                    <strong>{{ $scenario['label'] }}</strong>
                                </div>

                                <button
                                    type="submit"
                                    class="saml-sim-btn saml-sim-btn--ghost"
                                    @disabled(! $scenario['available'])
                                >
                                    Simula
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    </div>

</div>
<script>
// Scenario tabs: show the panel of the clicked tab only.
document.addEventListener('DOMContentLoaded', function() {
    var tabs = document.querySelectorAll('.saml-sim-tab');
    var panels = document.querySelectorAll('.saml-sim-tab-panel');
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var target = tab.dataset.tab;
            tabs.forEach(function(t) { t.classList.remove('is-active'); });
            panels.forEach(function(p) {
                p.classList.remove('is-active');
                p.hidden = true;
            });
            tab.classList.add('is-active');
            var panel = document.querySelector('[data-panel="' + target + '"]');
            if (panel) {
                panel.classList.add('is-active');
                panel.hidden = false;
            }
        });
    });
});
</script>
</body>
</html>
