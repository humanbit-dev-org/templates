<?php

namespace App\Http\Concerns;

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
}
