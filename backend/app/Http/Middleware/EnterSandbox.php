<?php

namespace App\Http\Middleware;

use App\Services\Sandbox\SandboxContext;
use App\Services\Restricted\RestrictedToolAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Switches the whole request to the sandbox context.
 *
 * Must run before authentication (registered in the middleware priority list
 * ahead of AuthenticatesRequests) so Sanctum looks the token up in the sandbox
 * database — sandbox tokens do not exist in the real one, and vice versa.
 */
class EnterSandbox
{
    public function handle(Request $request, Closure $next): Response
    {
        // Network-level gate, same source of truth as the restricted tools:
        // when IP_BLOCK_ENABLE is on, only cors.allowed_ips callers may reach
        // the sandbox. Off (e.g. local) → no IP restriction at all.
        $access = app(RestrictedToolAccessService::class);

        if ($access->ipBlockEnabled() && ! $access->isIpAllowed($request->ip())) {
            // Same OR rule as the restricted pages: a Backpack session with
            // the tool's roles also passes — that is how the docs page's
            // try-it calls reach the sandbox from an admin browser.
            $user = backpack_auth()->user();
            $roles = config('restricted_tools.tools.api_sandbox.roles', []);

            abort_unless(
                $user && $access->userHasAllowedRole($user, $roles),
                403,
                'Access denied: IP not allowed'
            );
        }

        SandboxContext::enter('sandbox');

        // Fail closed: never run sandbox traffic on the real schema.
        abort_if(
            DB::connection()->getDatabaseName() === config('database.connections.mysql.database'),
            500
        );

        return $next($request);
    }
}
