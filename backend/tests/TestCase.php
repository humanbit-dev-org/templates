<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Traits that rebuild or empty the schema of the connected database.
     *
     * @var list<class-string>
     */
    private const DESTRUCTIVE_TRAITS = [
        RefreshDatabase::class,
        DatabaseMigrations::class,
        DatabaseTruncation::class,
    ];

    /**
     * Databases those traits are allowed to run against.
     *
     * @var list<string>
     */
    private const DISPOSABLE_DATABASES = ['templates_testing', ':memory:'];

    /**
     * Seatbelt.
     *
     * Tests run against the normal database and rely on DatabaseTransactions,
     * so every write is rolled back and nothing survives the run. That only
     * holds as long as no test swaps in RefreshDatabase, DatabaseMigrations or
     * DatabaseTruncation: those drop or empty every table, and against the
     * development database the data would be gone before anyone noticed.
     *
     * The check runs in setUpTraits(), BEFORE the traits are set up: checking
     * after parent::setUp() would be too late, because that is exactly where
     * RefreshDatabase runs migrate:fresh.
     */
    protected function setUpTraits()
    {
        $this->guardAgainstDestructiveTraits();

        return parent::setUpTraits();
    }

    private function guardAgainstDestructiveTraits(): void
    {
        $used = array_intersect(self::DESTRUCTIVE_TRAITS, class_uses_recursive(static::class));

        if ($used === []) {
            return;
        }

        $database = (string) DB::connection()->getDatabaseName();

        if (in_array(basename($database), self::DISPOSABLE_DATABASES, true)) {
            return;
        }

        $trait = class_basename((string) reset($used));

        throw new RuntimeException(
            'Test suite aborted: '.static::class." uses {$trait}, which drops or empties every table of "
            ."the database it is connected to — currently [{$database}]. "
            .'Use DatabaseTransactions instead, so the test rolls back and leaves the database untouched. '
            .'If the schema really has to be rebuilt, point DB_DATABASE at one of: '
            .implode(', ', self::DISPOSABLE_DATABASES).'.'
        );
    }
}
