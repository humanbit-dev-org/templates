<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

trait ChecksBackpackPermissions
{
	/**
	 * Controlla se l'utente corrente ha il permesso di eseguire un'azione su un modello
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
	 * Applica i controlli di autorizzazione sui metodi CRUD nel controller
	 */
	protected function setupPermissionChecks(): void
	{
		// Estrai il nome del modello dal nome della classe controller
		$fullClassName = static::class;
		$parts = explode("\\", $fullClassName);
		$className = end($parts);
		$modelName = str_replace("CrudController", "", $className);

		// Applica il middleware per ogni azione CRUD
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
}
