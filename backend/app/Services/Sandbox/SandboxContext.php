<?php

namespace App\Services\Sandbox;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Switches the application to the sandbox context for the current request.
 *
 * Changing the default DB connection is enough to isolate data: no model
 * declares an explicit $connection, so Eloquent, Sanctum tokens and
 * validation rules all follow the sandbox schema.
 *
 * Nothing is restored on purpose: under PHP-FPM the process state dies with
 * the request anyway.
 */
class SandboxContext
{
    public static function enter(string $connection): void
    {
        DB::setDefaultConnection($connection);

        config([
            'cache.default' => 'array', // nothing shared with staging, nothing left after the request
            'mail.default' => 'log',
            'queue.default' => 'sync',
            'app.debug' => false,
        ]);

        app()->forgetInstance('cache.store');

        // No outbound call ever leaves the sandbox: every one gets a fake 200.
        Http::fake(['*' => Http::response(['access_token' => 'sandbox'], 200)]);
    }
}
