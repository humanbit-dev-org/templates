<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\Sandbox\SandboxContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The sandbox variant of app:init.
 *
 * Same seeding machinery, different target: the sandbox schema, reachable
 * only through the /sandbox/* routes. On top of the reference data it creates
 * the demo account of every profile declared in config('sandbox.profiles'),
 * which is the account sandbox tokens are minted on.
 *
 * Always rebuilds: the data is fake and deterministic, so a fresh build is
 * the only trustworthy state — and migrate:fresh keeps the schema in step
 * with the migrations for free.
 *
 * Runs on the admin connection: migrate:fresh needs DDL, and the limited
 * app user exists to protect the per-request rollback, not the seeder.
 */
class SandboxSeed extends AppInit
{
    protected $signature = 'sandbox:seed
                            {--force       : Skip the confirmation prompt}
                            {--fresh-dump  : Reload the schema and reference data from the dump first}
                            {--keep-schema : Align the existing schema instead of rebuilding it from scratch}';

    protected $description = 'Rebuild the sandbox demo dataset inside the sandbox schema';

    protected function guardEnvironment(): bool
    {
        if (! config('sandbox.enabled')) {
            $this->error('Sandbox is disabled — set SANDBOX_ENABLED=true first.');

            return false;
        }

        // Probe before switching the default connection: if the schema does
        // not exist yet, connecting to it by name fails outright.
        try {
            DB::connection('sandbox_admin')->select('select 1');
        } catch (QueryException) {
            $schema = config('database.connections.sandbox.database');
            $this->error("Sandbox database [{$schema}] is not reachable — run make sandbox-init (or provision the schema on AWS).");

            return false;
        }

        // Every write below lands on the sandbox schema — migrations included,
        // the admin connection has DDL. The array cache also turns the
        // inherited cache:clear into a no-op on the real store.
        SandboxContext::enter('sandbox_admin');

        return parent::guardEnvironment();
    }

    /**
     * The sandbox always rebuilds: there is no real data to preserve here.
     */
    protected function ricostruisce(): bool
    {
        return true;
    }

    /**
     * The sandbox is not a Backpack environment: no operator accounts.
     */
    protected function seedInternalBackpackUsers(): void {}

    /**
     * This command IS the sandbox sync: without the no-op, the inherited
     * handle() would call sandbox:seed again from inside itself.
     */
    protected function syncSandbox(): void {}

    /**
     * One demo account per sandbox profile, with predictable credentials:
     * the username doubles as the password, which is acceptable only because
     * the sandbox holds fake data and is never enabled in production.
     */
    protected function postSeed(): void
    {
        $this->components->task('Sandbox demo accounts', function (): void {
            $userRoleId = Role::query()->where('name', 'User')->value('id');

            foreach (config('sandbox.profiles', []) as $key => $profile) {
                User::query()->updateOrCreate(
                    ['username' => $profile['user']],
                    [
                        'email' => sprintf('%s@sandbox.test', $profile['user']),
                        'name' => 'Sandbox',
                        'surname' => $profile['label'] ?? $key,
                        'role_id' => $userRoleId,
                        'password' => $profile['user'],
                        'email_verified_at' => now(),
                    ],
                );
            }
        });
    }

    protected function summary(): void
    {
        foreach (config('sandbox.profiles', []) as $key => $profile) {
            $this->components->twoColumnDetail('<fg=green>Profile</>', ($profile['label'] ?? $key).' ('.$profile['user'].')');
        }

        $this->components->twoColumnDetail('<fg=green>Users</>', (string) User::query()->count());

        $this->newLine();
        $this->components->info('Mint sandbox tokens via POST /sandbox/token');
    }
}
