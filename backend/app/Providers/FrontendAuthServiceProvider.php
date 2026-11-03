<?php

namespace App\Providers;

use App\Models\ModelPermission;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class FrontendAuthServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		//
	}

	public function boot(): void
	{
		// Verify if user has access to a specific model and action
		Gate::define("frontend-access-model", ModelPermission::gateFor("role_id"));
	}
}
