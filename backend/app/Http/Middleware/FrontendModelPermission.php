<?php

namespace App\Http\Middleware;

use App\Http\Traits\ChecksFrontendPermissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class FrontendModelPermission
{
	use ChecksFrontendPermissions;

	/**
	 * Static helper to create middleware string with clear syntax
	 *
	 * @param array $models List of model names (OR logic)
	 * @param string $action Permission action (read, create, update, delete)
	 * @return string
	 *
	 * @example
	 * // Single model
	 * FrontendModelPermission::require('User', 'read')
	 *
	 * // Multiple models (OR logic)
	 * FrontendModelPermission::require(['User', 'Ecommerce'], 'read')
	 */
	public static function require($models, string $action = "read"): string
	{
		$modelList = is_array($models) ? $models : [$models];
		$params = implode(",", array_merge($modelList, [$action]));

		return "frontend.permission:{$params}";
	}

	/**
	 * Shorthand for read permission
	 */
	public static function read($models): string
	{
		return self::require($models, "read");
	}

	/**
	 * Shorthand for create permission
	 */
	public static function create($models): string
	{
		return self::require($models, "create");
	}

	/**
	 * Shorthand for update permission
	 */
	public static function update($models): string
	{
		return self::require($models, "update");
	}

	/**
	 * Shorthand for delete permission
	 */
	public static function delete($models): string
	{
		return self::require($models, "delete");
	}

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
	 */
	public function handle(Request $request, Closure $next, ...$params): Response
	{
		$user = Auth::user();

		// Parse parameters - last one is action, all others are model names
		$action = array_pop($params) ?: "read";
		$modelNames = $params;

		if (!$user) {
			return response()->json(["message" => "Unauthorized"], 401);
		}

		// Check if user has access to ANY of the models (OR logic)
		if (count($modelNames) > 1) {
			$hasAccess = false;
			foreach ($modelNames as $model) {
				if (Gate::forUser($user)->allows("frontend-access-model", [$model, $action])) {
					$hasAccess = true;
					break;
				}
			}

			if (!$hasAccess) {
				return response()->json(["message" => "Forbidden"], 403);
			}
		} else {
			// Single model
			$modelName = $modelNames[0] ?? "";

			if (!Gate::forUser($user)->allows("frontend-access-model", [$modelName, $action])) {
				return response()->json(["message" => "Forbidden"], 403);
			}
		}

		return $next($request);
	}
}
