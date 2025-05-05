<?php

namespace App\Http\Middleware;

use App\Http\Traits\ChecksFrontendPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FrontendModelPermission
{
	use ChecksFrontendPermissions;

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
	 */
	public function handle(Request $request, Closure $next, string $modelName, string $action = "read"): Response
	{
		if (!$this->userCan($modelName, $action)) {
			return response()->json(["message" => "Forbidden"], 403);
		}

		return $next($request);
	}
}
