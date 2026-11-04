<?php

use App\Http\Controllers\Restricted\PortalTokenController;
use App\Http\Middleware\RollbackDocs;
use App\Http\Middleware\StripAuthorizationHeader;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| API Portal routes
|--------------------------------------------------------------------------
|
| Public, versioned copies of internal API endpoints under /api/v1/*,
| exposed to API clients for real production calls. Routes are cloned by
| name (config('portal.routes')) from the internal ones so behavior can never
| diverge — same controllers, same side effects, same idempotency guards.
| No rollback middleware: writes here are meant to commit.
|
| Access chain on every endpoint: sanctum bearer auth → optional role scope
| (config('portal.roles')) → the 'api:write' ability on the token row →
| Authorization header strip. The 'portal-refresh:*' credentials never reach
| these routes: they lack the api:write ability and the PortalTokenFence pins
| them to /api/v1/token.
|
| Loaded from bootstrap/app.php only when config('portal.enabled') is true.
|
*/

Route::getRoutes()->refreshNameLookups();

// Optional role gate: RoleScope refuses everyone but Admin/Developer when it
// gets no role at all, so it is only attached when roles are configured.
$roleScope = config('portal.roles', []) === []
    ? []
    : ['role.scope:'.implode(',', config('portal.roles'))];

foreach (config('portal.routes', []) as $name) {
    $real = Route::getRoutes()->getByName($name)
        ?? throw new RuntimeException("Portal: route [$name] not found");

    // api/articles/... → api/v1/articles/...
    $route = Route::match($real->methods(), 'api/v1/'.Str::after($real->uri(), 'api/'), $real->getAction('uses'))
        ->name('portal.'.$name)
        ->middleware([
            'api',
            'auth:sanctum',
            ...$roleScope,
            'abilities:api:write',
            StripAuthorizationHeader::class,
            ThrottleRequestsWithRedis::using('portal-calls'),
        ])
        // The header-based docs rollback must stay off: a caller could
        // otherwise opt its own production writes into rollback.
        ->withoutMiddleware(RollbackDocs::class);

    // Route defaults (e.g. a flag a controller reads) travel with the clone.
    foreach ($real->defaults as $key => $value) {
        $route->defaults($key, $value);
    }
}

// Token exchange: the client presents its refresh token as a Bearer
// credential; the controller verifies it is a 'portal-refresh:*' PAT and
// returns a fresh 'portal-access' token.
Route::post('api/v1/token', PortalTokenController::class)
    ->middleware([
        'api',
        'auth:sanctum',
        StripAuthorizationHeader::class,
        ThrottleRequestsWithRedis::using('portal-token'),
    ])
    ->withoutMiddleware(RollbackDocs::class)
    ->name('portal.token');
