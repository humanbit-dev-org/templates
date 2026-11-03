<?php

namespace App\Providers;

use App\Logging\CloudWatchHandler;
use App\Models\Metadata;
use App\Models\User;
use App\Observers\MetadataObserver;
use App\Observers\UserObserver;
use App\Queue\ClusterRedisConnector;
use App\Services\ApiLab\ApiLabTrace;
use App\Services\Auth\DerivingArgon2IdHasher;
use App\Services\Auth\PasswordDerivationService;
use App\Services\Auth\TwoFactorAuthService;
use App\Services\Restricted\RestrictedToolAccessService;
use App\Support\Cache\ScopedRedisStore;
use App\Support\Locale\Locales;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The redis cache driver with a flush limited to its own keys, before
        // anything resolves a store: see ScopedRedisStore.
        $this->app->booting(function (): void {
            Cache::extend('redis', function ($app, array $config) {
                $connection = $config['connection'] ?? 'default';
                $store = new ScopedRedisStore(
                    $app['redis'],
                    $config['prefix'] ?? $app['config']['cache.prefix'],
                    $connection,
                    $app['config']['cache.serializable_classes'] ?? null,
                );

                return $this->repository($store->setLockConnection($config['lock_connection'] ?? $connection), $config);
            });
        });

        $this->app->singleton(TwoFactorAuthService::class);
        $this->app->singleton(PasswordDerivationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // HKDF pepper applied uniformly: the custom argon2id driver derives every
        // value before hashing/checking, so Auth::attempt, the Backpack login, the
        // current_password rule, the 'hashed' cast and the password broker are all
        // derivation-aware without touching the call sites.
        Hash::extend('argon2id', function ($app) {
            return new DerivingArgon2IdHasher($app['config']->get('hashing.argon') ?? []);
        });

        // CloudWatch handlers buffer: send at the end of each request, job and
        // command. Horizon workers never exit, so the destructor is not enough.
        if (filled(config('logging.cloudwatch.group'))) {
            $this->app->terminating(static fn () => CloudWatchHandler::flushAll());
            Event::listen(
                [JobProcessed::class, JobFailed::class, JobExceptionOccurred::class, CommandFinished::class],
                static fn () => CloudWatchHandler::flushAll(),
            );
        }

        $this->app->afterResolving(QueueManager::class, function (QueueManager $manager) {
            $manager->addConnector('redis', function () {
                return new ClusterRedisConnector($this->app['redis']);
            });
        });

        RateLimiter::for('login', function (Request $request) {
            if (App::environment('local') || App::environment('integration')) {
                return Limit::perMinute(PHP_INT_MAX)->by('local');
            }

            $page = $request->input('page', '');
            $frontendUrl = rtrim(config('app.frontend_url'), '/').($page ? "/{$page}" : '');

            // Detect locale from Referer URL (e.g. /en/, /de/ or /it/)
            $referer = $request->headers->get('referer', '');
            $locale = preg_match('#/(en|de)/#', $referer, $matches) ? $matches[1] : 'it';

            $throttleResponse = function (Request $req, array $headers) use ($frontendUrl, $locale) {
                App::setLocale($locale);
                $seconds = $headers['Retry-After'] ?? null;
                $minutes = $seconds ? (int) ceil($seconds / 60) : '?';
                $message = __('auth.throttle', ['minutes' => $minutes]);

                return redirect()->away("{$frontendUrl}?error=".urlencode($message));
            };

            return [
                // Block by IP
                Limit::perMinutes(15, 5)->by('ip:'.$request->ip())
                    ->response($throttleResponse),
                // Block by target email (prevents distributed attacks from multiple IPs)
                Limit::perMinutes(15, 5)->by('email:'.$request->input('email'))
                    ->response($throttleResponse),
            ];
        });

        RateLimiter::for('password.forgot', function (Request $request) {
            if (App::environment('local')) {
                return Limit::perMinute(PHP_INT_MAX)->by('local');
            }

            return Limit::perMinutes(15, 5)->by('ip:'.$request->ip())
                ->response(function (Request $req, array $headers) {
                    $locale = Locales::normalize($req->header('X-Locale'));
                    App::setLocale($locale);
                    $seconds = $headers['Retry-After'] ?? null;
                    $minutes = $seconds ? (int) ceil($seconds / 60) : '?';
                    $message = __('passwords.throttle', ['minutes' => $minutes]);

                    return response()->json(['message' => $message], 429, $headers);
                });
        });

        RateLimiter::for('password.reset', function (Request $request) {
            if (App::environment('local')) {
                return Limit::perMinute(PHP_INT_MAX)->by('local');
            }

            return Limit::perMinutes(15, 10)->by('ip:'.$request->ip())
                ->response(function (Request $req, array $headers) {
                    $locale = Locales::normalize($req->header('X-Locale'));
                    App::setLocale($locale);
                    $seconds = $headers['Retry-After'] ?? null;
                    $minutes = $seconds ? (int) ceil($seconds / 60) : '?';
                    $message = __('passwords.throttle', ['minutes' => $minutes]);

                    return response()->json(['message' => $message], 429, $headers);
                });
        });

        RateLimiter::for('contatto.store', function (Request $request) {
            if (App::environment('local')) {
                return Limit::perMinute(PHP_INT_MAX)->by('local');
            }

            $userId = $request->user()?->user_id ?? $request->ip();

            return Limit::perMinutes(15, 3)->by('contatto:'.$userId)
                ->response(function (Request $req, array $headers) {
                    $locale = Locales::normalize($req->header('X-Locale'));
                    App::setLocale($locale);
                    $seconds = $headers['Retry-After'] ?? null;
                    $minutes = $seconds ? (int) ceil($seconds / 60) : '?';
                    $message = __('contatto.throttle', ['minutes' => $minutes]);

                    return response()->json(['message' => $message], 429, $headers);
                });
        });

        // Admin panel support widget: a per-hour cap for each Backpack user.
        // The key uses the "backpack" guard explicitly, because $request->user()
        // reads the default guard and would fall back to the IP for admins.
        RateLimiter::for('admin-support', function (Request $request) {
            if (App::environment('local')) {
                return Limit::perMinute(PHP_INT_MAX)->by('local');
            }

            $userId = backpack_auth()->id() ?? $request->ip();

            return Limit::perHour((int) config('backpack.support.throttle_per_hour', 10))
                ->by('admin-support:'.$userId)
                ->response(function (Request $req, array $headers) {
                    $seconds = $headers['Retry-After'] ?? null;
                    $minutes = $seconds ? (int) ceil($seconds / 60) : '?';

                    return response()->json([
                        'message' => __('backpack::support.throttle', ['minutes' => $minutes]),
                    ], 429, $headers);
                });
        });

        // Sandbox rate limits — per IP. Applied on routes via
        // ThrottleRequestsWithRedis::using(), which talks to Redis directly
        // and is unaffected by the sandbox 'array' cache driver.
        RateLimiter::for('sandbox-token', function (Request $request) {
            return Limit::perHour(config('sandbox.limits.tokens_per_hour', 10))->by($request->ip());
        });

        RateLimiter::for('sandbox-calls', function (Request $request) {
            return Limit::perMinute(config('sandbox.limits.calls_per_minute', 60))->by($request->ip());
        });

        // Portal rate limits — per IP, same model as the sandbox ones: they
        // throttle abuse, not bank traffic, so the caps stay generous.
        RateLimiter::for('portal-token', function (Request $request) {
            return Limit::perHour(config('portal.limits.tokens_per_hour', 20))->by($request->ip());
        });

        RateLimiter::for('portal-calls', function (Request $request) {
            return Limit::perMinute(config('portal.limits.calls_per_minute', 60))->by($request->ip());
        });

        // =====================================================
        // Cache invalidation — Eloquent Observers
        // -----------------------------------------------------
        // These fire on single-row Eloquent operations:
        //   - Backpack admin create/update/delete (daily edits)
        //   - Frontend controller writes (tiraggi, user, etc.)
        //   - ImportCsvAction Eloquent paths (Backpack UI import)
        //
        // Bulk import paths that use raw DB::statement or query
        // builder updates bypass Eloquent — those are covered by
        // CacheFlush::forBanca() called post-import explicitly.
        // =====================================================

        User::observe(UserObserver::class);
        Metadata::observe(MetadataObserver::class);

        $appUrl = config('app.url');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME);
        $frontendUrl = config('app.frontend_url');

        URL::forceScheme($scheme);
        URL::forceRootUrl($appUrl);
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) use ($frontendUrl) {
            return $frontendUrl."?token=$token&email={$notifiable->getEmailForPasswordReset()}";
        });

        // MigrationPolicy is not registered here: Laravel discovers listeners in
        // app/Listeners on its own, and registering it again fired every prompt
        // twice.

        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(SecurityScheme::http('bearer'));
        });
        Scramble::ignoreDefaultRoutes();

        $restrictedTools = $this->app->make(RestrictedToolAccessService::class);
        if ($restrictedTools->isEnabledInCurrentEnvironment('swagger')) {
            Scramble::registerUiRoute('swagger')->name('scramble.docs.ui');
            Scramble::registerJsonSpecificationRoute('swagger.json')->name('scramble.docs.document');
        }

        JsonResource::withoutWrapping();

        Event::listen(RequestSending::class, function (RequestSending $event) {
            if ($this->app->bound(ApiLabTrace::class)) {
                $this->app->make(ApiLabTrace::class)->onRequestSending($event);
            }
        });

        Event::listen(ResponseReceived::class, function (ResponseReceived $event) {
            if ($this->app->bound(ApiLabTrace::class)) {
                $this->app->make(ApiLabTrace::class)->onResponseReceived($event);
            }
        });
    }
}
