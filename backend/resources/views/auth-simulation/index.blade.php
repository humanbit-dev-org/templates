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
        <p>Scegli protocollo, banca e scenario per simulare un login con un utente casuale nello stato desiderato.</p>
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
            <dt>User ID operatore</dt>
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
                        <input type="hidden" name="access_token" value="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...">
                        <input type="hidden" name="idfisico" value="664586">
                        <input type="hidden" name="filiale_madre" value="664586:422">
                        <input type="hidden" name="name_id" value="e65547ec-286c-433c-b65d-b2ca596e2db2">
                        <input type="hidden" name="RelayState" value="{{ $relay }}">
                        <input type="hidden" name="scenario" value="manual">
                        <input type="hidden" name="protocol" value="{{ $protocol }}">

                        <div class="saml-sim-form__row">
                            <div class="saml-sim-form__field">
                                <label for="idgiuridico">User ID</label>
                                <input
                                    id="idgiuridico"
                                    name="idgiuridico"
                                    type="text"
                                    value="{{ old('idgiuridico') }}"
                                    autocomplete="off"
                                    required
                                >
                            </div>
                            <button type="submit" class="saml-sim-btn saml-sim-btn--primary">
                                @if($protocol === 'oidc') Avvia login OIDC @else Genera &amp; invia SAMLResponse @endif
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
                <span>// tiraggio_presets</span>
                <span>random user</span>
            </div>
            <div class="saml-sim-card__body">
                <form
                    method="GET"
                    action="{{ route('auth.sim.form') }}"
                    class="saml-sim-form saml-sim-bank-filter"
                >
                    @if($relay !== '')
                        <input type="hidden" name="relay" value="{{ $relay }}">
                    @endif

                    @if($oidcAvailable)
                        <div class="saml-sim-form__field">
                            <label>Protocollo</label>
                            <div class="saml-sim-protocol-toggle" id="protocol-toggle">
                                <button
                                    type="button"
                                    class="saml-sim-protocol-card @if($protocol === 'saml') is-active @endif"
                                    data-protocol="saml"
                                    onclick="selectProtocol('saml')"
                                >
                                    <span class="saml-sim-protocol-card__badge">SAML</span>
                                    <span class="saml-sim-protocol-card__name">SAML 2.0</span>
                                </button>
                                <button
                                    type="button"
                                    class="saml-sim-protocol-card @if($protocol === 'oidc') is-active @endif"
                                    data-protocol="oidc"
                                    onclick="selectProtocol('oidc')"
                                >
                                    <span class="saml-sim-protocol-card__badge">OIDC</span>
                                    <span class="saml-sim-protocol-card__name">OpenID Connect</span>
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="saml-sim-protocol-info">
                            <span class="saml-sim-protocol-info__badge">SAML</span>
                            <span>Protocollo attivo: <strong>SAML</strong> — OIDC non disponibile per questo tenant</span>
                        </div>
                    @endif

                    <div class="saml-sim-form__field">
                        <label for="preset-banca_abi">Banca</label>
                        <select
                            id="preset-banca_abi"
                            name="banca_abi"
                            class="saml-sim-select"
                            onchange="this.form.submit()"
                        >
                            <option value="" @selected($selectedBancaAbi === null)>Tutte le banche</option>
                            @foreach($bankGroups as $groupLabel => $groupBanks)
                                <optgroup label="{{ $groupLabel }}">
                                    @foreach($groupBanks as $bank)
                                        <option value="{{ $bank['banca_abi'] }}" @selected($selectedBancaAbi === $bank['banca_abi'])>
                                            {{ $bank['nome'] }} ({{ $bank['banca_abi'] }})
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    @if($selectedBank !== null)
                        <div class="saml-sim-bank-info">
                            <div class="saml-sim-bank-info__item @if($selectedBank['multi_tiraggio']) is-on @endif">
                                <span class="saml-sim-bank-info__label">Multi-Tiraggio</span>
                                <span class="saml-sim-bank-info__value">
                                    @if($selectedBank['multi_tiraggio']) Abilitato @else Non abilitato @endif
                                </span>
                            </div>
                            <div class="saml-sim-bank-info__item @if($selectedBank['durata_parametrica']) is-on @endif">
                                <span class="saml-sim-bank-info__label">Durata Parametrica</span>
                                <span class="saml-sim-bank-info__value">
                                    @if($selectedBank['durata_parametrica']) Abilitata @else Non abilitata @endif
                                </span>
                            </div>
                            <div class="saml-sim-bank-info__item @if($selectedBank['conguaglio_periodico']) is-on @endif">
                                <span class="saml-sim-bank-info__label">Conguaglio</span>
                                <span class="saml-sim-bank-info__value">
                                    @if($selectedBank['conguaglio_periodico'])
                                        {{ ucfirst($selectedBank['metodo_conguaglio'] ?? 'periodico') }}
                                    @else
                                        Non abilitato
                                    @endif
                                </span>
                            </div>
                        </div>
                    @endif
                </form>

                <div class="saml-sim-scenarios">
                    <div class="saml-sim-tabs" role="tablist">
                        <button class="saml-sim-tab is-active" role="tab" data-tab="tiraggio" type="button">Target tiraggio</button>
                        <button class="saml-sim-tab" role="tab" data-tab="error" type="button">Target errore</button>
                    </div>

                    <div class="saml-sim-tab-panel is-active" data-panel="tiraggio">
                        @php
                            $baseScenarios = [];
                            $productScenarios = [];
                            foreach ($scenarios as $scenario) {
                                if (in_array($scenario['key'], $productScenarioKeys, true)) {
                                    $productScenarios[] = $scenario;
                                } else {
                                    $baseScenarios[] = $scenario;
                                }
                            }
                        @endphp

                        @foreach($baseScenarios as $scenario)
                            <form
                                method="POST"
                                action="{{ $postAction }}"
                                target="_blank"
                                class="saml-sim-scenario @if(! $scenario['available']) is-disabled @endif"
                            >
                                @csrf
                                <input type="hidden" name="access_token" value="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...">
                                <input type="hidden" name="idfisico" value="664586">
                                <input type="hidden" name="filiale_madre" value="664586:422">
                                <input type="hidden" name="name_id" value="e65547ec-286c-433c-b65d-b2ca596e2db2">
                                <input type="hidden" name="RelayState" value="{{ $relay }}">
                                <input type="hidden" name="scenario" value="{{ $scenario['key'] }}">
                                <input type="hidden" name="protocol" value="{{ $protocol }}">
                                @if($selectedBancaAbi !== null)
                                    <input type="hidden" name="banca_abi" value="{{ $selectedBancaAbi }}">
                                @endif

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

                        @if(! empty($productScenarios))
                            @php
                                $productGroups = collect($productScenarios)->groupBy('group');
                            @endphp

                            @foreach($visibleProductGroups as $groupKey)
                                @php
                                    $groupScenarios = $productGroups->get($groupKey, collect());
                                @endphp

                                @if($groupScenarios->isNotEmpty())
                                    <div class="saml-sim-group-label">{{ \App\Services\Restricted\SamlSimulationScenarioService::PRODUCT_GROUPS[$groupKey] }}</div>
                                    @foreach($groupScenarios as $scenario)
                                        <form
                                            method="POST"
                                            action="{{ $postAction }}"
                                            target="_blank"
                                            class="saml-sim-scenario @if(! $scenario['available']) is-disabled @endif"
                                        >
                                            @csrf
                                            <input type="hidden" name="access_token" value="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...">
                                            <input type="hidden" name="idfisico" value="664586">
                                            <input type="hidden" name="filiale_madre" value="664586:422">
                                            <input type="hidden" name="name_id" value="e65547ec-286c-433c-b65d-b2ca596e2db2">
                                            <input type="hidden" name="RelayState" value="{{ $relay }}">
                                            <input type="hidden" name="scenario" value="{{ $scenario['key'] }}">
                                            <input type="hidden" name="protocol" value="{{ $protocol }}">
                                            @if($selectedBancaAbi !== null)
                                                <input type="hidden" name="banca_abi" value="{{ $selectedBancaAbi }}">
                                            @endif

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
                                @endif
                            @endforeach
                        @endif
                    </div>

                    <div class="saml-sim-tab-panel" data-panel="error" hidden>
                        @foreach($errorScenarios as $scenario)
                            <form
                                method="POST"
                                action="{{ $postAction }}"
                                target="_blank"
                                class="saml-sim-scenario saml-sim-scenario--error @if(! $scenario['available']) is-disabled @endif"
                            >
                                @csrf
                                <input type="hidden" name="access_token" value="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...">
                                <input type="hidden" name="idfisico" value="664586">
                                <input type="hidden" name="filiale_madre" value="664586:422">
                                <input type="hidden" name="name_id" value="e65547ec-286c-433c-b65d-b2ca596e2db2">
                                <input type="hidden" name="RelayState" value="{{ $relay }}">
                                <input type="hidden" name="scenario" value="{{ $scenario['key'] }}">
                                <input type="hidden" name="protocol" value="{{ $protocol }}">
                                @if($selectedBancaAbi !== null)
                                    <input type="hidden" name="banca_abi" value="{{ $selectedBancaAbi }}">
                                @endif

                                <span class="saml-sim-scenario__status status-error">{{ $scenario['translate_code'] }}</span>

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
                </div>
            </div>
        </section>
    </div>

</div>
<script>
function selectProtocol(protocol) {
    document.querySelectorAll('.saml-sim-protocol-card').forEach(function(card) {
        card.classList.toggle('is-active', card.dataset.protocol === protocol);
    });
    document.querySelectorAll('input[name="protocol"]').forEach(function(input) {
        input.value = protocol;
    });
    var btn = document.querySelector('#saml-manual-form button[type="submit"]');
    if (btn) {
        btn.textContent = protocol === 'oidc' ? 'Avvia login OIDC' : 'Genera & invia SAMLResponse';
    }
}

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
