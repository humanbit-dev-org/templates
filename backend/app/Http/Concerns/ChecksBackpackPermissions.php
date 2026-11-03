<?php

namespace App\Http\Concerns;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

trait ChecksBackpackPermissions
{
	/**
	 * Check if the current backpack user has permission to perform an action on a model
	 */
	protected function userCan(string $modelName, string $action): bool
	{
		$user = Auth::guard(backpack_guard_name())->user();
		if (!$user) {
			return false;
		}
		$result = Gate::forUser($user)->allows("backpack-access-model", [$modelName, $action]);
		return $result;
	}

	/**
	 * Apply the authorization checks on the CRUD methods in the controller
	 */
	protected function setupPermissionChecks(): void
	{
		// Extract the model name from the controller class name
		$fullClassName = static::class;
		$parts = explode("\\", $fullClassName);
		$className = end($parts);
		$modelName = str_replace("CrudController", "", $className);

		// Apply the middleware for each CRUD action
		$this->crud->denyAccess(["list", "create", "update", "delete"]);

		if ($this->userCan($modelName, "read")) {
			$this->crud->allowAccess("list");
			$this->crud->allowAccess("show");
		}

		if ($this->userCan($modelName, "create")) {
			$this->crud->allowAccess("create");
		}

		if ($this->userCan($modelName, "update")) {
			$this->crud->allowAccess("update");
		}

		if ($this->userCan($modelName, "delete")) {
			$this->crud->allowAccess("delete");
		}
	}

	/**
	 * Check if current backpack user can access a specific model menu item
	 */
	public static function userCanAccessMenu(string $modelName): bool
	{
		$user = Auth::guard(backpack_guard_name())->user();
		if (!$user) {
			return false;
		}

		return Gate::forUser($user)->allows("backpack-access-model", [$modelName, "read"]);
	}

	/**
	 * Check if current backpack user can access any of the provided menu items
	 *
	 * @param array $items Array of items containing 'name' key with model name
	 * @return bool
	 */
	public static function userCanAccessAnyMenu(array $items): bool
	{
		// Custom routes that don't require model permissions (accessible to all non-guest users)
		$customRoutes = ["CsvUpload"];

		foreach ($items as $item) {
			if (isset($item["name"])) {
				// Allow access to custom routes for all non-guest users
				if (in_array($item["name"], $customRoutes)) {
					return true;
				}
				// Check model permissions for regular routes
				if (self::userCanAccessMenu($item["name"])) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Check if current backpack user is a developer
	 *
	 * @return bool
	 */
	public static function isDeveloper(): bool
	{
		$user = Auth::guard(backpack_guard_name())->user();
		if (!$user) {
			return false;
		}

		// Check if user has backpack role and if it's "developer"
		return $user->backpackRole && strtolower($user->backpackRole->name) === "developer";
	}
}
