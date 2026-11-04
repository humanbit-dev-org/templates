<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Tests\TestCase refuses to set up a test that uses RefreshDatabase (or
 * another schema-dropping trait) against a non-disposable database.
 *
 * The guard is invoked directly on a probe test case that is never set up,
 * so RefreshDatabase never gets a chance to run.
 */
class DestructiveTraitsGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_schema_dropping_trait_aborts_on_the_development_database(): void
    {
        $probe = new class('probe') extends TestCase
        {
            use RefreshDatabase;
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('uses RefreshDatabase');

        $this->runGuardOf($probe);
    }

    public function test_database_transactions_pass_the_guard(): void
    {
        $this->runGuardOf($this);

        $this->assertTrue(true);
    }

    /**
     * Call the private guard of Tests\TestCase on the given test instance.
     */
    private function runGuardOf(TestCase $test): void
    {
        // Bound to the Tests\TestCase scope: the guard is private to that class.
        \Closure::bind(fn () => $this->guardAgainstDestructiveTraits(), $test, TestCase::class)();
    }
}
