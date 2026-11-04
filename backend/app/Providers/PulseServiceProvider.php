<?php

namespace App\Providers;

use App\Services\Restricted\RestrictedToolAccessService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PulseServiceProvider extends ServiceProvider
{
	/**
	 * Register any application services.
	 */
	public function register(): void
	{
		//
	}

	/**
	 * Bootstrap any application services.
	 *
	 * Overrides the default Pulse gate to require Backpack authentication
	 * with Admin or Developer role in ALL environments.
	 */
	public function boot(): void
	{
		$access = app(RestrictedToolAccessService::class);

		Gate::define("viewPulse", function ($user = null) use ($access) {
			return $access->canAccess("pulse", backpack_auth()->user(), request());
		});
	}
}
