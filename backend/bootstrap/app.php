<?php

use App\Http\Middleware\BackpackSessionLifetime;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnterSandbox;
use App\Http\Middleware\FrontendModelPermission;
use App\Http\Middleware\PortalTokenFence;
use App\Http\Middleware\RestrictedAccessShield;
use App\Http\Middleware\RoleScope;
use App\Http\Middleware\RollbackDocs;
use App\Http\Middleware\SecurityShield;
use App\Http\Middleware\SetLocaleFromHeader;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware(['web', SecurityShield::class])
                ->group(base_path('routes/restricted.php'));

            // SSO routes: browser-facing, but protected by SecurityShield like
            // everything else (its checks are generic and must not be relaxed for
            // browser traffic).
            Route::middleware(['web', SecurityShield::class])
                ->group(base_path('routes/sso.php'));

            $integrations = Route::middleware('b2b');

            if (app()->environment('integration')) {
                $integrations = $integrations->prefix('b2b');
            }

            $integrations->group(base_path('routes/integrations.php'));

            // Sandbox API copies: loaded only when the sandbox is enabled
            // (SANDBOX_ENABLED=true, never in production).
            if (config('sandbox.enabled')) {
                Route::group([], base_path('routes/sandbox.php'));
            }

            // API Portal: the production-facing /api/v1/* clones. Loaded
            // where portal.enabled holds — local/testing and production,
            // never staging/integration.
            if (config('portal.enabled')) {
                Route::group([], base_path('routes/portal.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust proxies defined via TRUSTED_PROXIES env variable (comma-separated CIDRs or IPs).
        // - Dev local   : 172.16.0.0/12  (Docker bridge range)
        // - Prod AWS    : 10.0.0.0/8     (ALB VPC private range)
        // - Prod Apache : 127.0.0.1      (host → container loopback)
        $middleware->trustProxies(
            at: array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', '')))),
            headers: Request::HEADER_X_FORWARDED_FOR |
                    Request::HEADER_X_FORWARDED_PROTO,
        );

        // Authenticated users hitting guest routes (e.g. /admin/login) → admin panel, not /
        $middleware->redirectTo(users: fn () => backpack_url());

        // Laravel's default guest redirect is route('login'), which does not exist
        // in this API-only backend: a browser navigation to an auth API URL would
        // die with RouteNotFoundException. API guests get no redirect (the handler
        // renders a JSON 401 via shouldRenderJsonWhen below); web guests go to the
        // Backpack login.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*', 'sandbox/*') ? null : route('backpack.auth.login')
        );

        // apiToken is a Bearer token the frontend reads from the cookie
        // (httpOnly: false) and sends back as the Authorization header:
        // encrypting it would make it unusable. SSO routes live in the "web"
        // group, where EncryptCookies runs, so the exception is needed there.
        $middleware->encryptCookies(except: ['apiToken']);

        $middleware->group('b2b', [
            SecurityShield::class,
            SubstituteBindings::class,
        ]);

        // EnterSandbox must run before auth so Sanctum resolves tokens against
        // the sandbox database, not the real one.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, EnterSandbox::class);

        $middleware->api(
            prepend: [
                // Uncomment this when Sanctum session-based authentication is used
                // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
                // First, so even SecurityShield's refusals speak the user's language.
                SetLocaleFromHeader::class,
                SecurityShield::class,
                RollbackDocs::class,
            ],
            append: [
                // One fence for the whole api group: portal credentials
                // ('api-portal', 'portal-password:*') stay inside /api/v1/*.
                PortalTokenFence::class,
            ],
        );

        $middleware->web(prepend: [
            BackpackSessionLifetime::class,
            SecurityShield::class,
        ]);

        $middleware->alias([
            // 'verified' is applied by Backpack itself, through
            // config/backpack/base.php 'setup_email_verification_middleware'.
            'verified' => EnsureEmailIsVerified::class,
            // The API routes name FrontendModelPermission through its own static
            // helpers (FrontendModelPermission::read('User')), so this alias is
            // only here for anyone who prefers the string form.
            'frontend.permission' => FrontendModelPermission::class,
            'shield' => RestrictedAccessShield::class,
            // Sanctum abilities gate for the portal's /api/v1/* clones.
            'abilities' => CheckAbilities::class,
            'role.scope' => RoleScope::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // B2B partner routes (routes/integrations.php) carry the "b2b" middleware group.
        $isB2bRoute = static fn (Request $request): bool => in_array('b2b', $request->route()?->gatherMiddleware() ?? [], true);

        // B2B partner routes are machine-to-machine: always JSON (no redirect()->back()).
        // api/* is included so a browser navigation to an API URL (no Accept: json)
        // gets a JSON 401 instead of a redirect to the non-existent 'login' route.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $isB2bRoute($request)
                || $request->is('api/*')
                || $request->is('sandbox/*')
                || $request->expectsJson()
        );

        // Hide raw SQL details from API consumers; return a generic 422 for
        // integrity constraint violations (SQLSTATE 23000) and a generic 500
        // for any other database error.
        $exceptions->render(function (QueryException $e, Request $request) use ($isB2bRoute) {
            if (! $isB2bRoute($request) && ! $request->expectsJson()) {
                return null; // let Laravel handle web responses normally
            }

            $sqlState = $e->getCode();

            if ($sqlState === '23000') {
                return response()->json([
                    'message' => __('api.duplicate_record'),
                ], 422);
            }

            return response()->json([
                'message' => __('api.database_error'),
            ], 500);
        });
    })->create();
