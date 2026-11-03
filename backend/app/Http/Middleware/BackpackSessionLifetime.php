<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Override session lifetime for Backpack admin routes so admin sessions
 * last longer (e.g. 24h) while Platform (SAML/apiToken) keeps short lifetime.
 * Must run before StartSession so the overridden value is used when session is stored.
 */
class BackpackSessionLifetime
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $prefix = config('backpack.base.route_prefix', 'admin');
        $prefix = trim($prefix, '/');
        if ($prefix !== '' && ($request->is($prefix) || $request->is($prefix.'/*'))) {
            Config::set('session.lifetime', config('session.lifetime_backpack', 1440));
        }

        return $next($request);
    }
}
