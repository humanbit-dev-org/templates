@if ($sandboxEnabled ?? false)
    <div class="api-sandbox-banner">
        <div class="api-sandbox-banner-impersonate" id="token-request">
            <strong><span id="token-label">Token sandbox (valido 9 ore):</span></strong>
            <button type="button" id="token-btn">Genera token</button>
        </div>
        <div class="api-sandbox-banner-token" id="token-section" style="display: none;">
            <strong>Token:</strong>
            <code id="api-token"></code>
            <button type="button" id="copy-btn">Copia</button>
            <span class="api-sandbox-banner-user">
                scade <strong id="token-expiry"></strong>
                <button type="button" id="refresh-token-btn" title="Genera un nuovo token">↻</button>
            </span>
        </div>
    </div>
@endif

<select id="api-sandbox-nav-mobile" class="api-sandbox-nav-mobile" aria-label="Vai all'endpoint">
    <option value="">Vai a…</option>
    @foreach ($endpoints as $endpoint)
        <option value="{{ $endpoint['id'] }}"
            @if (!empty($endpoint['operations']) && collect($endpoint['operations'])->every(fn ($op) => $op['conguaglio_only'] ?? false)) data-conguaglio-option @endif>
            {{ $endpoint['method'] }} — {{ $endpoint['title'] }}
        </option>
    @endforeach
</select>

<div class="api-sandbox-layout">
<nav class="api-sandbox-nav" aria-label="Endpoint">
    <p class="api-sandbox-nav-title">Endpoint</p>
    @foreach ($endpoints as $endpoint)
        <a href="#{{ $endpoint['id'] }}" class="api-sandbox-nav-link" data-nav="{{ $endpoint['id'] }}"
            @if (!empty($endpoint['operations']) && collect($endpoint['operations'])->every(fn ($op) => $op['conguaglio_only'] ?? false)) data-conguaglio-only @endif>
            <span class="api-sandbox-method method-{{ strtolower($endpoint['method']) }}">{{ $endpoint['method'] }}</span>
            {{ $endpoint['title'] }}
        </a>
    @endforeach
</nav>

<a href="#" class="api-sandbox-nav-link api-sandbox-nav-top">↑<span class="api-sandbox-nav-top-label"> Torna su</span></a>

