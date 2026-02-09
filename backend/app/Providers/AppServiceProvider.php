<?php

namespace App\Providers;

use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
		$appUrl = config("app.url");
		$scheme = parse_url($appUrl, PHP_URL_SCHEME);
		$frontendUrl = config("app.frontend_url");

		URL::forceScheme($scheme);
		URL::forceRootUrl($appUrl);
		ResetPassword::createUrlUsing(function (object $notifiable, string $token) use ($frontendUrl) {
			return $frontendUrl . "?token=$token&email={$notifiable->getEmailForPasswordReset()}";
		});

		// Register blocked migration commands for production
		// The commands will check LARAVEL_MIGRATE_ORIGINAL at runtime
		if ($this->app->runningInConsole() && config("app.env") === "production") {
			$this->commands([
				\App\Console\Commands\Database\BlockMigrateFresh::class,
				\App\Console\Commands\Database\BlockMigrate::class,
			]);
		}

		// Scramble
		Gate::define("viewApiDocs", function (?User $user) {
			// consenti in questi ambienti
			if (App::environment(["local", "development", "staging", "production"])) {
				return true;
			}
		});

		Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi) {
			$openApi->secure(SecurityScheme::http("bearer"));
		});

		JsonResource::withoutWrapping();
	}
}
