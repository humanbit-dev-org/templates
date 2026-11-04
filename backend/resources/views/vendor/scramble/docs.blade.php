<!doctype html>
<html lang="en" data-theme="{{ $config->get('ui.theme', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="color-scheme" content="{{ $config->get('ui.theme', 'light') }}">
    <title>{{ $config->get('ui.title', config('app.name') . ' - API Docs') }}</title>

    <script src="https://unpkg.com/@stoplight/elements@8.4.2/web-components.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@stoplight/elements@8.4.2/styles.min.css">
    <link rel="stylesheet" href="/static/css/swagger_docs.css">

    <script>
        const originalFetch = window.fetch;

        // Intercept TryIt requests to add:
        // - X-XSRF-TOKEN: required for Sanctum cookie-based auth
        // - X-Docs-Request: 1: signals RollbackDocs to wrap mutating requests in a transaction
        //   that is rolled back, so Swagger never persists real data changes
        const DOCS_REQUEST_HEADER = 'X-Docs-Request';
        const API_PATH = '/api/';
        window.fetch = (url, options) => {
            const CSRF_TOKEN_COOKIE_KEY = "XSRF-TOKEN";
            const CSRF_TOKEN_HEADER_KEY = "X-XSRF-TOKEN";
            const getCookieValue = (key) => {
                const cookie = document.cookie.split(';').find((cookie) => cookie.trim().startsWith(key));
                return cookie?.split("=")[1];
            };

            const updateFetchHeaders = (
                headers,
                headerKey,
                headerValue,
            ) => {
                if (headers instanceof Headers) {
                    headers.set(headerKey, headerValue);
                } else if (Array.isArray(headers)) {
                    headers.push([headerKey, headerValue]);
                } else if (headers) {
                    headers[headerKey] = headerValue;
                }
            };

            const isApiRequest = (requestUrl) => {
                try {
                    const urlObj = typeof requestUrl === 'string' ? new URL(requestUrl, window.location.origin) : requestUrl;
                    return urlObj.pathname.includes(API_PATH);
                } catch {
                    return false;
                }
            };

            const { headers = new Headers() } = options || {};
            let modified = false;

            const csrfToken = getCookieValue(CSRF_TOKEN_COOKIE_KEY);
            if (csrfToken) {
                updateFetchHeaders(headers, CSRF_TOKEN_HEADER_KEY, decodeURIComponent(csrfToken));
                modified = true;
            }

            if (isApiRequest(url)) {
                updateFetchHeaders(headers, DOCS_REQUEST_HEADER, '1');
                modified = true;
            }

            if (modified) {
                return originalFetch(url, { ...options, headers });
            }

            return originalFetch(url, options);
        };
    </script>
</head>
<body style="height: 100vh; overflow-y: hidden">

<div class="api-token-banner" id="token-banner">
    <div class="impersonate-section">
        <label for="impersonate-user-id">Username:</label>
        <input type="text" id="impersonate-user-id" placeholder="es. mrossi" />
        <button class="impersonate-btn" onclick="impersonateUser()" id="impersonate-btn">Genera token</button>
    </div>
    <div class="token-section" id="token-section" style="display: none;">
        <strong>Token:</strong>
        <code id="api-token" title="Click to select"></code>
        <button class="copy-btn" onclick="copyToken()" id="copy-btn">Copy</button>
        <span class="user-info">
            Testing as: <strong id="user-email"></strong>
            <button class="revoke-btn" onclick="revokeToken()" title="Revoke token">✕</button>
        </span>
    </div>
