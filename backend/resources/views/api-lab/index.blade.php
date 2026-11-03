<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>API Lab</title>
    <link rel="stylesheet" href="{{ asset('static/css/api_lab.css') }}">
    <link rel="stylesheet" href="{{ asset('static/css/api_lab_extras.css') }}">
</head>
<body class="api-lab-page" @if($active ?? false) data-lab-key="{{ $active['key'] }}" @endif>
<div class="api-lab-scanlines" aria-hidden="true"></div>
<div class="api-lab-glow" aria-hidden="true"></div>
<div class="api-lab-vignette" aria-hidden="true"></div>
<div class="api-lab-transmit-overlay" id="lab-transmit-overlay" hidden aria-hidden="true">
    <div class="api-lab-transmit-overlay__panel">
        <div class="api-lab-transmit-overlay__content" id="lab-transmit-content"></div>
    </div>
</div>

<header class="api-lab-header">
    <div class="api-lab-header__chrome">
        <span class="dot dot-red"></span>
        <span class="dot dot-amber"></span>
        <span class="dot dot-green"></span>
        <span class="api-lab-header__session">session://api-lab</span>
    </div>
    <div class="api-lab-header__badge">// RESTRICTED</div>
    <h1>API Lab<span class="api-lab-cursor" aria-hidden="true"></span></h1>
    <p>Handler source · pipeline trace · quality radar · DB rollback on mutations</p>
</header>

@php
    $networkInfo = $networkInfo ?? ['docker_ip' => null, 'external_ip' => null, 'client_ip' => null];
@endphp
<div class="api-lab-hud-bar" aria-hidden="false">
    <aside class="api-lab-hud" aria-label="Network telemetry">
        <div class="api-lab-hud__row">
            <div class="api-lab-hud__terminal hud-live" id="lab-hud-terminal" aria-label="Uplink chatter">
                <div class="api-lab-hud__terminal-bar">
                    <span class="dot dot-red"></span>
                    <span class="dot dot-amber"></span>
                    <span class="dot dot-green"></span>
                    <span class="api-lab-hud__terminal-title">// uplink</span>
                    <div class="api-lab-hud__tools" role="toolbar" aria-label="Quick tools">
                        <button type="button" class="api-lab-hud-tool" data-tool="curl" title="Copy request as cURL" @if(!($active ?? false)) disabled @endif>curl</button>
                        <button type="button" class="api-lab-hud-tool" data-tool="json" title="Copy last response JSON (run transmit first)" @if(!($active ?? false)) disabled @endif>json</button>
                        <button type="button" class="api-lab-hud-tool" data-tool="ping" title="Probe current URI (latency)" @if(!($active ?? false)) disabled @endif>ping</button>
                        <button type="button" class="api-lab-hud-tool" data-tool="wipe" title="Clear execution output" @if(!($active ?? false)) disabled @endif>wipe</button>
                        <button type="button" class="api-lab-hud-tool" data-tool="help" title="Show keyboard shortcuts">help</button>
                    </div>
                </div>
                <div class="api-lab-hud__stage" id="lab-hud-stage" aria-live="polite"></div>
            </div>
            <div class="api-lab-hud__net">
            <div class="api-lab-hud__net-row">
                <span class="api-lab-hud__label">downlink</span>
                <code id="lab-downlink" class="api-lab-hud__downlink" title="Browser estimate or live probe">…</code>
            </div>
            <div class="api-lab-hud__net-row">
                <span class="api-lab-hud__label">docker</span>
                <code id="lab-ip-docker">{{ $networkInfo['docker_ip'] ?? '—' }}</code>
            </div>
            <div class="api-lab-hud__net-row">
                <span class="api-lab-hud__label">external</span>
                <code id="lab-ip-external">{{ $networkInfo['external_ip'] ?? '—' }}</code>
            </div>
            <div class="api-lab-hud__net-row">
                <span class="api-lab-hud__label">client</span>
                <code>{{ $networkInfo['client_ip'] ?? '—' }}</code>
            </div>
        </div>
        </div>
    </aside>
</div>

@if($groups->isEmpty())
    <p class="api-lab-empty">No endpoints in config/api_lab.php</p>
