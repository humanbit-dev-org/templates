<?php

namespace App\Console\Commands;

use App\Models\BackpackRole;
use App\Models\Role;
use App\Models\User;
use App\Observers\CacheInvalidationContext;
use Database\Seeders\MetadataSeeder;
use Database\Seeders\ModelPermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Rebuilds a complete, working local dataset.
 *
 * The point is repeatability: running it twice must leave the application in the
 * same usable state, with every screen populated — platform, business console
 * and Backpack — without hand-fixing anything afterwards.
 *
 * It only ever touches domain data. Schema and reference tables (translates,
 * pages, metadata, …) come from the dump and are left alone.
 */
class AppInit extends Command
{
    protected $signature = 'app:init
                            {--users=40    : Platform users generated per bank}
                            {--force       : Skip the confirmation prompt}
                            {--fresh-dump  : Reload the schema and reference data from the dump first}
                            {--keep-schema : Align the existing schema instead of rebuilding it from scratch}';

    protected $description = 'Build a complete working local dataset';

    private const SEED_PASSWORD = 'password';

    public function handle(): int
    {
        if (! $this->guardEnvironment()) {
            return self::FAILURE;
        }

        // Deterministic output across runs.
        mt_srand(20260101);

        if (! $this->ricostruisce()) {
            $result = $this->completaClienti();
            $this->syncSandbox();

            return $result;
        }

        if ($this->option('fresh-dump') && ! $this->reloadDump()) {
            return self::FAILURE;
        }

        if (! $this->refreshSchema()) {
            return self::FAILURE;
        }

        // Every write below is a bulk seed: per-row cache invalidation would
        // dominate the runtime and the caches are flushed at the end anyway.
        CacheInvalidationContext::suppress();

        try {
            $this->seedRolesIfMissing();
            $this->importTranslations();
            $this->seedInternalBackpackUsers();
            $this->postSeed();
        } finally {
            CacheInvalidationContext::restore();
        }

        $this->call('cache:clear');

        $this->newLine();
        $this->summary();
        $this->syncSandbox();

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Guards and dump
    // -------------------------------------------------------------------------

    /**
     * Tail of the seed sequence: a hook for variants that need extra shapes
     * (sandbox:seed fills it with demo data for the API documentation).
     */
    protected function postSeed(): void {}

    /**
     * Whether the anagrafiche are the command's to invent.
     *
     * In development it builds the whole dataset. Anywhere else the users are
     * real, so it only completes what they are missing — see completaClienti().
     */
    protected function ricostruisce(): bool
    {
        return app()->environment(['local', 'development', 'testing']);
    }

    /**
     * Keep the sandbox dataset in step with the feature flag.
     *
     * Enabled: rebuild the demo data, so it ages alongside the main dataset.
     * Disabled: empty the schema — a stale copy could drift behind newer
     * migrations and serve broken responses after a re-enable. Only the
     * tables go: the schema and its users are provisioned outside the app
     * (make sandbox-init locally, Aurora on AWS), so they must survive.
     */
    protected function syncSandbox(): void
    {
        if (config('sandbox.enabled')) {
            // sandbox:seed flips the default connection to keep its writes
            // inside the sandbox schema and never restores it — fair for an
            // artisan entry point, but here the call shares this process, so
            // anything after it would silently write to the wrong schema.
            $default = config('database.default');

            try {
                if ($this->call('sandbox:seed', ['--force' => true]) !== self::SUCCESS) {
                    $this->components->warn('Sandbox seeding failed — re-run it by hand: php artisan sandbox:seed --force');
                }
            } finally {
                DB::setDefaultConnection($default);
            }

            return;
        }

        $schema = config('database.connections.sandbox.database');

        try {
            // Point the admin connection at information_schema: connecting
            // with the sandbox database name fails when it does not exist,
            // which is the very case this cleanup handles, while the mysql
            // system schema requires grants sandadmin does not have.
            config(['database.connections.sandbox_admin.database' => 'information_schema']);
            DB::purge('sandbox_admin');

            $exists = DB::connection('sandbox_admin')
                ->table('information_schema.schemata')
                ->where('schema_name', $schema)
                ->exists();

            if ($exists) {
                config(['database.connections.sandbox_admin.database' => $schema]);
                DB::purge('sandbox_admin');
                DB::connection('sandbox_admin')->getSchemaBuilder()->dropAllTables();
            }
        } catch (Throwable $e) {
            // components is only bound during a real command run — syncSandbox
            // is also invoked directly (tests, app:init internals).
            $this->components?->warn("Sandbox schema [{$schema}] could not be emptied: ".$e->getMessage());
        }
    }

    protected function guardEnvironment(): bool
    {
        // Production is the one place this never runs: the data there is not
        // ours to complete, and aligning the structure is db:schema-sync's job.
        if (app()->environment('production')) {
            $this->error('app:init non è ammesso in produzione.');
            $this->line('  Per allineare lo schema conservando i dati: <fg=cyan>php artisan db:schema-sync --apply</>');

            return false;
        }

        $database = DB::connection()->getDatabaseName();

        $domanda = $this->ricostruisce()
            ? "Every user, tiraggio, delibera, storico, previsionale, trattenuta and proiezione in [{$database}] will be deleted and rebuilt. Continue?"
            : "Existing customers in [{$database}] will be given the storici, previsionali, proiezioni and trattenute they are missing. Nothing is deleted, and no anagrafica, bank or product is touched. Continue?";

        if ($this->option('force')) {
            return true;
        }

        // Without a terminal there is nobody to answer, and confirm() takes the
        // default — so the command used to exit 1 having said nothing at all.
        // Refusing is right; refusing in silence sends whoever scripted it
        // hunting through the seeder for a bug that is not there.
        if (! $this->input->isInteractive()) {
            $this->error("app:init needs a confirmation it cannot ask for here [{$database}].");
            $this->line('  Non-interactive runs: <fg=cyan>php artisan app:init --force</>');

            return false;
        }

        return $this->confirm($domanda, false);
    }

    /**
     * Rebuild the schema before seeding into it.
     *
     * This command only runs outside production and throws the domain data away
     * regardless, so there is nothing here worth preserving — a full
     * migrate:fresh is both simpler to reason about and the only way to be sure
     * the structure is exactly what the migrations describe. Someone running
     * this on a database a few migrations behind used to get seed data written
     * against the wrong shape, failing somewhere in the middle.
     *
     * --keep-schema is the way out for a database holding tables that live
     * outside the migrations and are worth keeping: it aligns instead of
     * dropping, which is slower to trust but destroys nothing.
     */
    private function refreshSchema(): bool
    {
        if ($this->option('keep-schema')) {
            $exit = self::SUCCESS;

            $this->components->task('Aligning schema with migrations', function () use (&$exit): bool {
                // No --prune: that answers yes to every removal, which is the
                // opposite of what --keep-schema is asking for. Unattended, the
                // removals are simply skipped.
                $exit = $this->callSilent('db:schema-sync', ['--apply' => true]);

                return $exit === self::SUCCESS;
            });

            if ($exit !== self::SUCCESS) {
                $this->error('Schema alignment failed — refusing to seed into an unknown structure.');
                $this->line('  Usually the snapshot of the migrations is behind: <fg=cyan>make db-schema-snapshot</>');
                $this->line('  To read the error in full: <fg=cyan>make db-schema-diff</>');

                return false;
            }

            return true;
        }

        $this->components->task('Rebuilding schema (migrate:fresh)', function (): bool {
            // Not --seed: DatabaseSeeder runs UserSeeder, which expects the
            // banks to exist, and this command creates those later. The
            // reference data it does need is seeded in seedRolesIfMissing().
            $this->callSilent('migrate:fresh', ['--force' => true]);

            return true;
        });

        return true;
    }

    private function reloadDump(): bool
    {
        $dump = base_path('../../dump/rays_new_v.sql');

        if (! File::isFile($dump)) {
            $this->error("Dump not found at {$dump}");

            return false;
        }

        $this->components->task('Reloading schema and reference data from the dump', function () use ($dump): bool {
            $config = config('database.connections.'.config('database.default'));

            $command = sprintf(
                'mysql -h%s -P%s -u%s -p%s %s < %s',
                escapeshellarg($config['host']),
                escapeshellarg((string) $config['port']),
                escapeshellarg($config['username']),
                escapeshellarg((string) $config['password']),
                escapeshellarg($config['database']),
                escapeshellarg($dump),
            );

            exec($command.' 2>&1', $output, $exitCode);

            return $exitCode === 0;
        });

        return true;
    }

    /**
     * Translations are a projection of frontend/lang/*.json, not seed data —
     * there is already a command that imports them.
     */
    private function importTranslations(): void
    {
        $this->components->task('Translations from frontend/lang', function (): bool {
            return $this->callSilent('translations:import') === self::SUCCESS;
        });
    }

    private function seedRolesIfMissing(): void
    {
        $this->components->task('Roles and permissions', function (): void {
            foreach ([
                1 => 'Public', 2 => 'User', 3 => 'Desio', 4 => 'Filiale', 5 => 'Product Owner',
            ] as $id => $name) {
                Role::query()->updateOrCreate(['id' => $id], ['name' => $name, 'description' => $name]);
            }

            foreach (['Admin' => 'Administrator', 'Developer' => 'Developer', 'Guest' => 'Guest'] as $name => $description) {
                BackpackRole::query()->updateOrCreate(['name' => $name], ['description' => $description]);
            }

            // Roles and backpack roles are built above; these two are not, and
            // migrate:fresh empties them. An empty model_permissions is why
            // Backpack came up with no menu at all — no permissions, no entries.
            $this->callSilent('db:seed', ['--class' => ModelPermissionSeeder::class, '--force' => true]);
            $this->callSilent('db:seed', ['--class' => MetadataSeeder::class, '--force' => true]);
        });
    }
    // -------------------------------------------------------------------------
    // Users
    // -------------------------------------------------------------------------

    /**
     * The internal Humanbit accounts. They log into Backpack, so they need a
     * verified email, a known password and a Backpack role — without all three
     * the panel refuses them.
     */
    protected function seedInternalBackpackUsers(): void
    {
        $this->components->task('Backpack operators (Humanbit)', function (): void {
            $developerRoleId = BackpackRole::query()->where('name', 'Developer')->value('id');
            $adminRoleId = BackpackRole::query()->where('name', 'Admin')->value('id');
            $userRoleId = Role::query()->where('name', 'User')->value('id');

            $password = config('backpack.base.admin_panel_password') ?: self::SEED_PASSWORD;

            // Keyed by email rather than by ndg: the domain is not always
            // humanbit.com, and deriving it made anyone outside it a special case.
            foreach ([
                'borreca@humanbit.com' => 'Developer',
                'silveri@humanbit.com' => 'Developer',
                'busan@humanbit.com' => 'Developer',
                'marcom@cashberry.it' => 'Developer',
                'solia@humanbit.com' => 'Developer',
            ] as $email => $backpackRole) {
                $ndg = strstr($email, '@', true);

                User::query()->updateOrCreate(
                    ['username' => $ndg],
                    [
                        'email' => $email,
                        'backpack_role_id' => $backpackRole === 'Developer' ? $developerRoleId : $adminRoleId,
                        'role_id' => $userRoleId,
                        'password' => $password,
                        'email_verified_at' => now(),
                    ],
                );
            }
        });
    }
}
