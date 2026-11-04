<?php

use App\Http\Controllers\Restricted\ApiDocsController;
use App\Http\Controllers\Restricted\ApiLabController;
use App\Http\Controllers\Restricted\SamlController;
use App\Http\Controllers\Restricted\SwaggerController;
use App\Services\Restricted\RestrictedToolAccessService;
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
| A single page: pick the user (same checks and scenarios for every protocol)
| and the protocol. SAML builds the SAMLResponse and auto-posts it to the real
| endpoint.
*/
if ($restrictedTools->isEnabledInCurrentEnvironment('auth_sim')) {
    Route::middleware(['shield:auth_sim'])->prefix('auth-simulation')->group(function () {
        Route::get('/', [SamlController::class, 'form'])->name('auth.sim.form');
        Route::post('/post', [SamlController::class, 'autoPost'])->name('auth.sim.post');

        // Twin of the real SAML endpoint (POST /api/auth/saml → same authSaml()):
        // behind the operator shield it SKIPS the signature check, which the
        // real, public endpoint requires.
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
| API Docs — unified Sandbox | Portal documentation
|--------------------------------------------------------------------------
| One page with a Sandbox tab (try-it against /sandbox/*, fake data and
| rollback) and a Portal tab (reference of /api/v1/*, real data — docs and
| a partial token list only: no calls from the page, they would really
| write). Each tab shows only where its surface exists (sandbox.enabled /
| portal.enabled + tool): production keeps the Portal tab alone.
| The old /api-sandbox and /api-portal URLs redirect to the matching tab.
*/
if ($restrictedTools->isEnabledInCurrentEnvironment('api_docs')) {
    Route::middleware(['shield:api_docs'])->group(function () {
        Route::get('/api-docs', [ApiDocsController::class, 'index'])->name('api-docs.index');
        Route::redirect('/api-sandbox', '/api-docs?tab=sandbox')->name('api-sandbox.index');
        Route::redirect('/api-portal', '/api-docs?tab=portal')->name('api-portal.index');
    });
}

