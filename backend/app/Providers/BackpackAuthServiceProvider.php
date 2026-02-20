<?php

namespace App\Providers;

use App\Models\ModelPermission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class BackpackAuthServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		//
	}

	public function boot(): void
	{
		// Verify if user has access to a specific model and action
		Gate::define("backpack-access-model", function (User $user, string $modelName, string $action) {
			// If user doesn't have a backpack role, they don't have access
			if (!$user->backpack_role_id) {
				return false;
			}

			// Search permissions for this role and model
			$permissions = ModelPermission::where("backpack_role_id", $user->backpack_role_id)->get();

			foreach ($permissions as $permission) {
				$models = $permission->model_name;

				// Verify if the model is included in the permissions
				// Supports both numeric arrays ["Model1", "Model2"]
				// and associative arrays {"Model1": "Model1"}
				$modelExists = false;
				if (is_array($models)) {
					$modelExists = in_array($modelName, $models) || array_key_exists($modelName, $models);
				}

				if ($modelExists) {
					// Check the specific permission
					$result = false;
					switch ($action) {
						case "read":
							$result = (bool) $permission->can_read;
							break;
						case "create":
							$result = (bool) $permission->can_create;
							break;
						case "update":
							$result = (bool) $permission->can_update;
							break;
						case "delete":
							$result = (bool) $permission->can_delete;
							break;
					}

					return $result;
				}
			}

			return false;
		});
	}
}
