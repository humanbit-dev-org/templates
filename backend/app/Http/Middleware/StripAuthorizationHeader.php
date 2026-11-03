<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Removes the Authorization header once Sanctum has already authenticated
 * the request. Defense in depth: nothing downstream — logs, dumps, error
 * reporters, request recorders — can ever see or persist the bearer token,
 * because the header no longer exists on the request.
 *
 * Must run AFTER auth:sanctum in the route middleware list: the sanctum
 * guard resolves the token lazily on first $request->user() and caches the
 * result, so removing the header afterwards cannot break authentication.
 */
class StripAuthorizationHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->remove('Authorization');

        return $next($request);
    }
}
