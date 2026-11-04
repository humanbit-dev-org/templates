<?php

use App\Http\Controllers\Restricted\SandboxSampleController;
use App\Http\Controllers\Restricted\SandboxTokenController;
use App\Http\Middleware\EnterSandbox;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use App\Http\Middleware\RollbackDocs;
use App\Http\Middleware\RollbackSandbox;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Sandbox API routes
|--------------------------------------------------------------------------
|
| Copies of real API endpoints under /sandbox/, running against a separate
| database and always rolled back. Routes are cloned by name from the real
| ones (config('sandbox.routes')) so the two can never diverge.
|
| Loaded from bootstrap/app.php only when config('sandbox.enabled') is true.
|
*/

Route::getRoutes()->refreshNameLookups();

// Optional role gate: RoleScope refuses everyone but Admin/Developer when it
// gets no role at all, so it is only attached when roles are configured.
$roleScope = config('sandbox.roles', []) === []
    ? []
    : ['role.scope:'.implode(',', config('sandbox.roles'))];

foreach (config('sandbox.routes', []) as $name) {
    $real = Route::getRoutes()->getByName($name)
        ?? throw new RuntimeException("Sandbox: route [$name] not found");

    // api/articles/... → sandbox/articles/...
    $route = Route::match($real->methods(), 'sandbox/'.Str::after($real->uri(), 'api/'), $real->getAction('uses'))
        ->name('sandbox.'.$name)
        // EncryptCookies + StartSession: the api group is stateless, but
        // EnterSandbox needs the Backpack session to apply its IP-or-login
        // gate for try-it calls coming from the docs page.
        ->middleware([
            'api',
            EncryptCookies::class,
            StartSession::class,
            EnterSandbox::class,
            'auth:sanctum',
            ...$roleScope,
            ThrottleRequestsWithRedis::using('sandbox-calls'),
            RollbackSandbox::class,
        ])
        ->withoutMiddleware(RollbackDocs::class);

    // Route defaults (e.g. a flag a controller reads) travel with the clone.
    foreach ($real->defaults as $key => $value) {
        $route->defaults($key, $value);
    }
}

// No RollbackSandbox: the token must persist.
Route::post('sandbox/token', SandboxTokenController::class)
    ->middleware([
        'api',
        EncryptCookies::class,
        StartSession::class,
        EnterSandbox::class,
        ThrottleRequestsWithRedis::using('sandbox-token'),
    ])
    ->withoutMiddleware(RollbackDocs::class)
    ->name('sandbox.token');

// Demo-call resolver for the API docs buttons: read-only, but the
// rollback middleware keeps the no-write guarantee uniform.
Route::post('sandbox/sample', SandboxSampleController::class)
    ->middleware([
        'api',
        EncryptCookies::class,
        StartSession::class,
        EnterSandbox::class,
        'auth:sanctum',
        ...$roleScope,
        ThrottleRequestsWithRedis::using('sandbox-calls'),
        RollbackSandbox::class,
    ])
    ->withoutMiddleware(RollbackDocs::class)
    ->name('sandbox.sample');