</div>
<script>
    const CSRF_TOKEN = @json(csrf_token());
    const IMPERSONATE_URL = @json(route('swagger.impersonate'));
    const REVOKE_URL = @json(route('swagger.revoke'));

    // Restore state from sessionStorage on load
    (function() {
        const token = sessionStorage.getItem('swagger_api_token');
        const email = sessionStorage.getItem('swagger_user_email');
        const userId = sessionStorage.getItem('swagger_user_id');

        if (token) {
            showTokenSection(token, email, userId);
        }

        document.body.classList.add('has-token-banner');
    })();

    async function impersonateUser() {
        const userId = document.getElementById('impersonate-user-id').value.trim();
        if (!userId) {
            alert('Inserisci lo username');
            return;
        }

        const btn = document.getElementById('impersonate-btn');
        btn.disabled = true;
        btn.textContent = 'Loading...';

        try {
            const response = await fetch(IMPERSONATE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ username: userId }),
            });

            const data = await response.json();

            if (data.success) {
                sessionStorage.setItem('swagger_api_token', data.token);
                sessionStorage.setItem('swagger_user_email', data.user.email);
                sessionStorage.setItem('swagger_user_id', data.user.username);
                showTokenSection(data.token, data.user.email, data.user.username);
            } else {
                alert(data.message || 'Errore durante impersonation');
            }
        } catch (error) {
            alert('Errore di rete: ' + error.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Genera token';
        }
    }

    function showTokenSection(token, email, userId) {
        document.getElementById('api-token').textContent = token;
        document.getElementById('user-email').textContent = `${email} (${userId})`;
        document.getElementById('token-section').style.display = 'flex';
        document.getElementById('impersonate-user-id').value = userId || '';
    }

    function copyToken() {
        const token = document.getElementById('api-token').textContent;
        navigator.clipboard.writeText(token).then(() => {
            const btn = document.getElementById('copy-btn');
            btn.textContent = 'Copied!';
            btn.classList.add('copied');
            setTimeout(() => {
                btn.textContent = 'Copy';
                btn.classList.remove('copied');
            }, 2000);
        });
    }

    async function revokeToken() {
        try {
            await fetch(REVOKE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json',
                },
            });
        } catch (e) {
            // Ignore errors on revoke
        }

        sessionStorage.removeItem('swagger_api_token');
        sessionStorage.removeItem('swagger_user_email');
        sessionStorage.removeItem('swagger_user_id');

        document.getElementById('token-section').style.display = 'none';
        document.getElementById('api-token').textContent = '';
        document.getElementById('user-email').textContent = '';
    }

    // Revoke token when leaving the page
    window.addEventListener('beforeunload', function() {
        if (sessionStorage.getItem('swagger_api_token')) {
            navigator.sendBeacon(REVOKE_URL, new URLSearchParams({ _token: CSRF_TOKEN }));
            sessionStorage.removeItem('swagger_api_token');
            sessionStorage.removeItem('swagger_user_email');
            sessionStorage.removeItem('swagger_user_id');
        }
    });
</script>
<elements-api
    id="docs"
    tryItCredentialsPolicy="{{ $config->get('ui.try_it_credentials_policy', 'include') }}"
    router="hash"
    @if($config->get('ui.hide_try_it')) hideTryIt="true" @endif
    @if($config->get('ui.hide_schemas')) hideSchemas="true" @endif
    @if($config->get('ui.logo')) logo="{{ $config->get('ui.logo') }}" @endif
    @if($config->get('ui.layout')) layout="{{ $config->get('ui.layout') }}" @endif
/>
<script>
    (async () => {
        const docs = document.getElementById('docs');
        docs.apiDescriptionDocument = @json($spec);
    })();
</script>

@if($config->get('ui.theme', 'light') === 'system')
    <script>
        var mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

        function updateTheme(e) {
            if (e.matches) {
                window.document.documentElement.setAttribute('data-theme', 'dark');
                window.document.getElementsByName('color-scheme')[0].setAttribute('content', 'dark');
            } else {
                window.document.documentElement.setAttribute('data-theme', 'light');
                window.document.getElementsByName('color-scheme')[0].setAttribute('content', 'light');
            }
        }

        mediaQuery.addEventListener('change', updateTheme);
        updateTheme(mediaQuery);
    </script>
@endif
</body>
</html>
