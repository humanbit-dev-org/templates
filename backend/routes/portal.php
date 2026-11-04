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
| Public, versioned copies of the Business Console endpoints under
| /api/v1/*, exposed to banks for real production calls. Routes are cloned
| by name from the internal ones so behavior can never diverge — same
| controllers, same side effects, same idempotency guards. No rollback
| middleware: writes here are meant to commit.
|
| Access chain on every endpoint: sanctum bearer auth → Product Owner scope
| → the 'api:write' ability on the token row → Authorization header strip.
| The 'portal-refresh:*' credentials never reach these routes: they lack
| the api:write ability and the PortalTokenFence pins them to /api/v1/token.
|
| Loaded from bootstrap/app.php only when config('portal.enabled') is true.
|
*/

Route::getRoutes()->refreshNameLookups();

// $routes = [
//     'business.console.tiraggi.perfeziona',
//     'business.console.tiraggi.rifiuta',
//     'business.console.tiraggi.abbandona',
//     'business.console.tiraggi.estinzione',
//     'business.console.tiraggi.estinzione_parziale',
//     'dashboard.users.detail',
//     'business.console.eleggibili.update',
// ];

// foreach ($routes as $name) {
//     $real = Route::getRoutes()->getByName($name)
//         ?? throw new RuntimeException("Portal: route [$name] not found");

//     // api/business-console/... → api/v1/business-console/...
//     $route = Route::match($real->methods(), 'api/v1/'.Str::after($real->uri(), 'api/'), $real->getAction('uses'))
//         ->name('portal.'.$name)
//         ->middleware([
//             'api',
//             'auth:sanctum',
//             'role.scope:product owner',
//             'abilities:api:write',
//             StripAuthorizationHeader::class,
//             ThrottleRequestsWithRedis::using('portal-calls'),
//         ])
//         // The header-based docs rollback must stay off: a caller could
//         // otherwise opt its own production writes into rollback.
//         ->withoutMiddleware(RollbackDocs::class);

//     foreach ($real->defaults as $key => $value) {
//         $route->defaults($key, $value); // strict_prodotto=false on perfeziona
//     }
// }

// Token exchange: the bank presents its refresh token as a Bearer
// credential; the controller verifies it is a 'portal-refresh:*' PAT on a
// Product Owner and returns a fresh 'portal-access' token.
Route::post('api/v1/token', PortalTokenController::class)
    ->middleware([
        'api',
        'auth:sanctum',
        StripAuthorizationHeader::class,
        ThrottleRequestsWithRedis::using('portal-token'),
    ])
    ->withoutMiddleware(RollbackDocs::class)
    ->name('portal.token');
