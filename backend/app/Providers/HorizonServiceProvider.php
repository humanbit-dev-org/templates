<?php

namespace App\Providers;

use App\Services\Restricted\RestrictedToolAccessService;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
	/**
	 * Bootstrap any application services.
	 */
	public function boot(): void
	{
		parent::boot();

		// Horizon::routeSmsNotificationsTo('15556667777');
		// Horizon::routeMailNotificationsTo('example@example.com');
		// Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
	}

	/**
	 * Register the Horizon gate.
	 *
	 * Requires Backpack authentication with Admin or Developer role.
	 * Same behavior in all environments (local, staging, production).
	 */
	protected function gate(): void
	{
		$access = app(RestrictedToolAccessService::class);

		Gate::define("viewHorizon", function ($user = null) use ($access) {
			return $access->canAccess("horizon", backpack_auth()->user(), request());
		});
	}

	/**
	 * Configure the Horizon authorization services.
	 *
	 * Override parent to remove the "|| app()->environment('local')" bypass.
	 * Authentication is required in ALL environments.
	 * Uses backpack_auth() instead of $request->user() for correct guard.
	 */
	protected function authorization(): void
	{
		$this->gate();

		Horizon::auth(function ($request) {
			return Gate::check("viewHorizon", [backpack_auth()->user()]);
		});
	}
}
