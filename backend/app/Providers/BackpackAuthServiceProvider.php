<?php

namespace App\Providers;

use App\Models\ModelPermission;
use Illuminate\Support\Facades\Gate;
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
		Gate::define("backpack-access-model", ModelPermission::gateFor("backpack_role_id"));
	}
}
