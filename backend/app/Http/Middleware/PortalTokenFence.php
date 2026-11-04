<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contains portal credentials to their own surface, both ways:
 *
 *  - 'portal-access' tokens may call /api/v1/* only;
 *  - 'portal-refresh:*' tokens may call /api/v1/token only.
 *
 * A leaked client credential can therefore never leave the portal surface and
 * wander onto other token-authenticated routes. Internal tokens (['*'],
 * console, SSO) and sandbox tokens are untouched: their names never match
 * the portal prefixes.
 *
 * Resolves the sanctum guard explicitly: appended to the 'api' group, this
 * can run before auth:sanctum in the middleware pipeline — the guard still
 * resolves the bearer token on demand, so the check works from either
 * position.
 */
class PortalTokenFence
{
    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->user('sanctum')?->currentAccessToken()?->name;

        if (is_string($name) && $name !== '') {
            $allowed = match (true) {
                $name === config('portal.api_token_name') => $request->is('api/v1/*'),
                Str::startsWith($name, config('portal.refresh_token_prefix').':') => $request->is('api/v1/token'),
                default => true,
            };

            abort_unless($allowed, 403);
        }

        return $next($request);
    }
}
