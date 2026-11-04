<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RoleScope
{
	public function handle(Request $request, Closure $next, ...$allowedRoles): Response
	{
		$user = $request->user();
		if (!$user) {
			return response()->json(["message" => "Unauthorized"], 401);
		}

		$backpackRoleName = Str::lower((string) $user->backpackRole?->name);
		if (in_array($backpackRoleName, ["admin", "developer"], true)) {
			return $next($request);
		}

		if (empty($allowedRoles)) {
			return response()->json(["message" => "Forbidden"], 403);
		}

		$normalizedAllowedRoles = array_map(static fn($role): string => Str::lower(trim((string) $role)), $allowedRoles);

		$userRoleName = Str::lower((string) $user->role?->name);
		if (!in_array($userRoleName, $normalizedAllowedRoles, true)) {
			return response()->json(["message" => "Forbidden"], 403);
		}

		return $next($request);
	}
}
