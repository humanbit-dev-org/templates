<?php

/**
 * PHPUnit bootstrap.
 *
 * The application runs in Docker with APP_ENV and DB_* exported into the process
 * environment. PHP exposes those through $_SERVER, and Laravel's env repository
 * reads $_SERVER before $_ENV and putenv() — so PHPUnit's <env force="true">
 * entries are silently ignored for those keys, which is why tests used to run
 * with APP_ENV=local.
 *
 * The overrides below are therefore applied to all three channels, before the
 * framework boots.
 *
 * The database connection is deliberately NOT overridden: tests run against the
 * normal database and wrap every case in a transaction that is rolled back
 * (DatabaseTransactions), so they leave no trace. Tests\TestCase enforces that
 * no test reintroduces a schema-dropping trait here.
 */

require __DIR__.'/../vendor/autoload.php';

$overrides = [
    'APP_ENV' => 'testing',
    // The suite asserts exact Italian messages; pin the locale so it holds
    // regardless of the container's APP_LOCALE.
    'APP_LOCALE' => 'it',
];

foreach ($overrides as $key => $value) {
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    putenv("{$key}={$value}");
}
