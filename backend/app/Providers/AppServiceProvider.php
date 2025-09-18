<?php

namespace App\Providers;

use App\Models\User;
use Dedoc\Scramble\Scramble;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Resources\Json\JsonResource;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;

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

		JsonResource::withoutWrapping();

		//Scramble
		Gate::define("viewApiDocs", function (?User $user) {
			// consenti in questi ambienti
			if (App::environment(["local", "development", "staging", "production"])) {
				return true;
			}

			// consenti a utenti specifici in prod
			return in_array(
				optional($user)->email,
				[
					"solia@humanbit.com", // aggiungi altri se serve
				],
				true
			);
		});

		Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi) {
			$openApi->components->securitySchemes["SwaggerBearer"] = SecurityScheme::http("bearer", "Sanctum PAT");
			$openApi->components->securitySchemes["SwaggerBearer"]->description =
				"USARE SOLO IN SWAGGER: incolla un Personal Access Token Sanctum (Authorization: Bearer ...)";
			$openApi->components->securitySchemes["php_session"] = SecurityScheme::apiKey("header", "cookie");
			$openApi->security[] = new SecurityRequirement([
				"SwaggerBearer" => ["closed=3ganyaHkaZrey6fjgYzHJ7RImWra27Ed6os4uPuHf0ff7beb"],
			]);
			$openApi->security[] = new SecurityRequirement([
				"php_session" => ["php_session=3ganyaHkaZrey6fjgYzHJ7RImWra27Ed6os4uPuHf0ff7beb"],
			]);
		});
	}
}