@else
    <div class="api-lab-layout">
        <aside class="api-lab-sidebar" aria-label="Endpoint catalog">
            <div class="api-lab-sidebar__head">
                <span class="api-lab-sidebar__label">// catalog</span>
                <p class="api-lab-sidebar__hint">Titolo e path — sezioni chiuse di default, clic per espandere.</p>
                <label class="api-lab-sidebar__search-wrap">
                    <span class="visually-hidden">Filter endpoints</span>
                    <input
                        type="search"
                        id="lab-catalog-search"
                        class="api-lab-sidebar__search"
                        placeholder="Filter path, route, title…"
                        autocomplete="off"
                        spellcheck="false"
                    >
                </label>
            </div>
            <div class="api-lab-sidebar__scroll">
            @foreach($groups as $group)
                <section
                    class="api-lab-sector"
                    data-sector="{{ $group['key'] }}"
                >
                    <button
                        type="button"
                        class="api-lab-sector__toggle"
                        aria-expanded="false"
                        aria-controls="lab-sector-{{ $group['key'] }}"
                    >
                        <span class="api-lab-sector__chevron" aria-hidden="true"></span>
                        <span class="api-lab-sector__label">
                            <span class="api-lab-sector__title">{{ $group['label'] }}</span>
                            <span class="api-lab-sector__file">routes/{{ $group['file'] }}</span>
                        </span>
                        <span class="api-lab-sector__count">{{ $group['endpoints']->count() }}</span>
                    </button>
                    <div class="api-lab-sector__body" id="lab-sector-{{ $group['key'] }}" hidden>
                    @if($group['groups']->isNotEmpty())
                        @foreach($group['ungrouped'] as $endpoint)
                            @include('api-lab._link')
                        @endforeach
                        {{-- Second level, e.g. one sub-section per bank. --}}
                        @foreach($group['groups'] as $sub)
                            <section class="api-lab-sector api-lab-sector--nested" data-sector="{{ $sub['key'] }}">
                                <button
                                    type="button"
                                    class="api-lab-sector__toggle"
                                    aria-expanded="false"
                                    aria-controls="lab-sector-{{ $sub['key'] }}"
                                >
                                    <span class="api-lab-sector__chevron" aria-hidden="true"></span>
                                    <span class="api-lab-sector__label">
                                        <span class="api-lab-sector__title">{{ $sub['label'] }}</span>
                                    </span>
                                    <span class="api-lab-sector__count">{{ $sub['endpoints']->count() }}</span>
                                </button>
                                <div class="api-lab-sector__body" id="lab-sector-{{ $sub['key'] }}" hidden>
                                @foreach($sub['endpoints'] as $endpoint)
                                    @include('api-lab._link')
                                @endforeach
                                </div>
                            </section>
                        @endforeach
                    @else
                        @foreach($group['endpoints'] as $endpoint)
                            @include('api-lab._link')
                        @endforeach
                    @endif
                    </div>
                </section>
            @endforeach
            </div>
        </aside>

        @if($active)
            @php
                $bodyJson = $active['sample_body'] !== null
                    ? json_encode($active['sample_body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : '{}';
                $queryJson = ! empty($active['sample_query'])
                    ? json_encode($active['sample_query'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : '{}';
                $defaultHeaders = json_encode(
                    ($active['body_encoding'] ?? 'json') === 'form'
                        ? ['Accept' => 'application/json']
                        : ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
                    JSON_PRETTY_PRINT,
                );
            @endphp
            <main class="api-lab-main" id="api-lab"
                  data-execute-url="{{ route('api-lab.execute', ['key' => $active['key']]) }}"
                  data-source-file="{{ $active['source_file'] ?? '' }}">
                <div class="api-lab-hero">
                    <div class="api-lab-terminal api-lab-terminal--hero">
                        <div class="api-lab-terminal__bar">
                            <span>auto-protocol</span>
                            <code>{{ $active['controller'] ?? $active['route_name'] }}</code>
                        </div>
                        @if($active['doc_summary'])
                            <p class="api-lab-protocol-summary">{{ $active['doc_summary'] }}</p>
                        @endif
                        @if(!empty($active['protocol_lines']))
                            <ul class="api-lab-protocol-steps">
                                @foreach($active['protocol_lines'] as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if(!empty($active['inputs']))
                            <div class="api-lab-inputs">
                                @foreach($active['inputs'] as $input)
                                    <span class="chip">{{ $input['name'] }}{{ $input['required'] ? '*' : '' }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="api-lab-workbench">
                <aside class="api-lab-workbench__code">
                {{-- The handler's source, read-only: API Lab runs the code on disk, never an edited copy. --}}
                <section class="api-lab-sandbox" id="lab-sandbox">
                    <div class="api-lab-sandbox__chrome">
                        <div class="api-lab-sandbox__head">
                            <h3 class="api-lab-block__title">handler source</h3>
                        </div>
                        <p class="api-lab-sandbox__file" id="lab-sandbox-file">{{ $active['source_file'] ?? '—' }}</p>
                        @if(($active['handler_kind'] ?? '') === 'closure')
                            <p class="api-lab-sandbox__route-lock">route <code>{{ $active['route_name'] }}</code> · {{ $active['uri'] }}</p>
                        @endif
                    </div>
                    <div class="api-lab-sandbox__scroll">
                        <textarea id="lab-sandbox-source" class="api-lab-sandbox__code" spellcheck="false" readonly>{{ $active['handler_source'] ?? '' }}</textarea>
                    </div>
                </section>
                </aside>

                <div class="api-lab-workbench__panel">
                <form id="api-lab-form" class="api-lab-form">
                    <div class="api-lab-form__row">
                        <div class="api-lab-form__cell api-lab-form__cell--method">
                            <span class="lbl">method</span>
                            @if($active['method_selectable'])
                                <select id="lab-method">
                                    @foreach($active['methods'] as $method)
                                        <option value="{{ $method }}" @selected($method === $active['default_method'])>{{ $method }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="hidden" id="lab-method" value="{{ $active['default_method'] }}">
                                <span class="api-lab-method-fixed">{{ $active['default_method'] }}</span>
                            @endif
                        </div>
                        <div class="api-lab-form__cell api-lab-form__cell--uri">
                            <span class="lbl">uri</span>
                            <input type="text" id="lab-uri" readonly value="{{ $active['uri'] }}">
                        </div>
                    </div>

                    @foreach($active['parameter_names'] as $param)
                        <label>
                            <span class="lbl">path · {{ $param }}</span>
                            <input type="text" class="path-param" data-param="{{ $param }}"
                                   value="{{ ($active['path_param_defaults'][$param] ?? '') }}">
                        </label>
                    @endforeach

                    <label>
                        <span class="lbl">request headers (JSON)</span>
                        <textarea id="lab-headers" rows="4">{{ $defaultHeaders }}</textarea>
                    </label>

                    @if($active['show_query'])
                        <label>
                            <span class="lbl">query (JSON)</span>
                            <textarea id="lab-query" rows="3">{{ $queryJson }}</textarea>
                        </label>
                    @endif

                    @if($active['show_body'])
                        <label>
                            <span class="lbl">request body (JSON)</span>
                            <textarea id="lab-body" rows="10">{{ $bodyJson }}</textarea>
                        </label>
                    @endif

                    @if($active['needs_sanctum'])
                        <label>
                            <span class="lbl">sanctum user_id</span>
                            <input type="text" id="lab-acting-as"
                                   value="{{ backpack_auth()->user()?->user_id }}">
                        </label>
                    @endif

                    @if(($active['body_encoding'] ?? 'json') === 'form')
                        <p class="api-lab-sandbox__note">Body is sent as <code>application/x-www-form-urlencoded</code> (edit JSON fields below; API Lab encodes them as form fields).</p>
                    @endif

                    @if(($active['route_name'] ?? '') === 'b2b.nexi.oauth.token')
                        <p class="api-lab-sandbox__note">Leave <code>client_id</code> / <code>client_secret</code> empty to use server <code>OAUTH_RAYS_*</code> credentials.</p>
                    @endif

                    @if($active['needs_tenant_token'] ?? false)
                        <p class="api-lab-sandbox__note">B2B tenant Bearer: call <code>b2b.nexi.oauth.token</code> under <strong>Simulate inbound</strong> first (or leave Authorization empty for auto-inject).</p>
                    @endif
                </form>

                <section class="api-lab-output" id="lab-output" hidden>
                    <div class="api-lab-output__meta">
                        <span id="lab-duration"></span>
                        <span id="lab-rollback"></span>
                    </div>
                    <div id="lab-inbound" class="api-lab-block"></div>
                    <div id="lab-steps" class="api-lab-steps"></div>
                    <div id="lab-telemetry" class="api-lab-telemetry" hidden aria-label="Runtime telemetry"></div>
                </section>

                <div class="api-lab-transmit-bar" aria-label="Transmit controls">
                    <button type="submit" form="api-lab-form" id="lab-send" class="api-lab-send">
                        <span class="prompt">&gt;</span> transmit
                    </button>
                </div>
                </div>
                </div>
            </main>
        @endif
    </div>
@endif

<script>
(function () {
    const probeUrl = @json($groups->isNotEmpty() ? route('api-lab.probe') : '');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    let lastResponseJson = null;
    let hudPausedUntil = 0;
    let hudTickerTimer = null;
    let hudFlashTimer = null;
    let hudUnpauseTimer = null;
    let hudDestructInterval = null;
    let hudShowNextFn = null;
    const HUD_TOOL_PAUSE_MS = 10000;

    function clearHudPauseTimers() {
        if (hudFlashTimer) {
            clearTimeout(hudFlashTimer);
            hudFlashTimer = null;
        }
        if (hudUnpauseTimer) {
            clearTimeout(hudUnpauseTimer);
            hudUnpauseTimer = null;
        }
        if (hudTickerTimer) {
            clearTimeout(hudTickerTimer);
            hudTickerTimer = null;
        }
        if (hudDestructInterval) {
            clearInterval(hudDestructInterval);
            hudDestructInterval = null;
        }
    }

    function pauseHudEffects(ms = HUD_TOOL_PAUSE_MS) {
        clearHudPauseTimers();
        hudPausedUntil = Date.now() + ms;

        const terminal = document.getElementById('lab-hud-terminal');
        if (terminal) {
            terminal.classList.add('hud-paused');
            hudUnpauseTimer = setTimeout(() => {
                terminal.classList.remove('hud-paused');
                hudUnpauseTimer = null;
            }, ms);
        }

        if (hudShowNextFn) {
            hudTickerTimer = setTimeout(hudShowNextFn, ms);
        }
    }

    function flashHud(message, ms = HUD_TOOL_PAUSE_MS) {
        pauseHudEffects(ms);
        const stage = document.getElementById('lab-hud-stage');
        if (!stage) return;

        stage.innerHTML = '<div class="hud-event hud-event--beacon"><p class="hud-beacon-line">' + message + '</p></div>';
        stage.className = 'api-lab-hud__stage hud-active hud-tool-flash';

        hudFlashTimer = setTimeout(() => {
            if (stage.classList.contains('hud-tool-flash')) {
                stage.innerHTML = '';
                stage.className = 'api-lab-hud__stage';
            }
            hudFlashTimer = null;
        }, ms);
    }

    function extractLastResponseJson(steps) {
        if (!Array.isArray(steps)) return null;
        for (let i = steps.length - 1; i >= 0; i--) {
            const body = steps[i]?.response?.body_pretty;
            if (body && String(body).trim()) return String(body);
        }
        return null;
    }

    function formatThroughput(mbps) {
        if (!Number.isFinite(mbps) || mbps <= 0) return '—';
        if (mbps >= 1) return mbps.toFixed(1) + ' Mbps';
        return Math.round(mbps * 1000) + ' Kbps';
    }

    function initHudNetwork() {
        const el = document.getElementById('lab-downlink');
        if (!el) return;

        async function probeDownload() {
            if (!probeUrl) throw new Error('no probe');
            const samples = [];
            for (let i = 0; i < 3; i++) {
                const url = probeUrl + '?b=' + Date.now() + '-' + i + '-' + Math.random().toString(36).slice(2);
                const start = performance.now();
                const res = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
                if (!res.ok) continue;
                const blob = await res.blob();
                const sec = (performance.now() - start) / 1000;
                if (sec > 0 && blob.size > 0) samples.push((blob.size * 8) / sec / 1e6);
            }
            if (!samples.length) throw new Error('probe failed');
            samples.sort((a, b) => a - b);
            return { mbps: samples[Math.floor(samples.length / 2)], tag: ' live' };
        }

        async function update() {
            el.textContent = '…';
            el.classList.remove('is-live', 'is-estimate', 'is-tx');
            try {
                const sample = await probeDownload();
                el.textContent = formatThroughput(sample.mbps) + sample.tag;
                el.classList.add('is-live');
            } catch {
                el.textContent = '—';
            }
        }

        update();
        setInterval(update, 60000);
    }

    function initHudTools() {
        const root = document.getElementById('api-lab');
        document.querySelectorAll('.api-lab-hud-tool').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const tool = btn.dataset.tool;
                if (btn.disabled) return;
                pauseHudEffects();

                if (tool === 'help') {
                    flashHud(
                        '<span class="hud-keys"><b>help</b> · transmit = run request · json = copy last response body · curl = copy cURL · ping = latency probe · wipe = clear output · click catalog item to switch route</span>',
                    );
                    return;
                }

                if (!root) return;

                if (tool === 'curl') {
                    const method = document.getElementById('lab-method')?.value ?? 'GET';
                    const uri = document.getElementById('lab-uri')?.value ?? '/';
                    let lines = ["curl -sS -X " + method + " '" + window.location.origin + uri + "'"];
                    lines.push("  -H 'Accept: application/json'");
                    try {
                        const headers = JSON.parse(document.getElementById('lab-headers')?.value?.trim() || '{}');
                        Object.entries(headers).forEach(([k, v]) => {
                            lines.push("  -H '" + k + ": " + String(v).replace(/'/g, "'\\''") + "'");
                        });
                    } catch (e) {
                        flashHud('headers JSON invalid');
                        return;
                    }
                    if (csrf) lines.push("  -H 'X-CSRF-TOKEN: " + csrf + "'");
                    const bodyEl = document.getElementById('lab-body');
                    if (bodyEl && bodyEl.value.trim()) {
                        lines.push("  -H 'Content-Type: application/json'");
                        lines.push("  -d '" + bodyEl.value.trim().replace(/'/g, "'\\''") + "'");
                    }
                    await navigator.clipboard.writeText(lines.join(' \\\n'));
                    flashHud('cURL copied to clipboard');
                    return;
                }

                if (tool === 'json') {
                    if (!lastResponseJson) {
                        flashHud('run <b>transmit</b> first — then json copies response body');
                        return;
                    }
                    try {
                        const parsed = JSON.parse(lastResponseJson);
                        const pretty = JSON.stringify(parsed, null, 2);
                        await navigator.clipboard.writeText(pretty);
                        flashHud('response JSON copied (' + pretty.length + ' chars)');
                    } catch {
                        await navigator.clipboard.writeText(lastResponseJson);
                        flashHud('response body copied (raw)');
                    }
                    return;
                }

                if (tool === 'wipe') {
                    const output = document.getElementById('lab-output');
                    if (output) output.hidden = true;
                    document.getElementById('lab-inbound').innerHTML = '';
                    document.getElementById('lab-steps').innerHTML = '';
                    const tel = document.getElementById('lab-telemetry');
                    if (tel) { tel.hidden = true; tel.innerHTML = ''; }
                    lastResponseJson = null;
                    flashHud('output cleared');
                    return;
                }

                if (tool === 'ping') {
                    const uri = document.getElementById('lab-uri')?.value ?? '/health';
                    const method = (document.getElementById('lab-method')?.value ?? 'GET').toUpperCase();
                    const down = document.getElementById('lab-downlink');
                    const t0 = performance.now();
                    try {
                        const res = await fetch(uri, {
                            method,
                            credentials: 'same-origin',
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        const ms = Math.round(performance.now() - t0);
                        if (down) down.textContent = ms + ' ms · HTTP ' + res.status;
                        flashHud('ping ' + uri + ' → ' + res.status + ' in ' + ms + ' ms');
                    } catch (e) {
                        flashHud('ping failed: ' + e.message);
                    }
                }
            });
        });
    }

    const root = document.getElementById('api-lab');
    const sidebarScroll = document.querySelector('.api-lab-sidebar__scroll');
    const SIDEBAR_SCROLL_KEY = 'api-lab-sidebar-scroll';

    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }

    function restoreSidebarScroll() {
        if (!sidebarScroll) {
            return;
        }

        const saved = sessionStorage.getItem(SIDEBAR_SCROLL_KEY);
        if (saved !== null) {
            const top = parseInt(saved, 10);
            if (!Number.isNaN(top)) {
                sidebarScroll.scrollTop = top;
            }

            return;
        }

        const active = sidebarScroll.querySelector('.api-lab-link.is-active');
        if (active) {
            active.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        }
    }

    restoreSidebarScroll();

    const SECTOR_STATE_KEY = 'api-lab-sector-state-v2';

    function loadSectorState() {
        try {
            return JSON.parse(sessionStorage.getItem(SECTOR_STATE_KEY) || '{}');
        } catch {
            return {};
        }
    }

    function saveSectorState(state) {
        sessionStorage.setItem(SECTOR_STATE_KEY, JSON.stringify(state));
    }

    function setSectorOpen(sector, open) {
        sector.classList.toggle('is-open', open);
        const body = sector.querySelector('.api-lab-sector__body');
        if (body) {
            body.hidden = !open;
        }
        const toggle = sector.querySelector('.api-lab-sector__toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    function initSectorCollapsibles() {
        if (!sidebarScroll) {
            return;
        }

        const state = loadSectorState();

        sidebarScroll.querySelectorAll('.api-lab-sector').forEach((sector) => {
            const key = sector.dataset.sector;
            setSectorOpen(sector, state[key] === true);

            sector.querySelector('.api-lab-sector__toggle')?.addEventListener('click', () => {
                const next = !sector.classList.contains('is-open');
                setSectorOpen(sector, next);
                const nextState = loadSectorState();
                nextState[key] = next;
                saveSectorState(nextState);
            });
        });
    }

    initSectorCollapsibles();

    const catalogSearch = document.getElementById('lab-catalog-search');
    if (catalogSearch && sidebarScroll) {
        catalogSearch.addEventListener('input', () => {
            const query = catalogSearch.value.trim().toLowerCase();
            const state = loadSectorState();

            sidebarScroll.querySelectorAll('.api-lab-sector').forEach((sector) => {
                let visibleLinks = 0;

                sector.querySelectorAll('.api-lab-link').forEach((link) => {
                    const haystack = link.dataset.search || '';
                    const show = query === '' || haystack.includes(query);
                    link.hidden = !show;
                    if (show) {
                        visibleLinks++;
                    }
                });

                if (visibleLinks === 0) {
                    sector.hidden = true;
                    return;
                }

                sector.hidden = false;
                setSectorOpen(sector, query !== '' || state[sector.dataset.sector] === true);
            });
        });
    }

    initHudNetwork();
    initHudTools();

    document.querySelectorAll('.api-lab-link').forEach((link) => {
        link.addEventListener('click', (e) => {
            if (link.classList.contains('is-active')) {
                e.preventDefault();
                link.classList.add('is-locked');
                setTimeout(() => link.classList.remove('is-locked'), 400);
                return;
            }

            if (sidebarScroll) {
                sessionStorage.setItem(SIDEBAR_SCROLL_KEY, String(sidebarScroll.scrollTop));
            }

            link.classList.add('is-navigating');
            document.body.classList.add('api-lab--route-switch');
            sessionStorage.setItem('api-lab-flash', '1');
        });
    });

    if (!root) return;

    const form = document.getElementById('api-lab-form');
    const output = document.getElementById('lab-output');
    const showBody = @json($active['show_body'] ?? false);
    const showQuery = @json($active['show_query'] ?? false);
    const transmitOverlay = document.getElementById('lab-transmit-overlay');
    const transmitContent = document.getElementById('lab-transmit-content');
    const mainPanel = document.querySelector('.api-lab-main');
    let modeTimers = [];
    let modeCycleTimer = null;
    const MODE_CYCLE_MS = 3200;

    const SCROLL_OUTPUT_OFFSET = 72;

    function scrollLabToBottom() {
        if (!mainPanel || !output) {
            return;
        }

        requestAnimationFrame(() => {
            const panelTop = mainPanel.getBoundingClientRect().top;
            const outTop = output.getBoundingClientRect().top;
            const target = outTop - panelTop + mainPanel.scrollTop - SCROLL_OUTPUT_OFFSET;
            mainPanel.scrollTo({ top: Math.max(0, target), behavior: 'smooth' });
        });
    }

    function clearModeTimers() {
        modeTimers.forEach((id) => {
            clearInterval(id);
            clearTimeout(id);
        });
        modeTimers = [];
    }

    function pushTimer(id) {
        modeTimers.push(id);
        return id;
    }

    const transmitModes = [
        {
            id: 'typing',
            run(container) {
                const lines = [
                    '$ ./api-lab transmit --sign-request',
                    '$ wrapping handler dispatch...',
                    '$ serializing inbound envelope...',
                ];
                let line = 0;
                let char = 0;
                container.innerHTML = '<div class="tx-line"><span class="tx-prompt">&gt;</span><span class="tx-typed"></span><span class="tx-cursor">█</span></div>';
                const typed = container.querySelector('.tx-typed');
                const tick = pushTimer(setInterval(() => {
                    const text = lines[line];
                    typed.textContent = text.slice(0, char);
                    char++;
                    if (char > text.length) {
                        char = 0;
                        line = (line + 1) % lines.length;
                    }
                }, 38));
            },
        },
        {
            id: 'ping',
            run(container) {
                container.innerHTML = '<div class="tx-ping-host">PING uplink.internal (10.42.0.1) 56(84) bytes</div><div class="tx-ping-lines"></div>';
                const box = container.querySelector('.tx-ping-lines');
                let seq = 1;
                const tick = pushTimer(setInterval(() => {
                    const ms = (8 + Math.random() * 24).toFixed(1);
                    const row = document.createElement('div');
                    row.className = 'tx-ping-row';
                    row.textContent = '64 bytes from 10.42.0.1: icmp_seq=' + seq + ' ttl=64 time=' + ms + ' ms';
                    box.appendChild(row);
                    if (box.children.length > 5) box.removeChild(box.firstChild);
                    seq++;
                }, 520));
            },
        },
        {
            id: 'tls',
            run(container) {
                const lines = [
                    '* TLSv1.3 (OUT), TLS handshake, Client hello',
                    '* TLSv1.3 (IN), TLS handshake, Server hello',
                    '* SSL connection using TLSv1.3 / AES-256-GCM',
                    '* ALPN, server accepted to use http/1.1',
                ];
                let i = 0;
                container.innerHTML = '<div class="tx-tls-lines"></div>';
                const box = container.querySelector('.tx-tls-lines');
                const add = () => {
                    if (i >= lines.length) {
                        i = 0;
                        box.innerHTML = '';
                    }
                    const row = document.createElement('div');
                    row.className = 'tx-tls-row';
                    row.textContent = lines[i++];
                    box.appendChild(row);
                    if (box.children.length > 4) box.removeChild(box.firstChild);
                };
                add();
                pushTimer(setInterval(add, 700));
            },
        },
        {
            id: 'progress',
            run(container) {
                container.innerHTML = '<div class="tx-progress-label">injecting payload stream</div><div class="tx-progress-bar">[<span class="tx-progress-fill"></span>]</div><div class="tx-progress-pct">0%</div>';
                const fill = container.querySelector('.tx-progress-fill');
                const pct = container.querySelector('.tx-progress-pct');
                let n = 0;
                pushTimer(setInterval(() => {
                    n = (n + 3) % 101;
                    const blocks = Math.round(n / 5);
                    fill.textContent = '█'.repeat(blocks) + '░'.repeat(20 - blocks);
                    pct.textContent = n + '%';
                }, 90));
            },
        },
        {
            id: 'trace',
            run(container) {
                const hops = [
                    'traceroute to route-handler (internal)',
                    ' 1  gateway.internal (1.8 ms)',
                    ' 2  sanctum-middleware (6.2 ms)',
                    ' 3  pipeline.worker (11.4 ms)',
                    ' 4  route-handler (15.1 ms)',
                ];
                let i = 0;
                container.innerHTML = '<div class="tx-trace-lines"></div>';
                const box = container.querySelector('.tx-trace-lines');
                const add = () => {
                    if (i === 0) box.innerHTML = '';
                    if (i < hops.length) {
                        const row = document.createElement('div');
                        row.className = 'tx-trace-row';
                        row.textContent = hops[i++];
                        box.appendChild(row);
                    } else {
                        i = 0;
                    }
                };
                add();
                pushTimer(setInterval(add, 550));
            },
        },
    ];

    let lastTransmitModeId = null;

    function pickRandomTransmitMode() {
        let pool = transmitModes;
        if (transmitModes.length > 1 && lastTransmitModeId) {
            pool = transmitModes.filter((m) => m.id !== lastTransmitModeId);
        }
        const mode = pool[Math.floor(Math.random() * pool.length)];
        lastTransmitModeId = mode.id;
        return mode;
    }

    function runTransmitMode() {
        if (!transmitContent) return;
        const mode = pickRandomTransmitMode();
        transmitContent.className = 'api-lab-transmit-overlay__content mode-' + mode.id;
        clearModeTimers();
        mode.run(transmitContent);
    }

    if (sessionStorage.getItem('api-lab-flash') === '1') {
        sessionStorage.removeItem('api-lab-flash');
        root.classList.add('is-booting');
        setTimeout(() => root.classList.remove('is-booting'), 700);
    }

    function startTransmitUi() {
        document.body.classList.add('api-lab--transmitting');
        root.classList.add('is-scanning');
        if (transmitOverlay) transmitOverlay.hidden = false;
        lastTransmitModeId = null;
        runTransmitMode();
        modeCycleTimer = setInterval(runTransmitMode, MODE_CYCLE_MS);
    }

    function stopTransmitUi() {
        document.body.classList.remove('api-lab--transmitting');
        root.classList.remove('is-scanning');
        clearModeTimers();
        if (modeCycleTimer) {
            clearInterval(modeCycleTimer);
            modeCycleTimer = null;
        }
        if (transmitOverlay) transmitOverlay.hidden = true;
        if (transmitContent) transmitContent.innerHTML = '';
    }

    function esc(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function colorJson(text) {
        if (!text || text === '—') return esc('—');
        try {
            const parsed = JSON.parse(text);
            text = JSON.stringify(parsed, null, 2);
        } catch (e) { /* keep raw */ }
        return text.replace(
            /("(?:\\.|[^"\\])*")(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?/g,
            (match, str, colon, lit) => {
                if (lit) return '<span class="json-lit">' + lit + '</span>';
                if (colon) return '<span class="json-key">' + str + '</span>' + colon;
                return '<span class="json-str">' + str + '</span>';
            }
        );
    }

    function pre(text) {
        const raw = text || '—';
        const colored = raw === '—' ? esc('—') : colorJson(raw);
        return '<pre class="api-lab-pre api-lab-pre--json">' + colored + '</pre>';
    }

    function prePlain(text) {
        return '<pre class="api-lab-pre">' + esc(text || '—') + '</pre>';
    }

    function formatHeaders(headers) {
        if (!headers || typeof headers !== 'object') return '—';
        return Object.entries(headers)
            .map(([k, v]) => k + ': ' + (Array.isArray(v) ? v.join(', ') : String(v ?? '')))
            .join('\n');
    }

    function renderTelemetry(metrics, quality) {
        const el = document.getElementById('lab-telemetry');
        if (!el || (!metrics && !quality)) return;
        el.hidden = false;

        let html = '<div class="api-lab-telemetry__panel">';

        if (metrics) {
            const items = [
                ['total', metrics.duration_ms + ' ms'],
                ['handler', metrics.handler_ms + ' ms'],
                ['outbound', metrics.outbound_ms + ' ms · ' + metrics.outbound_calls + ' call(s)'],
                ['sql', metrics.sql_queries + ' q · ' + metrics.sql_time_ms + ' ms'],
                ['memory', metrics.memory_peak_mb + ' MB peak'],
                ['payload', Math.round((metrics.response_bytes || 0) / 1024 * 10) / 10 + ' KB'],
            ];
            html += '<div class="api-lab-telemetry__metrics">';
            items.forEach(([k, v]) => {
                html += '<span class="api-lab-metric-chip"><b>' + esc(k) + '</b> ' + esc(v) + '</span>';
            });
            html += '</div>';
        }

        if (quality) {
            const dims = quality.dimensions || {};
            const score = quality.score || 0;
            const grade = quality.grade || '—';
            const dimKeys = Object.keys(dims);
            const gradeClass = 'grade-' + esc(String(grade).toLowerCase());

            html += '<div class="api-lab-telemetry__quality">';
            html += '<span class="api-lab-telemetry__tag">// quality</span>';
            html += '<span class="api-lab-telemetry__score ' + gradeClass + '">' + esc(String(score)) + '</span>';
            html += '<span class="api-lab-telemetry__grade ' + gradeClass + '">' + esc(grade) + '</span>';
            if (dimKeys.length) {
                html += '<div class="api-lab-telemetry__tracks">';
                dimKeys.forEach((name) => {
                    const d = dims[name];
                    const level = d.score >= 85 ? 'ok' : (d.score >= 65 ? 'warn' : 'fail');
                    html += '<div class="api-lab-telemetry__track track-' + level + '" title="' + esc(name) + '">';
                    html += '<span class="api-lab-telemetry__track-name">' + esc(name) + '</span>';
                    html += '<span class="api-lab-telemetry__track-bar"><i style="width:' + esc(String(d.score)) + '%"></i></span>';
                    html += '<span class="api-lab-telemetry__track-val">' + esc(String(d.score)) + '</span>';
                    html += '</div>';
                });
                html += '</div>';
            }
            html += '</div>';

            const notes = dimKeys.flatMap((name) => (dims[name].items || []).map((item) => ({ name, item })));
            if (notes.length) {
                html += '<details class="api-lab-telemetry__drill"><summary>// drill-down</summary><ul>';
                notes.forEach(({ name, item }) => {
                    html += '<li class="q-' + esc(item.level) + '"><span>' + esc(name) + '</span> ' + esc(item.text) + '</li>';
                });
                html += '</ul></details>';
            }
        }

        html += '</div>';
        el.innerHTML = html;
    }

    function initHudTicker() {
        const stage = document.getElementById('lab-hud-stage');
        const terminal = document.getElementById('lab-hud-terminal');
        if (!stage) return;

        const randHex = (n) => Array.from({ length: n }, () =>
            Math.floor(Math.random() * 16).toString(16)).join('');

        const events = [
            {
                id: 'hack',
                html: () => '<div class="hud-event hud-event--hack"><pre class="hud-ascii">  [####]  unknown\n    |  \\\n    |   &gt; door probe...\n    v\n  /______\\  DENIED</pre><span class="hud-caption">intrusion attempt blocked</span></div>',
            },
            {
                id: 'police',
                html: () => {
                    const t = 'TRACE WARRANT #' + Math.floor(Math.random() * 9000);
                    return '<div class="hud-event hud-event--police"><p class="hud-glitch" data-text="' + esc(t) + '">' + esc(t) + '</p><span class="hud-caption">law-enforcement trace</span></div>';
                },
            },
            {
                id: 'packets',
                html: () => {
                    let pkts = '';
                    for (let i = 0; i < 6; i++) {
                        pkts += '<span class="hud-packet" style="--d:' + (i * 0.12) + 's">#' + Math.floor(Math.random() * 255) + ' TCP</span>';
                    }
                    return '<div class="hud-event hud-event--packets"><div class="hud-packet-lane">' + pkts + '</div><span class="hud-caption">packets on the wire</span></div>';
                },
            },
            {
                id: 'matrix',
                html: () => {
                    let lines = '';
                    for (let i = 0; i < 5; i++) {
                        lines += '<span class="hud-matrix-line" style="--i:' + i + '">' + randHex(14 + (i % 3) * 4) + '</span>';
                    }
                    return '<div class="hud-event hud-event--matrix"><div class="hud-matrix">' + lines + '</div><span class="hud-caption">cipher rain</span></div>';
                },
            },
            {
                id: 'scan',
                html: () => {
                    const ports = [22, 80, 443, 3306, 6379, 8080];
                    let lines = '';
                    ports.slice(0, 4).forEach((p, i) => {
                        const open = Math.random() > 0.55;
                        lines += '<div class="hud-scan-line" style="--i:' + i + '">:' + p + ' … ' + (open ? 'OPEN' : 'filtered') + '</div>';
                    });
                    return '<div class="hud-event hud-event--scan">' + lines + '<span class="hud-caption">port sweep</span></div>';
                },
            },
            {
                id: 'beacon',
                html: () => '<div class="hud-event hud-event--beacon"><p class="hud-beacon-line">&gt; uplink heartbeat OK <span class="hud-beacon-cursor">█</span></p><span class="hud-caption">beacon / keep-alive</span></div>',
            },
            {
                id: 'cipher',
                html: () => {
                    const blob = randHex(28) + ' ' + randHex(20);
                    return '<div class="hud-event hud-event--cipher"><p class="hud-cipher">' + blob + '</p><span class="hud-caption">key rotation</span></div>';
                },
            },
            {
                id: 'gameover',
                html: () => '<div class="hud-event hud-event--gameover"><p class="hud-gameover">GAME OVER</p><p class="hud-gameover-sub">INSERT ROUTE TO CONTINUE</p><span class="hud-caption">arcade buffer</span></div>',
            },
            {
                id: 'loot',
                html: () => '<div class="hud-event hud-event--loot"><p class="hud-loot">+420 XP · PIPELINE CLEARED</p><p class="hud-loot-sub">ACHIEVEMENT: zero-day avoided</p><span class="hud-caption">loot drop</span></div>',
            },
            {
                id: 'boss',
                html: () => '<div class="hud-event hud-event--boss"><p class="hud-boss">BOSS: Latency Daemon</p><div class="hud-boss-hp"><i style="width:' + (35 + Math.floor(Math.random() * 50)) + '%"></i></div><span class="hud-caption">raid encounter</span></div>',
            },
            {
                id: 'konami',
                html: () => '<div class="hud-event hud-event--konami"><p class="hud-konami">↑ ↑ ↓ ↓ ← → ← → B A</p><span class="hud-caption">cheat buffer armed</span></div>',
            },
            {
                id: 'destruct',
                html: () => {
                    const n = 5 + Math.floor(Math.random() * 6);
                    return '<div class="hud-event hud-event--destruct" data-count="' + n + '"><p class="hud-destruct-title">SELF-DESTRUCT</p><p class="hud-destruct-timer" id="hud-destruct-n">' + n + '</p><span class="hud-caption">autodestruct sequence</span></div>';
                },
            },
            {
                id: 'worm',
                html: () => '<div class="hud-event hud-event--worm"><pre class="hud-worm">@###==&gt;······················</pre><span class="hud-caption">packet worm</span></div>',
            },
            {
                id: 'raid',
                html: () => '<div class="hud-event hud-event--raid"><p class="hud-raid">[RAID-0] stripe sync… ' + (Math.random() > 0.5 ? 'OK' : 'REBUILD') + '</p><span class="hud-caption">storage array</span></div>',
            },
        ];
        const showNext = () => {
            const wait = Math.max(0, hudPausedUntil - Date.now());
            if (wait > 0 || stage.classList.contains('hud-tool-flash')) {
                hudTickerTimer = setTimeout(showNext, wait > 0 ? wait + 50 : 200);
                return;
            }
            const ev = events[Math.floor(Math.random() * events.length)];
            stage.classList.remove('hud-active');
            void stage.offsetWidth;
            stage.innerHTML = ev.html();
            stage.className = 'api-lab-hud__stage hud-active hud-' + ev.id;
            if (ev.id === 'destruct') {
                const box = stage.querySelector('.hud-event--destruct');
                const numEl = stage.querySelector('.hud-destruct-timer');
                let n = parseInt(box?.dataset.count || '8', 10);
                if (hudDestructInterval) clearInterval(hudDestructInterval);
                hudDestructInterval = setInterval(() => {
                    if (stage.classList.contains('hud-tool-flash') || Date.now() < hudPausedUntil) return;
                    n--;
                    if (numEl) numEl.textContent = n > 0 ? String(n) : 'BOOM';
                    if (n <= 0) {
                        clearInterval(hudDestructInterval);
                        hudDestructInterval = null;
                    }
                }, 700);
            }
            if (terminal && !terminal.classList.contains('hud-paused')) {
                terminal.classList.add('hud-live');
            }
            hudTickerTimer = setTimeout(showNext, 5000 + Math.floor(Math.random() * 7000));
        };
        hudShowNextFn = showNext;
        hudTickerTimer = setTimeout(showNext, 1200 + Math.floor(Math.random() * 2500));
    }
    initHudTicker();

    function headerBlock(title, headers) {
        return '<div class="api-lab-block__section"><h4>' + esc(title) + '</h4>' + prePlain(formatHeaders(headers)) + '</div>';
    }

    function renderInbound(inbound) {
        const el = document.getElementById('lab-inbound');
        el.innerHTML = '<h3 class="api-lab-block__title">inbound request</h3>' +
            '<p class="api-lab-block__sub">' + esc(inbound.method + ' ' + inbound.uri) + '</p>' +
            headerBlock('headers', inbound.headers) +
            '<div class="api-lab-block__section"><h4>body</h4>' + pre(inbound.body_pretty) + '</div>';
    }

    function renderStep(step) {
        const ok = step.phase === 'ok';
        const badge = '<span class="step-badge ' + (ok ? 'ok' : 'error') + '">' +
            esc(step.status || '…') + '</span>';
        let html = '<article class="api-lab-step api-lab-step--reveal ' + (ok ? 'is-ok' : 'is-err') + '" style="--step-i:' + step.index + '">' +
            '<header><span class="step-idx">#' + step.index + '</span> ' +
            '<strong>' + esc(step.label) + '</strong> ' + badge +
            '<span class="step-ms">' + (step.duration_ms != null ? step.duration_ms + ' ms' : '') + '</span></header>';

        if (step.request) {
            html += '<p class="step-url">' + esc((step.request.method || '') + ' ' + (step.request.url || '')) + '</p>';
            html += headerBlock('request headers', step.request.headers);
            if (step.request.body) {
                html += '<div class="api-lab-block__section"><h4>request body</h4>' + pre(step.request.body) + '</div>';
            }
        }

        if (step.response) {
            html += headerBlock('response headers', step.response.headers);
            html += '<div class="api-lab-block__section"><h4>response body</h4>' + pre(step.response.body_pretty) + '</div>';
        }

        return html + '</article>';
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('lab-send');
        btn.disabled = true;
        startTransmitUi();
        output.hidden = false;
        output.classList.remove('is-revealed');
        document.getElementById('lab-inbound').innerHTML = '';
        document.getElementById('lab-duration').textContent = '';
        document.getElementById('lab-rollback').textContent = '';
        document.getElementById('lab-steps').innerHTML =
            '<div class="api-lab-block api-lab-awaiting"><h3 class="api-lab-block__title">execution trace</h3>' +
            '<p class="api-lab-awaiting__text"><span class="tx-cursor">█</span> awaiting uplink response...</p></div>';
        const telemetryEl = document.getElementById('lab-telemetry');
        if (telemetryEl) {
            telemetryEl.hidden = true;
            telemetryEl.innerHTML = '';
        }
        scrollLabToBottom();

        const pathParams = {};
        document.querySelectorAll('.path-param').forEach((el) => {
            pathParams[el.dataset.param] = el.value;
        });

        let headers = {};
        try {
            const h = document.getElementById('lab-headers').value.trim();
            if (h) headers = JSON.parse(h);
        } catch (err) {
            alert('Headers JSON: ' + err.message);
            stopTransmitUi();
            btn.disabled = false;
            return;
        }

        let query = {};
        if (showQuery) {
            try {
                const q = document.getElementById('lab-query')?.value.trim() ?? '';
                if (q) query = JSON.parse(q);
            } catch (err) {
                alert('Query JSON: ' + err.message);
                stopTransmitUi();
                btn.disabled = false;
                return;
            }
        }

        let body = showBody ? (document.getElementById('lab-body')?.value ?? null) : null;

        try {
            const res = await fetch(root.dataset.executeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    method: document.getElementById('lab-method').value,
                    path_params: pathParams,
                    query,
                    body,
                    headers,
                    acting_as_user_id: document.getElementById('lab-acting-as')?.value || null,
                }),
            });
            const data = await res.json();
            if (!res.ok && !(data.steps && data.steps.length)) {
                throw new Error(data.message || ('HTTP ' + res.status));
            }
            stopTransmitUi();
            output.hidden = false;
            output.classList.add('is-revealed');
            scrollLabToBottom();
            document.getElementById('lab-duration').textContent =
                (data.duration_ms != null ? data.duration_ms + ' ms total' : '');
            document.getElementById('lab-rollback').textContent =
                data.rollback_applied ? '↺ db rollback' : '';

            if (data.inbound) {
                const inboundEl = document.getElementById('lab-inbound');
                renderInbound(data.inbound);
                inboundEl.classList.add('api-lab-block--reveal');
            }
            const stepsEl = document.getElementById('lab-steps');
            stepsEl.innerHTML = '<h3 class="api-lab-block__title">execution trace</h3>' +
                (data.steps || []).map(renderStep).join('');
            renderTelemetry(data.metrics, data.quality);
            lastResponseJson = extractLastResponseJson(data.steps);

            const downEl = document.getElementById('lab-downlink');
            if (downEl && data.metrics && data.duration_ms > 0 && data.metrics.response_bytes) {
                const txMbps = (data.metrics.response_bytes * 8) / (data.duration_ms / 1000) / 1e6;
                downEl.textContent = formatThroughput(txMbps) + ' last tx';
                downEl.classList.add('is-tx');
            }

            requestAnimationFrame(() => {
                stepsEl.querySelectorAll('.api-lab-step--reveal').forEach((el, idx) => {
                    el.style.animationDelay = (idx * 0.12) + 's';
                });
                scrollLabToBottom();
            });
        } catch (err) {
            stopTransmitUi();
            output.hidden = false;
            const stepsEl = document.getElementById('lab-steps');
            stepsEl.innerHTML = '<h3 class="api-lab-block__title">execution trace</h3>' +
                '<article class="api-lab-step is-err"><header><strong>transmit error</strong></header>' +
                '<div class="api-lab-block__section"><h4>detail</h4>' + pre(err.message) + '</div></article>';
            scrollLabToBottom();
        } finally {
            btn.disabled = false;
        }
    });
})();
</script>
</body>
</html>
