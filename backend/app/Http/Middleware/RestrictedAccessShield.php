<?php

namespace App\Http\Middleware;

use App\Services\Restricted\RestrictedToolAccessService;
use Closure;
use Illuminate\Http\Request;

/**
 * Unified middleware to protect sensitive backend routes.
 *
 * Supports multiple internal tools via parameter and policy configured in
 * config/restricted_tools.php.
 *
 * Examples:
 * - shield:swagger
 * - shield:pulse
 * - shield:horizon
 * - shield:auth_sim
 * - shield:api_lab
 * - shield:backpack
 */
class RestrictedAccessShield
{
    public function __construct(protected RestrictedToolAccessService $access) {}

    public function handle(Request $request, Closure $next, string $tool)
    {
        // Tool not configured in restricted_tools.php:
        // no restriction active, pass through to downstream middleware.
        if (! config("restricted_tools.tools.$tool")) {
            return $next($request);
        }

        if (! $this->access->isEnabledInCurrentEnvironment($tool)) {
            abort(404);
        }

        $user = backpack_auth()->user();
        $config = $this->access->config($tool);

        if ($this->access->canAccess($tool, $user, $request)) {
            return $next($request);
        }

        // IP-only restriction (e.g. backpack): always 403, no redirect.
        // Auth is handled by downstream middleware — the block is at network level.
        if (in_array('ip', $config['checks'], true) && ! in_array('auth', $config['checks'], true)) {
            abort(403, 'Access denied: IP not allowed');
        }

        if (! $user) {
            return redirect()->guest(backpack_url('login'));
        }

        abort(403, 'Access denied: insufficient permissions');
    }
}
