<?php

use App\Http\Controllers\Integrations\NexiOidcController;
use App\Http\Controllers\Restricted\ApiDocsController;
use App\Http\Controllers\Restricted\ApiLabController;
use App\Http\Controllers\Restricted\SamlController;
use App\Http\Controllers\Restricted\SwaggerController;
use App\Services\Restricted\RestrictedToolAccessService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Restricted Routes
|--------------------------------------------------------------------------
| Internal tools protected by RestrictedAccessShield.
| Tool availability per APP_ENV: config/restricted_tools.php
|
| Available shields:
| - shield:swagger → Swagger (Backpack auth + allowed IP)
| - shield:api_lab → API Lab bench
| - shield:auth_sim → Auth Simulation (SAML/OIDC)
| - shield:api_sandbox → API sandbox docs page (Backpack auth or allowed IP)
*/

$restrictedTools = app(RestrictedToolAccessService::class);

/*
|--------------------------------------------------------------------------
| Auth Simulation (SAML + OIDC)
|--------------------------------------------------------------------------
| Pagina unica: si sceglie l'utenza (stessi controlli/scenari per entrambi) e il
| protocollo. SAML costruisce la SAMLResponse e la auto-posta all'endpoint vero;
| OIDC mette l'utenza scelta in sessione e avvia il flusso reale (vedi sotto).
*/
if ($restrictedTools->isEnabledInCurrentEnvironment('auth_sim')) {
    Route::middleware(['shield:auth_sim'])->prefix('auth-simulation')->group(function () {
        Route::get('/', [SamlController::class, 'form'])->name('auth.sim.form');
        Route::post('/post', [SamlController::class, 'autoPost'])->name('auth.sim.post');

        // Route gemella dell'endpoint SAML reale (POST /api/auth/saml → stesso authSaml()):
        // dietro shield operatore, SALTA la verifica firma. La reale, pubblica, la pretende.
        Route::post('saml', [SamlController::class, 'authSaml'])->name('auth.sim.saml');
    });

    Route::middleware(['shield:auth_sim'])->group(function () {
        Route::get('/ip-debug', function () {
            return request()->ip();
        });
    });
}

/*
|--------------------------------------------------------------------------
| OIDC Simulation — Nexi "in casa"
|--------------------------------------------------------------------------
| Le route `request`/`confirm` qui sotto puntano agli STESSI metodi del flusso reale
| (routes/sso.php), non a una copia: la simulazione esercita il codice vero e l'unica
| differenza è che il Provider siamo noi. La discriminante è il nome della route
| (`oidc.sim.*`), raggiungibile solo passando dallo shield → fail-closed.
*/
if ($restrictedTools->isEnabledInCurrentEnvironment('auth_sim') && config('app.tenant') === 'nexi-multi') {
    Route::prefix('oidc-simulation')->group(function () {

        // Gambe browser: dietro lo stesso shield della pagina Auth Simulation.
        Route::middleware(['shield:auth_sim'])->group(function () {

            // Route gemelle → metodi reali.
            Route::get('request', [NexiOidcController::class, 'request'])->name('oidc.sim.request');
            Route::get('confirm/{authz_code?}/{state?}', [NexiOidcController::class, 'confirm'])
                ->name('oidc.sim.confirm');

            // Provider in casa: /authorize prende il posto della login page Nexi. L'utenza
            // e' gia' stata scelta sulla pagina Auth Simulation, quindi qui non si chiede
            // nulla: si emette il codice e si prosegue (come l'auto-post del SAML).
            Route::get('authorize', [NexiOidcController::class, 'simAuthorize'])->name('oidc.sim.authorize');
        });

        // Gambe backend: le chiama confirm() server-to-server, senza cookie operatore —
        // lo shield le bloccherebbe. Protette dal contratto vero: /token pretende un
        // authz_code emesso da noi (quindi da un operatore) + PKCE valido; /jwks espone
        // solo chiavi pubbliche.
        // Niente CSRF: è una chiamata macchina-macchina, come il /token vero di Nexi.
        Route::post('token', [NexiOidcController::class, 'simToken'])
            ->withoutMiddleware([PreventRequestForgery::class])
            ->name('oidc.sim.token');
        Route::get('jwks', [NexiOidcController::class, 'simJwks'])->name('oidc.sim.jwks');
    });
}

/*
|--------------------------------------------------------------------------
| API Lab
|--------------------------------------------------------------------------
*/
if ($restrictedTools->isEnabledInCurrentEnvironment('api_lab')) {
    Route::middleware(['shield:api_lab'])->prefix('api-lab')->group(function () {
        Route::get('/', [ApiLabController::class, 'index'])->name('api-lab.index');
        Route::get('/probe', [ApiLabController::class, 'probe'])->name('api-lab.probe');
        Route::post('/{key}/execute', [ApiLabController::class, 'execute'])->name('api-lab.execute');
    });
}

/*
|--------------------------------------------------------------------------
| Swagger API Routes
|--------------------------------------------------------------------------
*/
if ($restrictedTools->isEnabledInCurrentEnvironment('swagger')) {
    Route::middleware(['shield:swagger'])->prefix('swagger')->group(function () {
        Route::post('/impersonate', [SwaggerController::class, 'impersonate'])->name('swagger.impersonate');
        Route::post('/revoke', [SwaggerController::class, 'revoke'])->name('swagger.revoke');
    });
}

/*
|--------------------------------------------------------------------------
| API Docs — documentazione unificata Sandbox | Portal
|--------------------------------------------------------------------------
| Pagina unica con tab Sandbox (Try-it verso /sandbox/*, dati finti e
| rollback) e Portal (reference /api/v1/*, dati reali — solo documentazione
| ed elenco parziale dei token: nessuna esecuzione da pagina, qui le
| chiamate scriverebbero davvero). Ogni tab si mostra solo dove la sua
| superficie esiste (sandbox.enabled / portal.enabled + tool): in
| production resta la sola scheda Portal.
| Le vecchie URL /api-sandbox e /api-portal reindirizzano sulla scheda
| corrispondente per compatibilità.
*/
if ($restrictedTools->isEnabledInCurrentEnvironment('api_docs')) {
    Route::middleware(['shield:api_docs'])->group(function () {
        Route::get('/api-docs', [ApiDocsController::class, 'index'])->name('api-docs.index');
        Route::redirect('/api-sandbox', '/api-docs?tab=sandbox')->name('api-sandbox.index');
        Route::redirect('/api-portal', '/api-docs?tab=portal')->name('api-portal.index');
    });
}

