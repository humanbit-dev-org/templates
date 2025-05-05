<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

trait ChecksFrontendPermissions
{
	/**
	 * Check if the current frontend user has permission to perform an action on a model
	 */
	protected function userCan(string $modelName, string $action): bool
	{
		$user = Auth::user();
		if (!$user) {
			return false;
		}
		$result = Gate::forUser($user)->allows("frontend-access-model", [$modelName, $action]);
		return $result;
	}

	/**
	 * Check if current frontend user can access a specific model feature
	 */
	public static function userCanAccess(string $modelName, string $action = "read"): bool
	{
		$user = Auth::user();
		if (!$user) {
			return false;
		}

		return Gate::forUser($user)->allows("frontend-access-model", [$modelName, $action]);
	}

	/**
	 * Check if current frontend user can access any of the provided features
	 *
	 * @param array $items Array of items containing 'name' key with model name and 'action' key with permission type
	 * @return bool
	 */
	public static function userCanAccessAny(array $items): bool
	{
		foreach ($items as $item) {
			if (isset($item["name"])) {
				$action = $item["action"] ?? "read";
				if (self::userCanAccess($item["name"], $action)) {
					return true;
				}
			}
		}
		return false;
	}
}
