<?php

namespace App\Providers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;

class AppServiceProvider extends ServiceProvider
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
	 */
	public function boot(): void
	{
		// URL::forceScheme('https');
		URL::forceRootUrl(config("app.url"));
		ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
			return config("app.frontend_url") . "?token=$token&email={$notifiable->getEmailForPasswordReset()}";
		});
	}
}
