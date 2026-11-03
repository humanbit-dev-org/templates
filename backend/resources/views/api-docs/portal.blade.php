@if ($viewerLogged)
<div class="api-sandbox-banner">
    @if ($lastToken)
        <div class="api-sandbox-banner-token">
            <strong>Access token — {{ $lastToken['banca'] }}:</strong>
            <code>{{ $lastToken['hint'] }}</code>
            <span class="api-sandbox-banner-user">
                <code>{{ $lastToken['user_id'] }}</code>
                · emesso {{ $lastToken['created_at']?->format('d/m/Y H:i') }}
                · scade {{ $lastToken['expires_at']?->format('d/m/Y H:i') ?? '—' }}
                · ultimo uso {{ $lastToken['last_used_at']?->diffForHumans() ?? 'mai' }}
            </span>
        </div>
    @else
        <div class="api-sandbox-banner-impersonate">
            <span>Nessun access token attivo — si emette scambiando il refresh token su <code>POST /api/v1/token</code>.</span>
        </div>
    @endif
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
    @if ($isStaff)
    <section class="api-sandbox-card">
        <h2 class="api-sandbox-subtitle">Refresh token emesse</h2>
        @if ($refreshTokens->isEmpty())
            <p>
                Nessuna Refresh token emessa finora. Le emette un operatore con
                <code>php artisan portal:refresh-token {user_id}</code>.
            </p>
        @else
            <p>
                Una Refresh token per banca, collegata al Product Owner.
                Il prefisso visibile identifica la credenziale; il segreto
                completo viene mostrato una sola volta all'emissione.
            </p>
            <div class="api-sandbox-table-wrap">
                <table class="api-sandbox-table">
                    <thead>
                        <tr>
                            <th>Banca</th>
                            <th>Product Owner</th>
                            <th>Refresh token</th>
                            <th>Emessa</th>
                            <th>Ultimo uso</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($refreshTokens as $row)
                            <tr>
                                <td>{{ $row['banca'] }}</td>
                                <td><code>{{ $row['user_id'] }}</code></td>
                                <td><code>{{ $row['hint'] }}</code></td>
                                <td>{{ $row['created_at']?->format('d/m/Y H:i') }}</td>
                                <td>{{ $row['last_used_at']?->format('d/m/Y H:i') ?? 'mai' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="api-sandbox-card">
        <h2 class="api-sandbox-subtitle">Access token attivi</h2>
        @if ($tokenLog->isEmpty())
            <p>Nessun access token attivo al momento.</p>
        @else
            <p>
                Token attivi per banca — solo l'impronta (ultimi caratteri
                dell'hash) è visibile, il segreto non è mai recuperabile.
            </p>
            <div class="api-sandbox-table-wrap api-docs-token-log">
                <table class="api-sandbox-table">
                    <thead>
                        <tr>
                            <th>Banca</th>
                            <th>Product Owner</th>
                            <th>Access token</th>
                            <th>Emesso</th>
                            <th>Scade</th>
                            <th>Ultimo uso</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tokenLog as $row)
                            <tr>
                                <td>{{ $row['banca'] }}</td>
                                <td><code>{{ $row['user_id'] }}</code></td>
                                <td><code>{{ $row['hint'] }}</code></td>
                                <td>{{ $row['created_at']?->format('d/m/Y H:i') }}</td>
                                <td>{{ $row['expires_at']?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td>{{ $row['last_used_at']?->format('d/m/Y H:i') ?? 'mai' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </section>
    @endif

    <h1 class="api-sandbox-title">
        Documentazione API
        <span class="api-sandbox-badge">Portal</span>
    </h1>
    <p class="api-sandbox-lead">Documentazione tecnica delle API per gli sviluppatori.</p>
    <p class="api-sandbox-lead api-sandbox-lead-sandbox">
        <strong>Ambiente di produzione.</strong> Le chiamate girano su
        <code>/api/v1/*</code> con dati reali: ogni scrittura è definitiva.
        Per provare le stesse API su dati finti con rollback usa la scheda
        <a href="{{ url('/api-docs?tab=sandbox') }}">Sandbox</a>.
    </p>


    <section class="api-sandbox-card">
        <h2 class="api-sandbox-subtitle">Autenticazione</h2>
        <p>
            Il portale usa due credenziali:
        </p>
        <ul>
            <li>
                <strong>Refresh token</strong> — credenziale a lungo termine della
                banca, emessa internamente e consegnata fuori banda. Serve
                solo per scambiare un <em>access token</em>: non chiama le API
                direttamente.
            </li>
            <li>
                <strong>Access token</strong> — token operativo (valido ~{{ $apiTokenHours }} ore)
                che si ottiene scambiando il refresh token su
                <code>POST /api/v1/token</code>. Va inviato come Bearer su
                tutte le chiamate <code>/api/v1/*</code>.
            </li>
        </ul>
        <pre class="api-sandbox-code">POST /api/v1/token
Authorization: Bearer {refresh-token}</pre>
        <pre class="api-sandbox-code">Authorization: Bearer {access-token}</pre>
        <p>
            Ogni scambio revoca l'access token precedente: resta attivo un solo
            token per banca, quindi i sistemi che condividono lo stesso refresh
            token devono condividere anche il token ottenuto.
        </p>
        <p>
            Le API sono accessibili solo con l'access token della propria banca:
            le richieste su clienti o pratiche di altre banche rispondono con
            <code>403</code>. I parametri contrassegnati con
            <span class="api-sandbox-required">*</span> sono obbligatori.
            Le date sono in formato <code>YYYY-MM-DD</code>.
        </p>
    </section>

    @foreach ($endpoints as $endpoint)
        @include('api-docs.partials.endpoint', ['endpoint' => $endpoint])
    @endforeach
</main>
</div>

<script>
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
</script>

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
