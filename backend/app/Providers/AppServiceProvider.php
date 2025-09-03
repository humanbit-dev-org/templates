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
		$this->app->singleton(\App\Services\TwoFactorAuthService::class);
	}

	/**
	 * Bootstrap any application services.
	 */
	public function boot(): void
	{
		$appUrl = env("APP_URL");
		$scheme = parse_url($appUrl, PHP_URL_SCHEME);
		$frontendUrl = env("FRONTEND_URL");

		URL::forceScheme($scheme);
		URL::forceRootUrl($appUrl);
		ResetPassword::createUrlUsing(function (object $notifiable, string $token) use ($frontendUrl) {
			return $frontendUrl . "?token=$token&email={$notifiable->getEmailForPasswordReset()}";
		});
	}
}