<main class="api-sandbox-main">
    <h1 class="api-sandbox-title">
        Documentazione API
        @if ($sandboxEnabled ?? false)
            <span class="api-sandbox-badge">Sandbox</span>
        @endif
    </h1>
    <p class="api-sandbox-lead">Documentazione tecnica delle API per gli sviluppatori.</p>
    @if ($sandboxEnabled ?? false)
        <p class="api-sandbox-lead api-sandbox-lead-sandbox">
            Ambiente di test: le chiamate eseguite da questa pagina girano su
            <code>/sandbox/*</code> con dati finti e ogni scrittura viene
            annullata. La controparte di produzione — stesse API su
            <code>/api/v1/*</code>, con dati reali e scritture definitive —
            è la scheda <a href="{{ url('/api-docs?tab=portal') }}">Portal</a>.
        </p>

        <div class="api-sandbox-tabs" role="tablist" aria-label="Configurazione banca">
            @foreach ($banks as $abi => $bank)
                <button type="button" role="tab"
                    class="api-sandbox-tab {{ (string) $abi === ($defaultBank ?? null) ? 'active' : '' }}"
                    data-bank="{{ $abi }}" aria-selected="{{ (string) $abi === ($defaultBank ?? null) ? 'true' : 'false' }}">
                    {{ $bank['label'] }}
                    <small>ABI {{ $abi }}</small>
                </button>
            @endforeach
            <label class="api-sandbox-conguaglio" id="conguaglio-wrap" hidden>
                <input type="checkbox" id="conguaglio-toggle" checked>
                Conguaglio
            </label>
        </div>
    @endif

    <section class="api-sandbox-card">
        <h2 class="api-sandbox-subtitle">Autenticazione</h2>
        <p>
            Tutte le chiamate richiedono un token di autenticazione, inviato
            nell'header <code>Authorization</code> come Bearer token:
        </p>
        <pre class="api-sandbox-code">Authorization: Bearer {token}</pre>
        <p>
            Le API sono accessibili solo agli operatori con ruolo
            <strong>Filiale</strong> o <strong>Product Owner</strong>, e ciascun
            operatore può agire esclusivamente sui dati della propria banca: le
            richieste su clienti o pratiche di altre banche rispondono con
            <code>403</code>.
        </p>
        <p>
            Ogni scheda del sandbox ha il proprio token: il token di una banca
            demo non può leggere i dati dell'altra.
        </p>
        <p>
            I parametri contrassegnati con <span class="api-sandbox-required">*</span>
            sono obbligatori. Le date sono in formato <code>YYYY-MM-DD</code>.
        </p>
    </section>

    <p class="api-sandbox-lead">
        Banche demo: i clienti seguono lo schema <code>ABI&lt;banca&gt;_PC…</code>
        e <code>ABI&lt;banca&gt;_MT…</code>; gli ID <code>ABI&lt;banca&gt;_ERR…</code>
        esistono per provare le risposte di errore.
    </p>

    @foreach ($endpoints as $endpoint)
        @include('api-docs.partials.endpoint', ['endpoint' => $endpoint])
    @endforeach
</main>
</div>

@if ($sandboxEnabled ?? false)
<script>
    const TOKEN_URL = @json(route('sandbox.token'));
    const SAMPLE_URL = @json(route('sandbox.sample'));
    const BANKS = @json($banks);
    const DP_ABIS = Object.keys(BANKS).filter((abi) => BANKS[abi].durata_parametrica);

    // Selected bank persists per tab — like the tokens, it lives in
    // sessionStorage so a refresh restores it but each tab stays independent.
    let currentBank = sessionStorage.getItem('api_sandbox_bank');
    if (!BANKS[currentBank]) {
        currentBank = @json($defaultBank);
    }
    // The conguaglio toggle persists too — stored per tab like the bank.
    let conguaglioOn = sessionStorage.getItem('api_sandbox_conguaglio') !== '0';
    document.getElementById('conguaglio-toggle').checked = conguaglioOn;

    // One token slot per bank per tab — try-it calls and the banner share it.
    let apiToken = null;
    let apiTokenExpiry = null;

    function loadToken() {
        apiToken = sessionStorage.getItem(`api_sandbox_token_${currentBank}`) || null;
        apiTokenExpiry = sessionStorage.getItem(`api_sandbox_token_expiry_${currentBank}`) || null;

        if (!tokenValid()) {
            apiToken = null;
            apiTokenExpiry = null;
        }
    }

    function tokenValid() {
        return apiToken && apiTokenExpiry && new Date(apiTokenExpiry).getTime() > Date.now();
    }

    function conguaglioActive() {
        return DP_ABIS.includes(currentBank) && conguaglioOn;
    }

    // ?sandbox_conguaglio=0 flips the DP product inside the rolled-back
    // transaction — the only way the same bank demos both product states.
    function toggleParam() {
        return DP_ABIS.includes(currentBank) && !conguaglioOn ? '?sandbox_conguaglio=0' : '';
    }

    function showTokenSection() {
        document.getElementById('api-token').textContent = apiToken;
        document.getElementById('token-expiry').textContent = new Date(apiTokenExpiry).toLocaleString('it-IT');
        document.getElementById('token-section').style.display = 'flex';
        document.getElementById('token-request').style.display = 'none';
    }

    function showTokenRequest() {
        document.getElementById('token-request').style.display = 'flex';
        document.getElementById('token-section').style.display = 'none';
    }

    function applyBankVisibility() {
        const dp = DP_ABIS.includes(currentBank);
        document.getElementById('conguaglio-wrap').hidden = !dp;

        document.querySelectorAll('[data-conguaglio-only]').forEach((el) => {
            el.hidden = !conguaglioActive();
        });

        // <option> can't be hidden reliably (iOS Safari) — disable instead.
        document.querySelectorAll('#api-sandbox-nav-mobile option[data-conguaglio-option]').forEach((opt) => {
            opt.disabled = !conguaglioActive();
        });

        document.getElementById('token-label').textContent =
            `Token sandbox — ${BANKS[currentBank].label} (valido 9 ore):`;

        loadToken();
        tokenValid() ? showTokenSection() : showTokenRequest();
    }

    // One shared issuer behind both the banner button and the first Try-it:
    // reuses the bank's session token while it is still valid, mints a new
    // one otherwise (or when forced). Mints are independent — a new token
    // never revokes the previous ones.
    async function getSandboxToken(force = false) {
        if (!force && tokenValid()) {
            return apiToken;
        }

        const res = await fetch(TOKEN_URL, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ bank: currentBank }),
        });

        if (!res.ok) {
            throw new Error(`Token non emesso (HTTP ${res.status})`);
        }

        const data = await res.json();
        apiToken = data.token;
        apiTokenExpiry = data.expires_at;
        sessionStorage.setItem(`api_sandbox_token_${currentBank}`, apiToken);
        sessionStorage.setItem(`api_sandbox_token_expiry_${currentBank}`, apiTokenExpiry);
        showTokenSection();

        return apiToken;
    }

    function selectBank(abi) {
        currentBank = abi;
        sessionStorage.setItem('api_sandbox_bank', abi);
        document.querySelectorAll('.api-sandbox-tab[data-bank]').forEach((t) => {
            const selected = t.dataset.bank === abi;
            t.classList.toggle('active', selected);
            t.setAttribute('aria-selected', String(selected));
        });
        applyBankVisibility();
    }

    document.querySelectorAll('.api-sandbox-tab[data-bank]').forEach((tab) => {
        tab.addEventListener('click', () => selectBank(tab.dataset.bank));
    });

    document.getElementById('conguaglio-toggle').addEventListener('change', (e) => {
        conguaglioOn = e.target.checked;
        sessionStorage.setItem('api_sandbox_conguaglio', conguaglioOn ? '1' : '0');
        applyBankVisibility();
    });

    document.getElementById('token-btn').addEventListener('click', async () => {
        const btn = document.getElementById('token-btn');
        btn.disabled = true;
        try {
            await getSandboxToken();
        } catch (e) {
            alert(e.message);
        } finally {
            btn.disabled = false;
        }
    });

    document.getElementById('refresh-token-btn').addEventListener('click', async () => {
        const btn = document.getElementById('refresh-token-btn');
        btn.disabled = true;
        try {
            await getSandboxToken(true);
        } catch (e) {
            alert(e.message);
        } finally {
            btn.disabled = false;
        }
    });

    document.getElementById('copy-btn').addEventListener('click', async () => {
        const btn = document.getElementById('copy-btn');
        const token = document.getElementById('api-token').textContent;

        try {
            if (navigator.clipboard) {
                await navigator.clipboard.writeText(token);
            } else {
                // clipboard API requires a secure context; fall back for plain-HTTP origins
                const textarea = document.createElement('textarea');
                textarea.value = token;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                textarea.remove();
            }
            btn.textContent = 'Copiato!';
            setTimeout(() => { btn.textContent = 'Copia'; }, 2000);
        } catch (e) {
            btn.textContent = 'Errore';
            setTimeout(() => { btn.textContent = 'Copia'; }, 2000);
        }
    });

    // Response payloads only ever land via textContent — never innerHTML.
    function renderResult(resultBox, status, rawBody, requestLine) {
        const statusClass = `status-${String(status)[0]}xx`;
        resultBox.innerHTML =
            (requestLine ? `<p class="api-sandbox-tryit-call"><code></code></p>` : '') +
            `<p><span class="api-sandbox-status ${statusClass}">${status}</span> Risposta</p>` +
            `<pre class="api-sandbox-code"></pre>`;
        if (requestLine) {
            resultBox.querySelector('.api-sandbox-tryit-call code').textContent = requestLine;
        }
        resultBox.querySelector('pre').textContent = rawBody;
    }

    async function authedFetch(url, options) {
        let res = await fetch(url, options);

        // Expired or rejected token: mint a fresh one and retry once.
        if (res.status === 401) {
            await getSandboxToken(true);
            options.headers['Authorization'] = `Bearer ${apiToken}`;
            res = await fetch(url, options);
        }

        return res;
    }

    document.querySelectorAll('[data-sample-op]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const panel = btn.closest('[data-tryit]');
            const resultBox = panel.querySelector('[data-tryit-result]');

            resultBox.hidden = false;
            resultBox.innerHTML = '<p class="api-sandbox-tryit-loading">Chiamata in corso...</p>';
            btn.disabled = true;

            try {
                await getSandboxToken();

                // The resolver picks a random valid row in the active bank
                // and returns the real /sandbox/* request to fire.
                const sample = await authedFetch(SAMPLE_URL + toggleParam(), {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${apiToken}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ operation: btn.dataset.sampleOp, bank: currentBank }),
                });

                const call = await sample.json();

                if (!sample.ok) {
                    renderResult(resultBox, sample.status, JSON.stringify(call, null, 2));
                    return;
                }

                const path = call.path + toggleParam();
                const res = await authedFetch(path, {
                    method: call.method,
                    headers: {
                        'Authorization': `Bearer ${apiToken}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: call.body ? JSON.stringify(call.body) : undefined,
                });

                const text = await res.text();
                let pretty;
                try {
                    pretty = JSON.stringify(JSON.parse(text), null, 2);
                } catch {
                    pretty = text || '(nessun contenuto)';
                }

                renderResult(resultBox, res.status, pretty, `${call.method} ${path}`);
            } catch (error) {
                resultBox.innerHTML = `<p class="api-sandbox-tryit-error">${error.message}</p>`;
            } finally {
                btn.disabled = false;
            }
        });
    });

    // Sidebar state: the link of the topmost section in view stays highlighted.
    const navLinks = document.querySelectorAll('.api-sandbox-nav-link');
    const cards = document.querySelectorAll('.api-sandbox-endpoint');

    const spy = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }
            navLinks.forEach((link) => {
                link.classList.toggle('active', link.dataset.nav === entry.target.id);
            });
        });
    }, { rootMargin: '-15% 0px -75% 0px' });

    cards.forEach((card) => spy.observe(card));

    selectBank(currentBank);
</script>
@endif

<script>
    // "Torna su" stays hidden until the first endpoint section reaches the top.
    const navTop = document.querySelector('.api-sandbox-nav-top');
    const firstCard = document.querySelector('.api-sandbox-endpoint');
    if (navTop && firstCard) {
        const toggleNavTop = () => {
            // Desktop: appear once the first endpoint reaches the top.
            // Mobile: same as the original floater — a fixed scroll depth.
            const show = window.matchMedia('(max-width: 900px)').matches
                ? window.scrollY > 400
                : firstCard.getBoundingClientRect().top <= 96;
            navTop.classList.toggle('visible', show);
        };
        window.addEventListener('scroll', toggleNavTop, { passive: true });
        toggleNavTop();
    }

    // Mobile endpoint picker — same anchors as the desktop sidebar.
    const mobileNav = document.getElementById('api-sandbox-nav-mobile');
    if (mobileNav) {
        mobileNav.addEventListener('change', () => {
            document.getElementById(mobileNav.value)?.scrollIntoView();
            mobileNav.value = '';
        });
    }
</script>
