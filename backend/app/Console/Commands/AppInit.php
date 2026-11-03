<?php

namespace App\Console\Commands;

use App\Models\BackpackRole;
use App\Models\Banca;
use App\Models\Delibera;
use App\Models\Filiale;
use App\Models\Interfaccia;
use App\Models\Previsionale;
use App\Models\Prodotto;
use App\Models\Proiezione;
use App\Models\Role;
use App\Models\Storico;
use App\Models\Tiraggio;
use App\Models\Trattenuta;
use App\Models\User;
use App\Observers\CacheInvalidationContext;
use App\Services\Banca\UserMensiliPeriodService;
use App\Support\Credito\ConguaglioSchedule;
use App\Support\Credito\PianoRientroCalculator;
use App\Support\Credito\TiraggioLimits;
use App\Support\Media\MediaStorage;
use Carbon\Carbon;
use Database\Seeders\MetadataSeeder;
use Database\Seeders\ModelPermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

    protected $description = 'Build a complete working local dataset: banks, products, interfaces, users, tiraggi, trattenute and proiezioni';

    /** Statuses that must all be represented, with at least MIN_PER_STATUS users each, per bank. */
    private const TIRAGGIO_STATUSES = ['open', 'requested', 'accepted', 'paid', 'rejected', 'dropped'];

    private const MIN_PER_STATUS = 5;

    /**
     * Share of the deliberated amount a running tiraggio must still owe.
     *
     * Only 'paid' reaches 100%. Without this floor the seeded withholdings could
     * close an 'accepted' tiraggio outright — 98 of them showed a full repayment
     * while still counted as running, a state the platform never produces.
     */
    private const RESIDUO_TIRAGGIO_APERTO = 0.03;

    /** The internal bank: its users are the Backpack operators, not platform clients. */
    private const INTERNAL_BANCA_ABI = Banca::INTERNAL_ABI;

    /**
     * Endeka is invented — there is no such Italian bank and this ABI is not
     * checked against the real ABI registry. It exists to give the previsionale
     * conguaglio a home of its own, next to AideXa (03625, real) on plafond.
     */
    private const FICTIONAL_BANCA_ABI = '03980';

    /**
     * Banks where the user picks the duration and the percentuale follows.
     * 02008 and 03625 are the two the frontend used to detect by hardcoded ABI,
     * so seeding them keeps app:init in step with the migration backfill.
     */
    private const ABI_DURATA_PARAMETRICA = ['02008', '03625', self::FICTIONAL_BANCA_ABI, '05387'];

    private const SEED_PASSWORD = 'password';

    /**
     * Sparkasse (Bolzano): the acquirer identifies a pratica by the tiraggio's
     * technical IBAN — the dealId its settlements carry IS the IBAN — so the
     * seed gives its deliberated tiraggi numero_pratica = iban_tecnico.
     */
    private const ABI_SPARKASSE = '06045';

    /** Sequence feeding the Sparkasse tiraggi's unique technical IBANs. */
    private int $ibanSeq = 0;

    private UserMensiliPeriodService $periods;

    /** @var array<int, array{anno: string, mese: string}> */
    private array $storicoMonths;

    /** @var array<int, array{anno: string, mese: string}> */
    private array $previsionaleMonths;

    public function handle(): int
    {
        if (! $this->guardEnvironment()) {
            return self::FAILURE;
        }

        $this->periods = new UserMensiliPeriodService;
        $this->storicoMonths = $this->periods->storicoMonths();
        $this->previsionaleMonths = $this->periods->previsionaleMonths();

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
            $this->resetDomainData();
            $this->seedRolesIfMissing();
            $this->seedBanche();
            // After the banks, not before: the JSON carries per-bank nodes and
            // translates.banca_abi points at banche. Run against an empty banche
            // the whole import rolls back on the foreign key, silently — which
            // is how it came out with zero rows every time.
            $this->importTranslations();
            $this->seedProdotti();
            $this->seedInterfacce();
            $this->seedInternalBackpackUsers();
            $this->seedBankStaff();
            $this->seedPlatformUsers();
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
    protected function postSeed(): void
    {
    }

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
     * Complete the customers already in the database, outside development.
     *
     * Anagrafiche, banks and products are real there and are left exactly as
     * they are. This only fills what stops a customer from being usable on the
     * platform: the storico and previsionale months they are missing, and — for
     * a tiraggio that has none — the proiezioni and the trattenute that follow.
     *
     * No scenario is invented: the customers are the scenarios. A second run
     * finds nothing left to add.
     */
    private function completaClienti(): int
    {
        $ruoloCliente = Role::query()->where('name', 'User')->value('id');

        if ($ruoloCliente === null) {
            $this->error('Nessun ruolo [User]: non so quali anagrafiche siano clienti.');

            return self::FAILURE;
        }

        // Customers only: PO, filiale and operators have no credito of their own.
        $clienti = User::query()
            ->where('role_id', $ruoloCliente)
            ->whereNotNull('banca_abi')
            ->select(['user_id', 'banca_abi']);

        if ($clienti->clone()->doesntExist()) {
            $this->components->warn('Nessun cliente da completare.');

            return self::SUCCESS;
        }

        $totali = ['clienti' => 0, 'storici' => 0, 'previsionali' => 0, 'tiraggi' => 0, 'delibere_fixate' => 0];

        // Bulk writes: per-row cache invalidation would dominate the runtime and
        // the caches are flushed at the end anyway.
        CacheInvalidationContext::suppress();

        try {
            // Real-data fixup: delibere imported from dumps may carry a null
            // max_atp_effettivo (the bank has not granted a figure yet). The
            // platform reads max_atp_effettivo ?? max_atp_consigliato as the
            // slider ceiling, so a null with no consigliato behind it breaks
            // management-analysis. Fill the gap once, up front: where effettivo
            // is empty, the consigliato is the best available answer.
            $delibereFixate = Delibera::query()
                ->whereNull('max_atp_effettivo')
                ->whereNotNull('max_atp_consigliato')
                ->update([
                    'max_atp_effettivo' => DB::raw('max_atp_consigliato'),
                ]);

            if ($delibereFixate > 0) {
                $this->components->info(sprintf(
                    '%d delibere con max_atp_effettivo vuoto allineate al consigliato.',
                    $delibereFixate,
                ));
            }

            $totali['delibere_fixate'] = $delibereFixate;

            // By chunk, and never all at once: a bank's customers and their
            // twelve months of storico do not fit in a command's memory.
            $clienti->chunkById(200, function ($blocco) use (&$totali): void {
                [$storici, $previsionali] = $this->completaMensili($blocco);

                $totali['clienti'] += $blocco->count();
                $totali['storici'] += $storici;
                $totali['previsionali'] += $previsionali;
                $totali['tiraggi'] += $this->completaCredito($blocco);
            }, 'user_id');
        } finally {
            CacheInvalidationContext::restore();
        }

        $this->call('cache:clear');

        $this->newLine();
        $this->components->info(sprintf(
            '%d clienti: +%d storici, +%d previsionali, %d tiraggi completati, %d delibere allineate.',
            $totali['clienti'],
            $totali['storici'],
            $totali['previsionali'],
            $totali['tiraggi'],
            $totali['delibere_fixate'],
        ));

        return self::SUCCESS;
    }

    /**
     * Add the storico and previsionale months a customer does not have yet.
     *
     * A month already on file is never touched: what the bank sent is the truth,
     * and this only closes the gaps around it. The forecast for a month equals
     * its turnover, so a completed customer starts out in linea rather than in a
     * delay nobody put there.
     *
     * @param  Collection<int, User>  $clienti
     * @return array{0: int, 1: int} [storici, previsionali]
     */
    private function completaMensili($clienti): array
    {
        $ids = $clienti->pluck('user_id');

        // One query each per chunk, not two per customer.
        $storicoPresente = Storico::query()
            ->whereIn('user_id', $ids)
            ->get(['user_id', 'anno', 'mese', 'importo_transato'])
            ->groupBy('user_id');

        $previsionalePresente = Previsionale::query()
            ->whereIn('user_id', $ids)
            ->get(['user_id', 'anno', 'mese'])
            ->groupBy('user_id')
            ->map(fn ($righe) => $righe->map(fn ($r) => $r->anno.'-'.$r->mese)->flip());

        $nuoviStorici = [];
        $nuoviPrevisionali = [];

        foreach ($clienti as $cliente) {
            $volumi = [];
            foreach ($storicoPresente[$cliente->user_id] ?? [] as $riga) {
                $volumi[$riga->anno.'-'.$riga->mese] = (float) $riga->importo_transato;
            }

            $medio = $volumi === []
                ? (float) $this->volumeMensile($cliente->user_id)
                : array_sum($volumi) / count($volumi);

            foreach ($this->storicoMonths as $mese) {
                $chiave = $mese['anno'].'-'.$mese['mese'];

                if (isset($volumi[$chiave])) {
                    continue;
                }

                $volumi[$chiave] = (float) $this->volumeMensile($cliente->user_id);

                $nuoviStorici[] = [
                    'user_id' => $cliente->user_id,
                    'banca_abi' => $cliente->banca_abi,
                    'anno' => $mese['anno'],
                    'mese' => $mese['mese'],
                    'importo_transato' => $volumi[$chiave],
                    'numero_transazioni' => mt_rand(120, 400),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $giaPrevisto = $previsionalePresente[$cliente->user_id] ?? collect();

            // Previsionali cover the storico months too: the forecast for a past
            // month is what the bank expected, which the charts read by index
            // alongside the trattenute.
            foreach (array_merge($this->storicoMonths, $this->previsionaleMonths) as $mese) {
                $chiave = $mese['anno'].'-'.$mese['mese'];

                if ($giaPrevisto->has($chiave)) {
                    continue;
                }

                $nuoviPrevisionali[] = [
                    'user_id' => $cliente->user_id,
                    'banca_abi' => $cliente->banca_abi,
                    'anno' => $mese['anno'],
                    'mese' => $mese['mese'],
                    'importo_transato_previsto' => (int) round($volumi[$chiave] ?? $medio),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($nuoviStorici, 500) as $blocco) {
            DB::table('storici')->insert($blocco);
        }
        foreach (array_chunk($nuoviPrevisionali, 500) as $blocco) {
            DB::table('previsionali')->insert($blocco);
        }

        return [count($nuoviStorici), count($nuoviPrevisionali)];
    }

    /**
     * Give the tiraggi that have no proiezioni theirs, and the trattenute that
     * follow from them.
     *
     * Only a tiraggio with no trattenute at all gets seeded ones: where the bank
     * has sent real withholdings, inventing more on top would corrupt them.
     *
     * @param  Collection<int, User>  $clienti
     */
    private function completaCredito($clienti): int
    {
        $tiraggi = Tiraggio::query()
            ->whereIn('user_id', $clienti->pluck('user_id'))
            ->whereIn('status', ['accepted', 'paid'])
            ->whereNotNull('data_delibera')
            ->where('importo_deliberato', '>', 0)
            ->whereDoesntHave('proiezioni')
            ->get();

        foreach ($tiraggi as $tiraggio) {
            $mesi = max((int) $tiraggio->mesi, 1);
            $trascorsi = (int) Carbon::parse($tiraggio->data_delibera)->diffInMonths(now()) + 1;
            $coperti = $tiraggio->status === 'paid' ? $mesi : max(1, min($mesi, $trascorsi));

            $this->seedProiezioni($tiraggio, $coperti);

            $haTrattenute = Trattenuta::where('tiraggio_numero_pratica', $tiraggio->numero_pratica)->exists();

            // seedTrattenute reads data_fine; a tiraggio without one has no
            // window to spread the withholdings over.
            if (! $haTrattenute && $tiraggio->data_fine !== null) {
                $this->seedTrattenute($tiraggio, $tiraggio->status === 'paid');
            }
        }

        return $tiraggi->count();
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

    // -------------------------------------------------------------------------
    // Reset
    // -------------------------------------------------------------------------

    private function resetDomainData(): void
    {
        $this->components->task('Clearing domain data', function (): void {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            // Children first, so the order still reads correctly if the checks
            // are ever put back on.
            foreach (['proiezioni', 'trattenute', 'tiraggi', 'delibere', 'storici', 'previsionali'] as $table) {
                DB::table($table)->truncate();
            }

            // Backpack operators are rebuilt below; keeping them would leave
            // stale rows behind whenever the internal list changes.
            DB::table('users')->delete();
            DB::table('filiali')->delete();

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        });
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
    // Banks, products, interfaces
    // -------------------------------------------------------------------------

    /**
     * Whether the bank sells durata-parametrica tiraggi — the merchant picks
     * the duration and the percentuale follows. SandboxSeed overrides this:
     * its fake banks are not bound to the real ABI list.
     */
    protected function haDurataParametrica(string $abi): bool
    {
        return in_array($abi, self::ABI_DURATA_PARAMETRICA, true);
    }

    private function seedBanche(): void
    {
        $this->components->task('Banks', function (): void {
            foreach ($this->banche() as $abi => $nome) {
                Banca::query()->updateOrCreate(['banca_abi' => $abi], [
                    'nome' => $nome,
                    'tiraggio_durata_parametrica' => $this->haDurataParametrica($abi),
                ]);
            }
        });
    }

    /**
     * Products differ from bank to bank on purpose — the platform has to be
     * exercised against a range of configurations — but every one of them
     * satisfies ProdottoRequest, notably max_percentuale <= max_atp_percentuale <= 70
     * and durata_max_delibera >= max_mesi_tiraggio.
     */
    private function seedProdotti(): void
    {
        $this->components->task('Products', function (): void {
            $index = 0;

            // Cast: '99999' is a numeric string, so PHP stores it as an int key
            // and the strict comparison below never matched — Humanbit ended up
            // with a product of its own. Every other ABI keeps its leading zero.
            foreach (array_map(strval(...), array_keys($this->banche())) as $abi) {
                if ($abi === self::INTERNAL_BANCA_ABI) {
                    continue;
                }

                $maxAtpPercentuale = Prodotto::MAX_ATP_PERCENTUALE_MAX;
                $minPercentuale = [10, 20, 30][$index % 3];
                $maxPercentuale = min([60, 70, 80][$index % 3], $maxAtpPercentuale);
                $maxMesi = [10, 12, 18][$index % 3];
                $multi = $index % 4 === 0;
                // Step and conguaglio only exist on durata parametrica banks; the
                // maggiorazione applies to every bank past 12 months.
                $durataParametrica = $this->haDurataParametrica($abi);
                $conguaglio = $durataParametrica && $index % 2 === 0;
                $minMesiSeeded = [3, 4, 6][$index % 3];
                $validSteps = Prodotto::validStepsFor($minMesiSeeded, $maxMesi);

                Prodotto::query()->updateOrCreate(['banca_abi' => $abi], [
                    'min_atp' => 1000,
                    'max_atp' => [50000, 120000, 250000][$index % 3],
                    'max_atp_percentuale' => $maxAtpPercentuale,
                    'max_tan' => [4.5, 5.5, 6.5][$index % 3],
                    'max_taeg' => [5.5, 6.5, 7.5][$index % 3],
                    'min_percentuale' => $minPercentuale,
                    'max_percentuale' => $maxPercentuale,
                    'min_mesi_tiraggio' => $minMesiSeeded,
                    'max_mesi_tiraggio' => $maxMesi,
                    'min_mesi_storico' => 12,
                    // Well below what the generated storico provides, so every
                    // seeded user clears the product target too.
                    'min_importo_storico' => [5000, 10000, 20000][$index % 3],
                    'min_transazioni_storico' => 50,
                    'multi_tiraggio' => $multi,
                    'durata_max_delibera' => $multi ? 18 : null,
                    'percentuale_ritardo_critico' => $multi ? 50 : null,
                    'step_mesi_tiraggio' => $durataParametrica ? $validSteps[$index % count($validSteps)] : null,
                    // Capped at 50 past 12 months, as ProdottoRequest enforces.
                    'percentuale_maggiorativa' => $maxMesi > 12 ? 50 : [20, 40, 60][$index % 3],
                    'conguaglio_periodico' => $conguaglio,
                    // Never above half of max_mesi_tiraggio, never below 2.
                    'frequenza_mesi_conguaglio' => $conguaglio ? max(2, intdiv($maxMesi, 2) - ($index % 2)) : null,
                    'metodo_conguaglio' => $conguaglio ? Prodotto::METODI_CONGUAGLIO[$index % 2] : null,
                    ...$this->prodottoOverrides()[$abi] ?? [],
                ]);

                $index++;
            }
        });
    }

    /**
     * The configurations the platform has to exercise on purpose, rather than
     * whatever the positional defaults above happen to produce.
     *
     * Keyed by ABI so adding a bank to banche() can never silently reshuffle
     * one of these three onto a different variant.
     *
     * Every entry respects ProdottoRequest: step_mesi_tiraggio divides both
     * min and max mesi, frequenza_mesi_conguaglio sits between 2 and half of
     * max_mesi_tiraggio, percentuale_maggiorativa stays at or below 50 once the
     * window passes 12 months, and durata_max_delibera covers max_mesi_tiraggio.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function prodottoOverrides(): array
    {
        return [
            // AideXa — conguaglio on the plafond method: importo_deliberato split
            // into equal instalments, so the amounts are flat.
            // 18 months on purpose, to exercise the 18-month proiezioni horizon.
            '03625' => [
                'min_mesi_tiraggio' => 6,
                'max_mesi_tiraggio' => 18,
                'step_mesi_tiraggio' => 6,
                'percentuale_maggiorativa' => 50,
                'multi_tiraggio' => false,
                'durata_max_delibera' => null,
                'percentuale_ritardo_critico' => null,
                'conguaglio_periodico' => true,
                'frequenza_mesi_conguaglio' => 3,
                'metodo_conguaglio' => 'plafond',
            ],

            // Endeka — conguaglio on the previsionale method: each settlement is
            // the forecast accumulated over its period, so the amounts vary.
            // 12 months, to keep the other side of the horizon step covered.
            self::FICTIONAL_BANCA_ABI => [
                'min_mesi_tiraggio' => 6,
                'max_mesi_tiraggio' => 12,
                'step_mesi_tiraggio' => 6,
                'percentuale_maggiorativa' => 40,
                'multi_tiraggio' => false,
                'durata_max_delibera' => null,
                'percentuale_ritardo_critico' => null,
                'conguaglio_periodico' => true,
                'frequenza_mesi_conguaglio' => 4,
                'metodo_conguaglio' => 'previsionale',
            ],

            // Credem — the multi-tiraggio reference. Explicitly not a conguaglio
            // bank: the two features have to be seen apart.
            '03032' => [
                'min_mesi_tiraggio' => 6,
                'max_mesi_tiraggio' => 12,
                'multi_tiraggio' => true,
                'durata_max_delibera' => 18,
                'percentuale_ritardo_critico' => 50,
                'conguaglio_periodico' => false,
                'frequenza_mesi_conguaglio' => null,
                'metodo_conguaglio' => null,
            ],

            // BPER — the multi-tiraggio + durata parametrica reference, without
            // conguagli. The only bank that stacks both features at once, so the
            // seeder exercises the combination the auth simulation advertises.
            // 18 months on purpose, to exercise the 18-month proiezioni horizon
            // and the percentuale_maggiorativa past 12 months.
            '05387' => [
                'min_mesi_tiraggio' => 6,
                'max_mesi_tiraggio' => 18,
                'step_mesi_tiraggio' => 6,
                'percentuale_maggiorativa' => 50,
                'multi_tiraggio' => true,
                'durata_max_delibera' => 18,
                'percentuale_ritardo_critico' => 50,
                'conguaglio_periodico' => false,
                'frequenza_mesi_conguaglio' => null,
                'metodo_conguaglio' => null,
            ],
        ];
    }

    /**
     * Interfaces keep the existing per-bank logos and colours, but the files are
     * written through MediaStorage so the paths follow the current layout
     * (uploads/logos/{abi}/…) instead of the legacy flat one.
     */
    private function seedInterfacce(): void
    {
        $this->components->task('Interfaces and logos', function (): void {
            $logos = $this->logoMap();

            // Driven by the banks, not by the logo list: a bank without a
            // bundled logo still needs its interfaccia, or the frontend has no
            // colours to theme it with.
            foreach (array_keys($this->platformBanche()) as $abi) {
                [$primary, $secondary] = $this->colorMap()[$abi] ?? ['#000000', '#ffffff'];
                $filename = $logos[$abi] ?? null;

                Interfaccia::query()->updateOrCreate(['banca_abi' => $abi], [
                    'logo_path' => $filename !== null ? $this->publishLogo($abi, $filename) : null,
                    'color_primary' => $primary,
                    'color_secondary' => $secondary,
                ]);
            }
        });
    }

    /**
     * Copy a bundled logo into the public media disk, under the same layout
     * MediaStorage writes: uploads/logos/{banca_abi}/{file}.
     */
    private function publishLogo(string $abi, string $filename): ?string
    {
        $source = public_path('static/images/logos/'.$filename);

        if (! File::isFile($source)) {
            return null;
        }

        $path = sprintf(
            '%s/logos/%s/%s.%s',
            MediaStorage::PUBLIC_PREFIX,
            $abi,
            $abi,
            pathinfo($filename, PATHINFO_EXTENSION),
        );

        Storage::disk(MediaStorage::publicDisk())->put($path, File::get($source), 'public');

        return $path;
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

            $password = config('backpack.base.backpack_admin_password') ?: self::SEED_PASSWORD;

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
                    ['user_id' => self::INTERNAL_BANCA_ABI.'-'.$ndg],
                    [
                        'banca_abi' => self::INTERNAL_BANCA_ABI,
                        'email' => $email,
                        'ragione_sociale' => ucfirst($ndg),
                        'backpack_role_id' => $backpackRole === 'Developer' ? $developerRoleId : $adminRoleId,
                        'role_id' => $userRoleId,
                        'password' => $password,
                        'email_verified_at' => now(),
                        'sospeso' => false,
                    ],
                );
            }
        });
    }

    /**
     * One Product Owner and three branch operators per bank, each attached to a
     * branch of that bank so the console scoping has something to filter on.
     */
    protected function seedBankStaff(): void
    {
        $banche = $this->platformBanche();

        $this->components->task('Product owners and branch operators', function () use ($banche): void {
            $productOwnerRoleId = Role::query()->where('name', 'Product Owner')->value('id');
            $filialeRoleId = Role::query()->where('name', 'Filiale')->value('id');

            foreach ($banche as $abi => $nome) {
                $filiali = [];

                for ($i = 1; $i <= 3; $i++) {
                    $filialeId = Filiale::composeFilialeId($abi, str_pad((string) $i, 3, '0', STR_PAD_LEFT));

                    Filiale::query()->updateOrCreate(['filiale_id' => $filialeId], [
                        'banca_abi' => $abi,
                        'descrizione' => sprintf('Filiale %d %s', $i, $nome),
                        'email' => sprintf('filiale%d.%s@seed.cashberry.it', $i, $abi),
                    ]);

                    $filiali[] = $filialeId;

                    User::query()->updateOrCreate(
                        ['user_id' => sprintf('FILIALE_%s_%d', $abi, $i)],
                        [
                            'banca_abi' => $abi,
                            'filiale_id' => $filialeId,
                            'role_id' => $filialeRoleId,
                            'ragione_sociale' => sprintf('Operatore filiale %d %s', $i, $nome),
                            'email' => sprintf('filiale%d.%s@seed.cashberry.it', $i, $abi),
                            // Predictable seed credentials: password matches the email local part.
                            // The 'hashed' cast routes through the derivation-aware hasher.
                            'password' => sprintf('filiale%d.%s', $i, $abi),
                        ],
                    );
                }

                User::query()->updateOrCreate(
                    ['user_id' => 'PO_'.$abi],
                    [
                        'banca_abi' => $abi,
                        'role_id' => $productOwnerRoleId,
                        'ragione_sociale' => 'Product Owner '.$nome,
                        'email' => 'po.'.$abi.'@seed.cashberry.it',
                        // Predictable seed credentials: password matches the email local part.
                        'password' => 'po.'.$abi,
                        'sospeso' => false,
                    ],
                );

                $this->filialiPerBanca[$abi] = $filiali;
            }
        });
    }

    /** @var array<string, list<string>> */
    protected array $filialiPerBanca = [];

    /**
     * Platform clients: eligible, not suspended, with a full storico and
     * previsionale window, then a tiraggio in a status assigned so that every
     * status is represented at least MIN_PER_STATUS times per bank.
     */
    private function seedPlatformUsers(): void
    {
        $perBanca = max((int) $this->option('users'), count(self::TIRAGGIO_STATUSES) * self::MIN_PER_STATUS);
        $banche = $this->platformBanche();

        $bar = $this->output->createProgressBar(count($banche));
        $bar->setFormat(' Platform users  %current%/%max% banks [%bar%] %message%');
        $bar->setMessage('');
        $bar->start();

        $userRoleId = Role::query()->where('name', 'User')->value('id');

        foreach ($banche as $abi => $nome) {
            $bar->setMessage($nome);

            DB::transaction(function () use ($abi, $perBanca, $userRoleId): void {
                $this->seedPlatformUsersForBanca($abi, $perBanca, $userRoleId);
                $this->seedErrorTargetsForBanca($abi, $userRoleId);
            });

            $bar->advance();
        }

        $bar->setMessage('done');
        $bar->finish();
        $this->newLine(2);
    }

    private function seedPlatformUsersForBanca(string $abi, int $perBanca, int $userRoleId): void
    {
        $prodotto = Prodotto::query()->where('banca_abi', $abi)->first();
        $filiali = $this->filialiPerBanca[$abi] ?? [];
        $statuses = $this->statusPlan($perBanca);

        $users = [];
        $storici = [];
        $previsionali = [];

        foreach (range(1, $perBanca) as $n) {
            $userId = sprintf('ABI%s_PC%06d', $abi, $n);

            $users[] = [
                'user_id' => $userId,
                'banca_abi' => $abi,
                'filiale_id' => $filiali[($n - 1) % max(count($filiali), 1)] ?? null,
                'role_id' => $userRoleId,
                'ragione_sociale' => sprintf('Azienda %s %03d', $abi, $n),
                'email' => sprintf('cliente%03d.%s@seed.cashberry.it', $n, $abi),
                'ateco' => '62.01.00',
                'descrizione_ateco' => 'Produzione di software',
                'provincia' => 'MI',
                'codice_postale' => '20100',
                'sospeso' => 0,
                'password' => Hash::make($userId),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Comfortably above every product threshold, so eligibility never
            // depends on which bank the user landed on.
            // The per-user forecast factor determines whether the user's real
            // turnover (storico) will be above, near, or below what the bank
            // expected (previsionale) — this is what drives the esito.
            $forecastFactor = [1.06, 0.92, 0.78][$n % 3];

            [$suoiStorici, $suoiPrevisionali] = $this->mensiliPerUtente($userId, $abi, $forecastFactor);
            $storici = [...$storici, ...$suoiStorici];
            $previsionali = [...$previsionali, ...$suoiPrevisionali];
        }

        foreach (array_chunk($users, 200) as $chunk) {
            DB::table('users')->insert($chunk);
        }
        foreach (array_chunk($storici, 500) as $chunk) {
            DB::table('storici')->insert($chunk);
        }
        foreach (array_chunk($previsionali, 500) as $chunk) {
            DB::table('previsionali')->insert($chunk);
        }

        foreach (range(1, $perBanca) as $n) {
            $this->seedCreditoForUser(
                sprintf('ABI%s_PC%06d', $abi, $n),
                $statuses[$n - 1],
                $prodotto,
                $n,
            );
        }

        // For multi_tiraggio banks, create dedicated users with 2-4 parallel
        // tiraggi that cover staggered repayment stages, delay scenarios and
        // plafond/percentuale exhaustion — each with full proiezioni and
        // trattenute, not ghost copies.
        if ($prodotto?->multi_tiraggio === true) {
            $this->seedMultiTiraggioScenarios($abi, $prodotto, $userRoleId);
        }
    }

    /**
     * Dedicated multi-tiraggio users with 2-4 parallel tiraggi covering every
     * scenario the platform has to handle: staggered repayment stages, delay
     * patterns, pending requests, and plafond/percentuale exhaustion.
     *
     * Each tiraggio gets its own proiezioni, trattenute and conguagli — the
     * opposite of the old ghost-copy approach where the second tiraggio had
     * no data at all.
     */
    private function seedMultiTiraggioScenarios(string $abi, Prodotto $prodotto, int $userRoleId): void
    {
        $filiali = $this->filialiPerBanca[$abi] ?? [];
        $now = Carbon::now();

        $productMaxAtp = (int) $prodotto->max_atp;
        $minMesi = (int) $prodotto->min_mesi_tiraggio;
        $maxMesi = (int) $prodotto->max_mesi_tiraggio;
        $durataMaxDelibera = (int) ($prodotto->durata_max_delibera ?? $maxMesi);
        $sogliaCritica = (int) ($prodotto->percentuale_ritardo_critico ?? 50);
        // Share of the forecast withheld: 5 points short of ritardo critico, and 10 past it.
        $sottoSoglia = (100 - $sogliaCritica + 5) / 100;
        $oltreSoglia = (100 - $sogliaCritica - 10) / 100;

        // Each scenario: percentuale, mesi, stage, andamento.
        // stage: 'almost_repaid' | 'midway' | 'just_started' | 'paid'
        // andamento: controls trattenute vs forecast/baseline ratio.
        // mesi_effettivi (paid only): how long the rimborso actually took, so
        // the esito sees something besides in_linea.
        $scenarios = [
            // MT-1: 2 accepted, first almost repaid, second just started.
            [
                ['pct' => 40, 'mesi' => $maxMesi, 'stage' => 'almost_repaid', 'andamento' => 1.05],
                ['pct' => 20, 'mesi' => $minMesi, 'stage' => 'just_started', 'andamento' => 1.0],
            ],
            // MT-2: 3 accepted, staggered stages.
            [
                ['pct' => 35, 'mesi' => $maxMesi, 'stage' => 'almost_repaid', 'andamento' => 1.02],
                ['pct' => 25, 'mesi' => (int) ceil($maxMesi / 2), 'stage' => 'midway', 'andamento' => 0.95],
                ['pct' => 15, 'mesi' => $minMesi, 'stage' => 'just_started', 'andamento' => 1.0],
            ],
            // MT-3: 4 tiraggi, first paid, then descending stages.
            [
                ['pct' => 30, 'mesi' => $maxMesi, 'stage' => 'paid', 'andamento' => 1.0, 'mesi_effettivi' => $maxMesi + 2],
                ['pct' => 25, 'mesi' => (int) ceil($maxMesi * 0.75), 'stage' => 'almost_repaid', 'andamento' => 1.0],
                ['pct' => 20, 'mesi' => (int) ceil($maxMesi / 2), 'stage' => 'midway', 'andamento' => 0.92],
                ['pct' => 10, 'mesi' => $minMesi, 'stage' => 'just_started', 'andamento' => 1.0],
            ],
            // MT-4: 2 accepted, first lieve_ritardo, second in_linea.
            [
                ['pct' => 40, 'mesi' => $maxMesi, 'stage' => 'midway', 'andamento' => 0.92],
                ['pct' => 20, 'mesi' => $minMesi, 'stage' => 'midway', 'andamento' => 1.05],
            ],
            // MT-5: 2 accepted, the second below its forecast by more than sogliaCritica → block.
            [
                ['pct' => 30, 'mesi' => $maxMesi, 'stage' => 'midway', 'andamento' => $sottoSoglia],
                ['pct' => 20, 'mesi' => $maxMesi, 'stage' => 'midway', 'andamento' => $oltreSoglia],
            ],
            // MT-6: 3 tiraggi, first paid, second late but within sogliaCritica, third in_linea → no block.
            [
                ['pct' => 30, 'mesi' => $maxMesi, 'stage' => 'paid', 'andamento' => 1.0, 'mesi_effettivi' => max(1, $maxMesi - 2)],
                ['pct' => 40, 'mesi' => $maxMesi, 'stage' => 'midway', 'andamento' => $sottoSoglia],
                ['pct' => 10, 'mesi' => $minMesi, 'stage' => 'just_started', 'andamento' => 1.05],
            ],
            // MT-7: 1 accepted + 1 requested (pending) → tests plafond with pending.
            [
                ['pct' => 40, 'mesi' => $maxMesi, 'stage' => 'midway', 'andamento' => 1.0],
                ['pct' => 20, 'mesi' => $minMesi, 'stage' => 'requested', 'andamento' => 1.0],
            ],
            // MT-8: 2 accepted, sum percentuali = 90 → atp_esaurito / percentuale_esaurita.
            [
                ['pct' => 50, 'mesi' => $maxMesi, 'stage' => 'almost_repaid', 'andamento' => 1.0],
                ['pct' => 40, 'mesi' => $maxMesi, 'stage' => 'midway', 'andamento' => 0.95],
            ],
        ];

        foreach ($scenarios as $idx => $tiraggiSpec) {
            $mtNum = $idx + 1;
            $userId = sprintf('ABI%s_MT%02d', $abi, $mtNum);

            // Create the user with full storico and previsionale.
            User::query()->create([
                'user_id' => $userId,
                'banca_abi' => $abi,
                'filiale_id' => $filiali[$mtNum % max(count($filiali), 1)] ?? null,
                'role_id' => $userRoleId,
                'ragione_sociale' => sprintf('Azienda %s MT %02d', $abi, $mtNum),
                'email' => sprintf('mt%02d.%s@seed.cashberry.it', $mtNum, $abi),
                'ateco' => '62.01.00',
                'descrizione_ateco' => 'Produzione di software',
                'provincia' => 'MI',
                'codice_postale' => '20100',
                'sospeso' => 0,
                'password' => Hash::make($userId),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Neutral previsionali (factor 1, so previsionale = storico): here the
            // per-tiraggio andamento in the scenario specs controls the esito.
            [$mtStorici, $mtPrevisionali] = $this->mensiliPerUtente($userId, $abi);
            DB::table('storici')->insert($mtStorici);
            DB::table('previsionali')->insert($mtPrevisionali);

            // Coherence: delibera max_atp derives from the user's storico
            // volumes, same as seedCreditoForUser, so each tiraggio's forecast
            // spreads across its full duration.
            $avgVolume = array_sum(array_column($mtStorici, 'importo_transato')) / max(count($mtStorici), 1);

            $maxPct = (int) $prodotto->max_percentuale;
            $volumeBasedMax = (int) round($avgVolume * $maxPct / 100 * $maxMesi);
            $maxAtp = min($volumeBasedMax, $productMaxAtp);

            // One delibera shared by all tiraggi of this user, and open: a
            // multi-tiraggio user is one who can still ask for another.
            $delibera = Delibera::query()->create([
                'user_id' => $userId,
                'status' => 'open',
                'max_atp_consigliato' => $maxAtp,
                'max_atp_effettivo' => $maxAtp,
                'tan' => $prodotto->max_tan,
                'taeg' => $prodotto->max_taeg,
                'iban_tecnico' => sprintf('IT60X0542811101000000%04d', 9000 + $mtNum),
            ]);

            // Each tiraggio's importo derives from its own pct × mesi × volume,
            // so the forecast covers the full duration. The sum is capped at
            // max_atp_effettivo by scaling down proportionally if needed.
            $plafondPerTiraggio = [];
            $rawTotal = 0;
            foreach ($tiraggiSpec as $spec) {
                $raw = (int) round($avgVolume * $spec['pct'] / 100 * max($spec['mesi'], $minMesi));
                $plafondPerTiraggio[] = $raw;
                $rawTotal += $raw;
            }
            if ($rawTotal > $maxAtp) {
                $scale = $maxAtp / $rawTotal;
                foreach ($plafondPerTiraggio as $i => $val) {
                    $plafondPerTiraggio[$i] = (int) round($val * $scale);
                }
                // Fix rounding: adjust last element so sum matches maxAtp.
                $scaledSum = array_sum($plafondPerTiraggio);
                if ($scaledSum > $maxAtp) {
                    $plafondPerTiraggio[count($plafondPerTiraggio) - 1] -= ($scaledSum - $maxAtp);
                }
            }

            // The withholding percentages all come out of the same POS takings,
            // so their sum across the committed tiraggi is capped — the merchant
            // has to keep something of their own income. A product whose
            // min_percentuale is high therefore cannot host many tiraggi at once:
            // at 30% the budget holds three, not four.
            $minPct = (int) $prodotto->min_percentuale;
            $budgetPct = $prodotto->percentualeMassimaCombinata();
            $maxImpegnati = max(1, intdiv($budgetPct, max($minPct, 1)));

            $impegnati = array_keys(array_filter(
                $tiraggiSpec,
                static fn (array $spec): bool => $spec['stage'] !== 'paid',
            ));

            // Drop the surplus from the end: a repaid tiraggio withholds nothing
            // any more, so only the committed ones are counted against the budget.
            foreach (array_slice($impegnati, $maxImpegnati) as $key) {
                unset($tiraggiSpec[$key], $plafondPerTiraggio[$key]);
            }

            $tiraggiSpec = array_values($tiraggiSpec);
            $plafondPerTiraggio = array_values($plafondPerTiraggio);
            $rimastiDaImpegnare = count(array_filter(
                $tiraggiSpec,
                static fn (array $spec): bool => $spec['stage'] !== 'paid',
            ));

            // First accepted tiraggio sets the delibera window start.
            $deliberaStartDate = null;
            $pctResidua = $budgetPct;

            foreach ($tiraggiSpec as $tIdx => $spec) {
                $status = $spec['stage'] === 'paid' ? 'paid'
                    : ($spec['stage'] === 'requested' ? 'requested' : 'accepted');

                $importoDeliberato = $plafondPerTiraggio[$tIdx];

                // Here the duration is the scenario, not a free variable: with
                // stage it decides whether this is a long tiraggio nearly repaid
                // or a short one just started, so deriving it from the amount
                // would flatten every scenario into the same shape. Pinning both
                // bounds to the target keeps it, and lets the percentuale absorb
                // the difference — which also clamps the spec splits that were
                // landing below min_percentuale on the narrower products.
                $mesiTarget = min(max((int) $spec['mesi'], $minMesi), $maxMesi);

                // What this tiraggio may take without starving the ones after it:
                // whatever is left of the budget, minus a minimum each for those
                // still to come. Clamping each percentage into the product range
                // on its own was not enough — three tiraggi held at a 30% floor
                // summed to 95%, over a budget of 90.
                $tettoPct = $status === 'paid'
                    ? $maxPct
                    : max($minPct, $pctResidua - $minPct * max($rimastiDaImpegnare - 1, 0));

                [$percentualeTiraggio, $mesi] = $this->allineaDurata(
                    $avgVolume,
                    $importoDeliberato,
                    (int) $spec['pct'],
                    $minPct,
                    min($maxPct, $tettoPct),
                    $mesiTarget,
                    $mesiTarget,
                );

                if ($status !== 'paid') {
                    $pctResidua -= $percentualeTiraggio;
                    $rimastiDaImpegnare--;
                }

                $importoRichiesto = $this->importoRichiestoPer($importoDeliberato, 1.1, $maxAtp);

                // Build timeline based on stage.
                [$dataRichiesta, $dataDelibera, $mesiTrascorsi, $dataFineReale] = $this->multiTiraggioTimeline(
                    $spec['stage'],
                    $mesi,
                    $tIdx,
                    $deliberaStartDate,
                    $spec['mesi_effettivi'] ?? null,
                );

                // Track the first accepted tiraggio's delibera date for the window.
                if ($deliberaStartDate === null && $dataDelibera !== null) {
                    $deliberaStartDate = $dataDelibera->copy();
                }

                $dataFine = $dataFineReale ?? $dataDelibera?->copy()->addMonths($mesi);

                // The bank assigns the numero pratica when it deliberates:
                // a request still waiting for an answer has none yet.
                [$numeroPratica, $ibanTecnico] = $dataDelibera !== null
                    ? $this->praticaEIban(substr($userId, 3, 5), sprintf('PRAT-%s-MT%d-%02d', substr($userId, 3, 5), $mtNum, $tIdx + 1))
                    : [null, null];

                $tiraggio = Tiraggio::query()->create([
                    'user_id' => $userId,
                    'delibera_id' => $delibera->delibera_id,
                    'numero_pratica' => $numeroPratica,
                    'iban_tecnico' => $ibanTecnico,
                    'status' => $status,
                    'importo_richiesto' => $importoRichiesto,
                    'importo_deliberato' => $dataDelibera !== null ? $importoDeliberato : 0,
                    'percentuale' => $percentualeTiraggio,
                    'mesi' => $mesi,
                    'data_richiesta' => $dataRichiesta,
                    'data_delibera' => $dataDelibera,
                    'data_fine' => $dataFine,
                    'tan' => $delibera->tan,
                    'taeg' => $delibera->taeg,
                    'trattenuta_totale' => 0,
                    'contratto_copia_conforme' => 1,
                ]);

                // Skip proiezioni/trattenute for non-deliberated tiraggi.
                if ($dataDelibera === null) {
                    continue;
                }

                // Paid tiraggio: fully repaid.
                // Accepted: partial repayment based on stage. The per-tiraggio
                // andamento scales the trattenute vs forecast to produce the
                // expected esito for each scenario.
                $mesiCoperti = $status === 'paid' ? $mesi : $mesiTrascorsi;

                $this->seedProiezioni($tiraggio, $mesiCoperti);
                $this->seedTrattenute($tiraggio, $status === 'paid', $spec['andamento']);
                $this->settleConguagli($tiraggio, $status);
                $this->seedCostoCredito($tiraggio, $mesiCoperti);
            }

            $hasRequested = in_array('requested', array_column($tiraggiSpec, 'stage'), true);

            if (! $hasRequested) {
                Tiraggio::query()->create([
                    'user_id' => $userId,
                    'delibera_id' => $delibera->delibera_id,
                    'status' => 'open',
                    'tan' => $delibera->tan,
                    'taeg' => $delibera->taeg,
                ]);
            }
        }
    }

    /**
     * Timeline for a multi-tiraggio scenario tiraggio.
     *
     * Staggered so the first tiraggio started earliest and subsequent ones
     * later, all within the delibera window. $deliberaStartDate is the first
     * accepted tiraggio's data_delibera, used to keep later tiraggi inside the
     * durata_max_delibera window.
     *
     * The fourth element is the actual end date — set only for 'paid', where
     * it becomes the stored data_fine.
     *
     * @return array{0: Carbon, 1: ?Carbon, 2: int, 3: ?Carbon}
     */
    private function multiTiraggioTimeline(string $stage, int $mesi, int $tIdx, ?Carbon $deliberaStartDate, ?int $mesiEffettivi = null): array
    {
        $now = Carbon::now();

        if ($stage === 'requested') {
            return [$now->copy()->subDays(2 + $tIdx * 3), null, 0, null];
        }

        if ($stage === 'paid') {
            // data_fine is the actual end: mesi_effettivi lets a spec place it
            // before, on, or past the plan so the esito is not always in_linea.
            $dataFine = $now->copy()->subMonths(1 + $tIdx);
            $dataDelibera = $dataFine->copy()->subMonths($mesiEffettivi ?? $mesi);

            return [$dataDelibera->copy()->subDays(3), $dataDelibera, $mesi, $dataFine];
        }

        // accepted: stagger by index so tiraggi are at different stages.
        $offsets = [
            'almost_repaid' => max(1, $mesi - 1),
            'midway' => (int) ceil($mesi / 2),
            'just_started' => 1,
        ];

        $mesiTrascorsi = $offsets[$stage] ?? 1;

        // The stage sets how long ago this tiraggio started; the index separates
        // the ones that share a stage. Without it two 'midway' tiraggi of the
        // same length came out on the very same data_delibera, which no merchant
        // would ever produce — nobody opens two credit lines on one day.
        //
        // Later index means more recent, matching the order the specs are
        // written in (most advanced first), and it is clamped so the newest one
        // still sits safely in the past.
        $dataDelibera = $now->copy()->subMonths($mesiTrascorsi)->addDays($tIdx * 9);

        if ($dataDelibera->greaterThan($limite = $now->copy()->subDays(3))) {
            $dataDelibera = $limite;
        }

        // If we have a delibera window start, ensure this tiraggio's repayment
        // end stays within durata_max_delibera from the first tiraggio.
        // (The caller already ensures mesi fits, this is a safety check.)

        return [$dataDelibera->copy()->subDays(2 + $tIdx * 2), $dataDelibera, $mesiTrascorsi, null];
    }

    /**
     * Create a few users per bank that trigger the error scenarios:
     *
     * - error_not_eleggibile: suspended users — they pass technical checks
     *   but the platform blocks them at the eligibility gate.
     * - error_incomplete_data: eligible users with no tiraggio at all,
     *   so the platform has no data to show an offer.
     *
     * error_delibera_closed is already covered by the paid/rejected/dropped
     * users from the regular seeding.
     */
    private function seedErrorTargetsForBanca(string $abi, int $userRoleId): void
    {
        $filiali = $this->filialiPerBanca[$abi] ?? [];
        $now = now();

        // Two suspended users for error_not_eleggibile.
        foreach (range(1, 2) as $n) {
            $userId = sprintf('ABI%s_ERR%02d', $abi, $n);

            User::query()->create([
                'user_id' => $userId,
                'banca_abi' => $abi,
                'filiale_id' => $filiali[($n - 1) % max(count($filiali), 1)] ?? null,
                'role_id' => $userRoleId,
                'ragione_sociale' => sprintf('Azienda %s ERR %02d', $abi, $n),
                'email' => sprintf('errore%02d.%s@seed.cashberry.it', $n, $abi),
                'ateco' => '62.01.00',
                'descrizione_ateco' => 'Produzione di software',
                'provincia' => 'MI',
                'codice_postale' => '20100',
                'sospeso' => 1,
                'password' => Hash::make($userId),
                'email_verified_at' => $now,
            ]);

            foreach ($this->storicoMonths as $month) {
                DB::table('storici')->insert([
                    'user_id' => $userId,
                    'banca_abi' => $abi,
                    'anno' => $month['anno'],
                    'mese' => $month['mese'],
                    'importo_transato' => $this->volumeMensile($userId),
                    'numero_transazioni' => mt_rand(120, 400),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Previsionali cover the storico months too, not only the future
            // ones: a tiraggio deliberated in the past needs an expectation for
            // the months it has already run, or its forecast derives from
            // nothing. Same rule as every other seeded user — there used to be
            // two populations, 31 months and 19.
            foreach (array_merge($this->storicoMonths, $this->previsionaleMonths) as $month) {
                DB::table('previsionali')->insert([
                    'user_id' => $userId,
                    'banca_abi' => $abi,
                    'anno' => $month['anno'],
                    'mese' => $month['mese'],
                    'importo_transato_previsto' => $this->volumeMensile($userId),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Two eligible users with no tiraggio for error_incomplete_data.
        // The Delibera model boot enforces max_atp >= 5000, so we cannot
        // create a delibera with empty ATP values. Instead, users with
        // no tiraggio at all match the filter ($tiraggio === null).
        foreach (range(3, 4) as $n) {
            $userId = sprintf('ABI%s_ERR%02d', $abi, $n);

            User::query()->create([
                'user_id' => $userId,
                'banca_abi' => $abi,
                'filiale_id' => $filiali[($n - 1) % max(count($filiali), 1)] ?? null,
                'role_id' => $userRoleId,
                'ragione_sociale' => sprintf('Azienda %s ERR %02d', $abi, $n),
                'email' => sprintf('errore%02d.%s@seed.cashberry.it', $n, $abi),
                'ateco' => '62.01.00',
                'descrizione_ateco' => 'Produzione di software',
                'provincia' => 'MI',
                'codice_postale' => '20100',
                'sospeso' => 0,
                'password' => Hash::make($userId),
                'email_verified_at' => $now,
            ]);

            foreach ($this->storicoMonths as $month) {
                DB::table('storici')->insert([
                    'user_id' => $userId,
                    'banca_abi' => $abi,
                    'anno' => $month['anno'],
                    'mese' => $month['mese'],
                    'importo_transato' => $this->volumeMensile($userId),
                    'numero_transazioni' => mt_rand(120, 400),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Previsionali cover the storico months too, not only the future
            // ones: a tiraggio deliberated in the past needs an expectation for
            // the months it has already run, or its forecast derives from
            // nothing. Same rule as every other seeded user — there used to be
            // two populations, 31 months and 19.
            foreach (array_merge($this->storicoMonths, $this->previsionaleMonths) as $month) {
                DB::table('previsionali')->insert([
                    'user_id' => $userId,
                    'banca_abi' => $abi,
                    'anno' => $month['anno'],
                    'mese' => $month['mese'],
                    'importo_transato_previsto' => $this->volumeMensile($userId),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Status for each user: every status gets MIN_PER_STATUS slots first, the
     * remainder goes to open — the state a user needs to be able to ask for
     * credit, and the one most screens are exercised from.
     *
     * @return list<string>
     */
    private function statusPlan(int $total): array
    {
        $plan = [];

        foreach (self::TIRAGGIO_STATUSES as $status) {
            $plan = [...$plan, ...array_fill(0, self::MIN_PER_STATUS, $status)];
        }

        while (count($plan) < $total) {
            $plan[] = 'open';
        }

        return array_slice($plan, 0, $total);
    }

    /**
     * Delibera plus tiraggio for one user, with a timeline that holds together:
     * an accepted tiraggio is still being repaid (data_fine in the future, part
     * of the amount withheld so far), a paid one is finished (data_fine in the
     * past, fully withheld), and the ones that never got deliberated carry no
     * delibera date, no trattenute and no proiezioni at all.
     */
    private function seedCreditoForUser(string $userId, string $status, ?Prodotto $prodotto, int $seq): void
    {
        $minMesi = (int) ($prodotto?->min_mesi_tiraggio ?? 3);
        $maxMesi = (int) ($prodotto?->max_mesi_tiraggio ?? 12);
        $mesi = $minMesi + ($seq % max($maxMesi - $minMesi + 1, 1));

        $minPercentuale = (int) ($prodotto?->min_percentuale ?? 10);
        $maxPercentuale = (int) ($prodotto?->max_percentuale ?? 70);
        $percentuale = $minPercentuale + ($seq % max($maxPercentuale - $minPercentuale + 1, 1));

        // Coherence: the delibera and tiraggio amounts derive from the user's
        // actual storico volumes, so the forecast spreads naturally across all
        // mesi instead of hitting the cap in the first few.
        $avgVolume = (float) Storico::where('user_id', $userId)->avg('importo_transato');
        if ($avgVolume <= 0) {
            $avgVolume = 50000.0;
        }

        $delibera = $this->createDelibera($userId, $status, $prodotto, $seq, $avgVolume);

        if ($status === 'open') {
            Tiraggio::query()->create([
                'user_id' => $userId,
                'delibera_id' => $delibera->delibera_id,
                'status' => 'open',
                'tan' => $delibera->tan,
                'taeg' => $delibera->taeg,
            ]);

            return;
        }

        // importo_deliberato = avg_volume × percentuale × mesi, capped at the
        // plafond the validation allows for these mesi (maggiorated past 12).
        $cap = TiraggioLimits::plafondPerMesi(
            (int) $delibera->max_atp_effettivo,
            $mesi,
            $prodotto?->percentuale_maggiorativa,
        );
        $importoDeliberato = (int) round($avgVolume * $percentuale / 100 * $mesi);
        $importoDeliberato = min($importoDeliberato, $cap);
        $importoDeliberato = max($importoDeliberato, (int) ($prodotto?->min_atp ?? 1000));

        // The cap above may have cut the amount well below what percentuale and
        // mesi described. Realign the three so the tiraggio's own numbers agree
        // with the plan its proiezioni will draw.
        // A maggiorated importo is only valid past 12 months: keep the duration there.
        $minMesiAllineati = $importoDeliberato > (int) $delibera->max_atp_effettivo ? max($minMesi, 13) : $minMesi;

        [$percentuale, $mesi] = $this->allineaDurata(
            $avgVolume,
            $importoDeliberato,
            $percentuale,
            $minPercentuale,
            $maxPercentuale,
            $minMesiAllineati,
            $maxMesi,
        );

        // The request is slightly above the granted amount — banks do not
        // always grant the full request.
        $importoRichiesto = $this->importoRichiestoPer(
            $importoDeliberato,
            [1.1, 1.2, 1.3][$seq % 3],
            (int) $delibera->max_atp_effettivo,
        );

        [$dataRichiesta, $dataDelibera, $mesiTrascorsi, $dataFineEffettiva] = $this->timelineFor($status, $mesi, $seq, $maxMesi);

        $isDeliberated = $dataDelibera !== null;
        $importoDeliberato = $isDeliberated ? $importoDeliberato : 0;

        // The bank assigns the numero pratica at delibera: requested,
        // rejected and dropped tiraggi never got one.
        [$numeroPratica, $ibanTecnico] = $isDeliberated
            ? $this->praticaEIban(substr($userId, 3, 5), sprintf('PRAT-%s-%06d', substr($userId, 3, 5), $seq))
            : [null, null];

        $tiraggio = Tiraggio::query()->create([
            'user_id' => $userId,
            'delibera_id' => $delibera->delibera_id,
            'numero_pratica' => $numeroPratica,
            'iban_tecnico' => $ibanTecnico,
            'status' => $status,
            'importo_richiesto' => $importoRichiesto,
            'importo_deliberato' => $importoDeliberato,
            'percentuale' => $percentuale,
            'mesi' => $mesi,
            'data_richiesta' => $dataRichiesta,
            'data_delibera' => $dataDelibera,
            // Paid tiraggi carry the actual end, so their esito reflects how
            // long the rimborso really took; the rest keep the planned one.
            'data_fine' => $dataFineEffettiva ?? $dataDelibera?->copy()->addMonths($mesi),
            'tan' => $delibera->tan,
            'taeg' => $delibera->taeg,
            'trattenuta_totale' => 0,
            'contratto_copia_conforme' => 1,
        ]);

        if (! $isDeliberated) {
            return;
        }

        // Trattenute use real storico volumes (what actually happened), while
        // the forecast uses previsionali (what the bank expected). The natural
        // difference between the two drives the esito — no artificial andamento.
        $mesiCoperti = $status === 'paid' ? $mesi : $mesiTrascorsi;

        // Every fourth conguaglio tiraggio was requested under a cadence the product
        // has since moved away from: its periods must follow its own, not the product's.
        $frequenzaProdotto = (int) ($prodotto?->frequenza_mesi_conguaglio ?? 0);
        if ($prodotto?->conguaglio_periodico && $frequenzaProdotto > 0 && $seq % 4 === 1 && $mesi >= 3) {
            $tiraggio->update(['frequenza_mesi_conguaglio' => $frequenzaProdotto === 2 ? 3 : 2]);
        }

        $this->seedProiezioni($tiraggio, $mesiCoperti);
        $this->seedTrattenute($tiraggio, $status === 'paid');
        $this->settleConguagli($tiraggio, $status);
        $this->seedCostoCredito($tiraggio, $mesiCoperti);

        // A user always holds one open delibera: the load that closed this one
        // opened the next, as the console and the eleggibili import do.
        if ($delibera->status === 'close') {
            $delibera->replicate()->fill(['status' => 'open'])->save();
        }
    }

    /**
     * Fill in quota_interessi and costo_totale the way the perfeziona flow does.
     *
     * Seeded tiraggi never go through TiraggioManagementOperation, which is the
     * only writer of these columns — so without this every seeded tiraggio has
     * them NULL and anything reading the cost of the credit finds nothing.
     */
    private function seedCostoCredito(Tiraggio $tiraggio, int $mesiCoperti): void
    {
        $deliberato = (float) $tiraggio->importo_deliberato;
        $mesi = max($mesiCoperti, 1);
        $quotaMensile = $deliberato / $mesi;

        // Residual owed at the start of each month, the input the formula wants.
        $residuo = $deliberato;
        $quotaResidua = [];

        for ($i = 0; $i < $mesi; $i++) {
            $quotaResidua[] = $residuo;
            $residuo = max(0.0, $residuo - $quotaMensile);
        }

        $tiraggio->update(Tiraggio::costoCredito($quotaResidua, $deliberato, (float) $tiraggio->tan));
    }

    /**
     * Monthly POS turnover bands, one per merchant size.
     *
     * A single 40k-90k band gave every merchant the same shape, and at those
     * volumes any admissible percentuale repays a whole plafond in a month or
     * two: the mesi written on the tiraggio had nothing to do with the plan the
     * proiezioni described. Four bands spread the population the way a real
     * book is spread, so the product ceiling binds on the large merchants only.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    private const PROFILI_VOLUME = [
        'micro' => [2500, 9000],
        'piccolo' => [9000, 25000],
        'medio' => [25000, 60000],
        'grande' => [60000, 120000],
    ];

    /**
     * Weighted toward the smaller sizes, as a real merchant book is.
     *
     * @var list<string>
     */
    private const PESI_PROFILO = ['micro', 'micro', 'piccolo', 'piccolo', 'piccolo', 'medio', 'medio', 'grande'];

    /**
     * The merchant's size. Deterministic in the user id, so re-running app:init
     * gives the same merchant the same profile and a bug stays reproducible.
     */
    private function profiloVolume(string $userId): string
    {
        return self::PESI_PROFILO[crc32($userId) % count(self::PESI_PROFILO)];
    }

    /**
     * The storico and previsionale rows for one merchant.
     *
     * On the past months the forecast is the turnover divided by
     * $forecastFactor: above 1 the merchant is taking more than the bank
     * expected, below 1 less, and that difference is what drives the esito. A
     * factor of 1 is neutral, which is what the multi-tiraggio scenarios want —
     * there the per-tiraggio andamento decides the outcome instead.
     *
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>} [storici, previsionali]
     */
    private function mensiliPerUtente(string $userId, string $abi, float $forecastFactor = 1.0): array
    {
        $storici = [];
        $previsionali = [];
        $now = now();

        foreach ($this->storicoMonths as $month) {
            $volume = $this->volumeMensile($userId);

            $storici[] = [
                'user_id' => $userId,
                'banca_abi' => $abi,
                'anno' => $month['anno'],
                'mese' => $month['mese'],
                'importo_transato' => $volume,
                'numero_transazioni' => mt_rand(120, 400),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Previsionali cover the storico months too: the forecast for a past
            // month has to be what the bank expected, not what happened.
            $previsionali[] = [
                'user_id' => $userId,
                'banca_abi' => $abi,
                'anno' => $month['anno'],
                'mese' => $month['mese'],
                'importo_transato_previsto' => (int) round($volume / $forecastFactor),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($this->previsionaleMonths as $month) {
            $previsionali[] = [
                'user_id' => $userId,
                'banca_abi' => $abi,
                'anno' => $month['anno'],
                'mese' => $month['mese'],
                'importo_transato_previsto' => $this->volumeMensile($userId),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return [$storici, $previsionali];
    }

    /**
     * One month of turnover for this merchant, within their own band.
     */
    private function volumeMensile(string $userId): int
    {
        [$min, $max] = self::PROFILI_VOLUME[$this->profiloVolume($userId)];

        return mt_rand($min, $max);
    }

    /**
     * What the merchant asked for: a little above what was granted, but as the
     * platform slider allows it, on its step (TiraggioPatch::getSliderStep) and
     * never past its ceiling, the delibera ATP or the maggiorated grant itself.
     */
    private function importoRichiestoPer(int $deliberato, float $margine, int $maxAtp): int
    {
        $step = $maxAtp < 10000 ? 100 : ($maxAtp <= 50000 ? 500 : 1000);
        $tetto = max($deliberato, $maxAtp);

        return (int) max(
            min(ceil($deliberato * $margine / $step), floor($tetto / $step)) * $step,
            ceil($deliberato / $step) * $step,
        );
    }

    /**
     * Make importo, percentuale and mesi agree with the merchant's turnover.
     *
     * The three are not independent: withholding `percentuale` of `volume` a
     * month repays `importo` in importo / (volume x percentuale) months. Drawing
     * all three separately produced tiraggi whose stored duration contradicted
     * their own proiezioni — six months on paper, closed by the forecast in one.
     *
     * mesi is the derived one. If the derivation lands outside what the product
     * allows, the duration is held at the boundary and percentuale absorbs the
     * difference, because percentuale is the parameter the product lets the
     * merchant move.
     *
     * @return array{0: int, 1: int} [percentuale, mesi]
     */
    private function allineaDurata(
        float $volume,
        int $importo,
        int $percentuale,
        int $minPercentuale,
        int $maxPercentuale,
        int $minMesi,
        int $maxMesi,
    ): array {
        $percentuale = min(max($percentuale, $minPercentuale), $maxPercentuale);

        if ($volume <= 0 || $importo <= 0 || $percentuale <= 0) {
            return [$percentuale, $minMesi];
        }

        $mesi = (int) max(1, round($importo / ($volume * $percentuale / 100)));

        if ($mesi >= $minMesi && $mesi <= $maxMesi) {
            return [$percentuale, $mesi];
        }

        $mesi = min(max($mesi, $minMesi), $maxMesi);
        $percentuale = (int) round($importo / ($volume * $mesi) * 100);

        return [min(max($percentuale, $minPercentuale), $maxPercentuale), $mesi];
    }

    /**
     * The credit offer behind the tiraggio.
     *
     * max_atp_effettivo is what the bank actually granted: sometimes it matches
     * the recommended figure, sometimes it is cut down — both happen in real
     * data and the platform has to render either.
     */
    private function createDelibera(string $userId, string $status, ?Prodotto $prodotto, int $seq, float $avgVolume = 50000): Delibera
    {
        $productMaxAtp = (int) ($prodotto?->max_atp ?? 50000);
        $maxPct = (int) ($prodotto?->max_percentuale ?? 70);
        $maxMesi = (int) ($prodotto?->max_mesi_tiraggio ?? 12);

        // Coherence: max_atp derives from the user's volumes so the delibera
        // can actually cover a full-duration tiraggio at a meaningful percentuale.
        $volumeBasedMax = (int) round($avgVolume * $maxPct / 100 * $maxMesi);
        $maxAtp = min($volumeBasedMax, $productMaxAtp);
        $consigliato = (int) round($maxAtp * [1.0, 0.8, 0.6, 0.45][$seq % 4]);

        // One in three keeps the recommended amount; the others are granted less.
        $effettivo = $seq % 3 === 0
            ? $consigliato
            : (int) round($consigliato * [0.9, 0.75, 0.6][$seq % 3]);

        return Delibera::query()->create([
            'user_id' => $userId,
            // A delibera closes only when a new one is loaded over it, and that
            // needs a tiraggio already accepted — accepting one does not close
            // it by itself. Paid is the state where a later load has plausibly
            // happened; seedCreditoForUser() opens the next one.
            'status' => $status === 'paid' ? 'close' : 'open',
            'max_atp_consigliato' => $consigliato,
            'max_atp_effettivo' => $effettivo,
            'tan' => $prodotto?->max_tan ?? 5.0,
            'taeg' => $prodotto?->max_taeg ?? 6.0,
            'iban_tecnico' => sprintf('IT60X0542811101000000%06d', $seq),
        ]);
    }

    /**
     * numero_pratica and iban_tecnico of a deliberated tiraggio: Sparkasse
     * (06045) pratiche ARE the technical IBAN — the dealId its acquirer sends
     * back IS the IBAN — so numero_pratica = iban_tecnico there, while every
     * other bank keeps its own PRAT- code and an empty iban_tecnico.
     *
     * @return array{0: string, 1: ?string}
     */
    private function praticaEIban(string $bancaAbi, string $numeroPratica): array
    {
        if ($bancaAbi !== self::ABI_SPARKASSE) {
            return [$numeroPratica, null];
        }

        // ABI in the check block; the running sequence keeps the UNIQUE
        // tiraggi.iban_tecnico constraint happy across users and tiraggi.
        $iban = sprintf('IT60X0604511101%012d', ++$this->ibanSeq);

        return [$iban, $iban];
    }

    /**
     * Request date, delibera date and elapsed months for a status, plus — for
     * a paid tiraggio — the actual end date, which is what data_fine stores
     * once the rimborso is over (for the others it stays the planned end).
     *
     * @return array{0: Carbon, 1: ?Carbon, 2: int, 3: ?Carbon}
     */
    private function timelineFor(string $status, int $mesi, int $seq, int $maxMesi): array
    {
        $now = Carbon::now();

        if ($status === 'requested') {
            // Waiting for the bank: asked for a few days ago, not decided yet.
            return [$now->copy()->subDays(1 + $seq % 20), null, 0, null];
        }

        if (in_array($status, ['rejected', 'dropped'], true)) {
            // Turned down or abandoned: it never reached a delibera date.
            return [$now->copy()->subMonths(1 + $seq % 6)->subDays($seq % 15), null, 0, null];
        }

        if ($status === 'paid') {
            // Finished: repayment ended between one and four months ago. The
            // actual duration varies around the plan — a paid tiraggio whose
            // data_fine always lands on delibera + mesi can only ever read
            // in_linea, and the esito buckets would stay forever green.
            $dataFine = $now->copy()->subMonths(1 + $seq % 4);
            $mesiEffettivi = match ($seq % 5) {
                1 => max(1, $mesi - 2),              // closed early
                2 => min($mesi + 2, $maxMesi),       // late, inside the cap
                3 => $maxMesi + 2,                   // past the cap
                default => $mesi,                    // on time
            };
            $dataDelibera = $dataFine->copy()->subMonths($mesiEffettivi);

            return [$dataDelibera->copy()->subDays(2 + $seq % 8), $dataDelibera, $mesi, $dataFine];
        }

        // accepted: still running, so somewhere between one month in and one
        // month short of the end.
        $mesiTrascorsi = 1 + ($seq % max($mesi - 1, 1));
        $dataDelibera = $now->copy()->subMonths($mesiTrascorsi);

        return [$dataDelibera->copy()->subDays(2 + $seq % 8), $dataDelibera, $mesiTrascorsi, null];
    }

    /**
     * Daily trattenute across the repayment window that has actually elapsed —
     * from the delibera date to today, or to data_fine once the tiraggio is
     * over. Weekends carry no card business, and the rows add up exactly to the
     * tiraggio's trattenuta_totale.
     */
    private function seedTrattenute(Tiraggio $tiraggio, bool $fullyPaid, float $andamento = 1.0): void
    {
        $start = Carbon::parse($tiraggio->data_delibera);
        $end = Carbon::parse($tiraggio->data_fine)->min(Carbon::now());
        $percentuale = max((int) $tiraggio->percentuale, 1);
        $importoDeliberato = (float) $tiraggio->importo_deliberato;

        // How far the withholdings may go. A tiraggio still running owes
        // something by definition, so only a paid one reaches the whole amount.
        $tetto = $fullyPaid
            ? $importoDeliberato
            : round($importoDeliberato * (1 - self::RESIDUO_TIRAGGIO_APERTO), 2);

        // Build a per-month target from actual storico volumes × percentuale,
        // so trattenute follow the real business curve. For paid tiraggi the
        // target is the full importo_deliberato. The optional andamento scales
        // the targets for MT scenarios where per-tiraggio control is needed.
        $monthlyTargets = $this->monthlyWithholdingTargets(
            $tiraggio,
            $start,
            $percentuale,
            $tetto,
            $fullyPaid,
            $andamento,
        );

        $rows = [];
        $trattenutaTotale = 0.0;

        foreach ($monthlyTargets as $monthKey => $target) {
            if ($target <= 0 || $trattenutaTotale >= $tetto) {
                continue;
            }

            [$year, $month] = explode('-', $monthKey);
            $monthStart = Carbon::create((int) $year, (int) $month, 1);
            $monthEnd = $monthStart->copy()->endOfMonth()->min($end);

            // Working days in this month within the tiraggio window.
            $giorni = [];
            for ($date = $monthStart->copy()->max($start); $date->lessThanOrEqualTo($monthEnd); $date->addDay()) {
                if (! $date->isWeekend()) {
                    $giorni[] = $date->copy();
                }
            }

            if ($giorni === []) {
                continue;
            }

            $perDay = $target / count($giorni);
            $residuoMese = min($target, $tetto - $trattenutaTotale);

            foreach ($giorni as $i => $date) {
                $isLast = $i === count($giorni) - 1;
                $importoTrattenuto = $isLast
                    ? $residuoMese
                    : min(round($perDay * mt_rand(70, 130) / 100, 2), $residuoMese);

                if ($importoTrattenuto <= 0) {
                    continue;
                }

                $residuoMese = round($residuoMese - $importoTrattenuto, 2);
                $trattenutaTotale = round($trattenutaTotale + $importoTrattenuto, 2);

                $rows[] = [
                    'user_id' => $tiraggio->user_id,
                    'tiraggio_numero_pratica' => $tiraggio->numero_pratica,
                    'data_contabile' => $date->toDateString(),
                    'importo_transato' => round($importoTrattenuto * 100 / $percentuale, 2),
                    'importo_trattenuto' => $importoTrattenuto,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('trattenute')->insert($chunk);
        }

        $tiraggio->update(['trattenuta_totale' => $trattenutaTotale]);
    }

    /**
     * Per-month withholding targets derived from actual storico volumes.
     *
     * Coverage follows the repayment window itself — the delibera month
     * through the month holding today, or data_fine once the tiraggio is
     * over — so the in-progress month gets a target too.
     *
     * For each covered month the withholding is
     *   storico.importo_transato × percentuale / 100,
     * prorated for the first partial month and the in-progress one, capped at
     * the residuo. The storico window excludes the current month by
     * definition, so its previsionale volume stands in.
     *
     * For paid tiraggi the total is topped up to importo_deliberato.
     *
     * @return array<string, float> Key is "YYYY-MM".
     */
    private function monthlyWithholdingTargets(
        Tiraggio $tiraggio,
        Carbon $start,
        int $percentuale,
        float $tetto,
        bool $fullyPaid,
        float $andamento = 1.0,
    ): array {
        $targets = [];
        $remainingDays = PianoRientroCalculator::giorniResiduiPrimoMese($start);
        $now = Carbon::now();
        $coverUntil = Carbon::parse($tiraggio->data_fine)->min($now)->copy()->startOfMonth();
        $currentMonth = $now->copy()->startOfMonth();

        // Fetch storico rows for the tiraggio window in one query.
        $storici = Storico::where('user_id', $tiraggio->user_id)
            ->orderByRaw('STR_TO_DATE(CONCAT(anno, "-", LPAD(mese, 2, "0"), "-01"), "%Y-%m-%d")')
            ->get()
            ->keyBy(fn ($s) => $s->anno.'-'.$s->mese);

        $previsionali = Previsionale::where('user_id', $tiraggio->user_id)
            ->get()
            ->keyBy(fn ($p) => $p->anno.'-'.$p->mese);

        $forecastTotale = 0.0;

        for ($i = 0; ; $i++) {
            $month = $start->copy()->startOfMonth()->addMonths($i);

            if ($month->gt($coverUntil)) {
                break;
            }

            $key = $month->year.'-'.$month->month;

            $volume = isset($storici[$key]) ? (float) $storici[$key]->importo_transato : 0.0;
            if ($volume <= 0 && $month->equalTo($currentMonth) && isset($previsionali[$key])) {
                $volume = (float) $previsionali[$key]->importo_transato_previsto;
            }
            if ($volume <= 0) {
                continue;
            }

            $monthly = ($volume * $percentuale) / 100;

            if ($month->equalTo($currentMonth)) {
                // The in-progress month is prorated by elapsed days — and by
                // the days after the delibera when the two coincide.
                $monthly = ($monthly / $now->daysInMonth) * ($i === 0 ? max($now->day - $start->day, 0) : $now->day);
            } elseif ($i === 0 && $remainingDays < $start->daysInMonth) {
                // Prorata the first month by remaining days, like InsertProiezioni.
                $monthly = ($monthly / $start->daysInMonth) * $remainingDays;
            }

            // Cap at what is left under the ceiling: the deliberated amount for
            // a paid tiraggio, slightly less for one still running.
            $monthly = min($monthly, $tetto - $forecastTotale);
            if ($monthly <= 0) {
                break;
            }

            $forecastTotale += $monthly;

            // Apply andamento for MT scenarios; PC users have andamento=1.0
            // (the esito emerges from the storico/previsionale difference).
            $targets[$key] = $fullyPaid ? $monthly : round($monthly * $andamento, 2);
        }

        // For paid tiraggi, make sure the total reaches importo_deliberato.
        if ($fullyPaid && $forecastTotale < $tetto && ! empty($targets)) {
            $lastKey = array_key_last($targets);
            $targets[$lastKey] = round($targets[$lastKey] + ($tetto - $forecastTotale), 2);
        }

        return $targets;
    }

    /**
     * Monthly forecast and baseline over the months the tiraggio has run.
     *
     * Tiraggio::getEsitoAttribute compares trattenuta_totale against the totals
     * of these two series, so covering only the elapsed months is what makes
     * "on track / late / seriously late" mean anything before the end.
     */
    private function seedProiezioni(Tiraggio $tiraggio, int $mesiCoperti): void
    {
        $dataDelibera = Carbon::parse($tiraggio->data_delibera);
        $start = $dataDelibera->copy()->startOfMonth();
        $remainingDays = PianoRientroCalculator::giorniResiduiPrimoMese($dataDelibera);
        $percentuale = (float) $tiraggio->percentuale;
        $importoDeliberato = (float) $tiraggio->importo_deliberato;

        $prodotto = $tiraggio->getProdotto();
        $durataParametrica = $tiraggio->haDurataParametrica();
        $isConguaglio = $prodotto && $prodotto->conguaglio_periodico && $tiraggio->frequenzaConguaglio() > 0;
        $periodo = $isConguaglio ? $tiraggio->frequenzaConguaglio() : 0;

        // Fixed on the tiraggio, as the perfeziona flows do.
        if ($isConguaglio && $tiraggio->frequenza_mesi_conguaglio === null) {
            $tiraggio->update(['frequenza_mesi_conguaglio' => $periodo]);
        }
        $metodoConguaglio = $prodotto?->metodo_conguaglio;
        $horizon = $tiraggio->proiezioniHorizon();
        $durataMesi = (int) $tiraggio->mesi;
        $conguaglioPlafond = $isConguaglio && $metodoConguaglio === 'plafond'
            ? ConguaglioSchedule::importoPlafond($importoDeliberato, $durataMesi, $periodo)
            : 0.0;

        // Forecast uses previsionali (what the bank expected), not storici
        // (what actually happened). Previsionali now cover the same months as
        // storici, so the forecast for past months reflects the expected
        // turnover, and the difference with the actual trattenute drives the
        // esito naturally.
        $previsionali = Previsionale::where('user_id', $tiraggio->user_id)
            ->orderByRaw('STR_TO_DATE(CONCAT(anno, "-", LPAD(mese, 2, "0"), "-01"), "%Y-%m-%d")')
            ->get()
            ->keyBy(fn ($p) => $p->anno.'-'.$p->mese);

        $rows = [];
        $forecastTotale = 0.0;
        $baselineTotale = 0.0;
        $conguaglioAccumulatore = 0.0;
        $conguaglioTotale = 0.0;

        $totalMonths = max($mesiCoperti, $horizon, 1);

        for ($i = 0; $i < $totalMonths; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->year.'-'.$month->month;

            // Always use previsionale volumes for forecast.
            $volume = isset($previsionali[$key])
                ? (float) $previsionali[$key]->importo_transato_previsto
                : 0.0;

            // Baseline: none on durata parametrica with conguaglio, otherwise volume × an implicit
            // 85% of the percentuale (the "safe" scenario), capped at the
            // same residuo as the forecast so baseline_total ≈ 0.85 × importo.
            $baseline = 0.0;
            if (! ($durataParametrica && $isConguaglio) && $volume > 0) {
                $baselineResiduo = $importoDeliberato - $baselineTotale;
                if ($baselineResiduo > 0) {
                    $baseline = ($volume * $percentuale * 0.85) / 100;
                    if ($i === 0 && $remainingDays < $dataDelibera->daysInMonth) {
                        $baseline = ($baseline / $dataDelibera->daysInMonth) * $remainingDays;
                    }
                    $baseline = min($baseline, $baselineResiduo);
                    $baselineTotale += $baseline;
                }
            }

            // Forecast: volume × percentuale / 100, capped at residuo.
            $importoResiduo = $importoDeliberato - $forecastTotale;
            $forecast = 0.0;
            $conguaglio = 0.0;

            if ($importoResiduo > 0 && $volume > 0) {
                $forecast = ($volume * $percentuale) / 100;

                if ($i === 0 && $remainingDays < $dataDelibera->daysInMonth) {
                    $forecast = ($forecast / $dataDelibera->daysInMonth) * $remainingDays;
                }

                $forecast = min($forecast, $importoResiduo);

                // Conguaglio handling: same logic as InsertProiezioni.
                if ($isConguaglio) {
                    if ($durataParametrica) {
                        // Durata parametrica + conguaglio: forecast stays
                        // monthly, conguaglio rides on top at period boundaries.
                        $conguaglioAccumulatore += $forecast;

                        if (ConguaglioSchedule::chiudePeriodo($i, $durataMesi, $periodo)) {
                            $conguaglio = $metodoConguaglio === 'plafond'
                                ? $conguaglioPlafond
                                : $conguaglioAccumulatore;
                            $conguaglio = max(0, min($conguaglio, $importoDeliberato - $conguaglioTotale));
                            $conguaglioTotale += $conguaglio;
                            $conguaglioAccumulatore = 0.0;
                        }
                    } else {
                        // Non-durata-parametrica + conguaglio: forecast is
                        // concentrated at period boundaries.
                        $conguaglioAccumulatore += $forecast;

                        if (ConguaglioSchedule::chiudePeriodo($i, $durataMesi, $periodo)) {
                            $forecast = $metodoConguaglio === 'plafond'
                                ? min($conguaglioPlafond, $importoResiduo)
                                : min($conguaglioAccumulatore, $importoResiduo);
                            $conguaglio = $forecast;
                            $conguaglioAccumulatore = 0.0;
                        } else {
                            $forecast = 0.0;
                        }
                    }
                }

                $forecastTotale += $forecast;
            }

            $rows[] = [
                'user_id' => $tiraggio->user_id,
                'tiraggio_numero_pratica' => $tiraggio->numero_pratica,
                'anno' => (string) $month->year,
                'mese' => (string) $month->month,
                'forecast' => round($forecast, 2),
                'baseline' => round($baseline, 2),
                'conguaglio' => round($conguaglio, 2),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('proiezioni')->insert($rows);
    }

    /**
     * Mark some conguagli as actually settled, by inserting dedicated
     * trattenute rows — same shape as estinzioneParziale at runtime.
     *
     * Each settled conguaglio is a new Trattenuta with:
     *   importo_transato  = -2  (sentinel: a conguaglio settlement, the same value
     *                            estinzioneParziale writes at runtime. -1 is the
     *                            other kind of non-POS movement, an early full
     *                            repayment.)
     *   importo_trattenuto = 0   (no daily withholding on a settlement row)
     *   importo_versato   = the conguaglio amount from the proiezione
     *   data_versamento   = end of the period month
     *
     * A fully paid tiraggio settles everything; the others leave the most recent
     * one open, so conguaglio_proposto is non-zero and the esito shows a delay
     * somewhere instead of every user reading in_linea.
     */
    private function settleConguagli(Tiraggio $tiraggio, string $status): void
    {
        $previsti = $tiraggio->getConguagliPrevisti()
            ->filter(fn (Proiezione $p) => Carbon::create((int) $p->anno, (int) $p->mese, 1)->endOfMonth() <= now());

        if ($previsti->isEmpty()) {
            return;
        }

        $daSaldare = $status === 'paid' ? $previsti : $previsti->slice(0, -1);

        foreach ($daSaldare as $proiezione) {
            $mese = Carbon::create((int) $proiezione->anno, (int) $proiezione->mese, 1);

            // Daily trattenute skip weekends, so use the next Saturday after
            // end-of-month as data_contabile to avoid the unique constraint.
            $dataContabile = $mese->copy()->endOfMonth();
            while (! $dataContabile->isSaturday()) {
                $dataContabile->addDay();
            }

            Trattenuta::query()->create([
                'user_id' => $tiraggio->user_id,
                'tiraggio_numero_pratica' => $tiraggio->numero_pratica,
                'data_contabile' => $dataContabile->toDateString(),
                'data_versamento' => $mese->copy()->endOfMonth()->toDateString(),
                'importo_transato' => -2,
                'importo_trattenuto' => 0,
                'importo_versato' => (float) $proiezione->conguaglio,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Reference data
    // -------------------------------------------------------------------------

    /** @return array<string, string> */
    protected function banche(): array
    {
        return [
            '01005' => 'Banca Nazionale del Lavoro',
            '01015' => 'Banco di Sardegna',
            '01030' => 'Banca Monte dei Paschi di Siena',
            '02008' => 'UniCredit',
            '03032' => 'Credito Emiliano',
            '03069' => 'Intesa Sanpaolo',
            '03084' => 'Banca Cesare Ponti',
            '03104' => 'Deutsche Bank',
            '03140' => 'MPS Banca Personale',
            '03268' => 'Banca Sella',
            '03440' => 'Banco di Desio e della Brianza',
            '03625' => 'Banca AideXa',
            self::FICTIONAL_BANCA_ABI => 'Endeka Banca',
            '05034' => 'Banco BPM',
            '05385' => 'Banca Popolare di Puglia e Basilicata',
            '05387' => 'BPER Banca',
            '05424' => 'Bdm Banca',
            '05696' => 'Banca Popolare di Sondrio',
            '05856' => 'Banca Popolare dell\'Alto Adige',
            '06085' => 'Cassa di Risparmio di Asti',
            '06230' => 'Crédit Agricole Italia',
            '19275' => 'Compass Banca',
            // Appended, not sorted in: seedProdotti() walks this list by index,
            // so inserting mid-list would reshuffle every later bank's product.
            self::ABI_SPARKASSE => 'Sparkasse - Cassa di Risparmio di Bolzano',
            self::INTERNAL_BANCA_ABI => 'Humanbit',
        ];
    }

    /** @return array<string, string> */
    protected function platformBanche(): array
    {
        return array_filter(
            $this->banche(),
            static fn (string $abi): bool => $abi !== self::INTERNAL_BANCA_ABI,
            ARRAY_FILTER_USE_KEY
        );
    }

    /** @return array<string, string> */
    private function logoMap(): array
    {
        return [
            '03440' => '30-banco-di-desio-della-brianza.gif',
            '03032' => '63-credito-emiliano.gif',
            '06085' => '36-cassa-di-risparmio-di-asti.gif',
            '05387' => '35-banca-popolare-dell-emilia-romagna.gif',
            '02008' => '34-unicredit.gif',
            '06230' => '23-credit-agricole-italia.gif',
            '03069' => '58-intesa-sanpaolo.gif',
            '05034' => '75-banco-popolare.gif',
            '01030' => '20-banca-monte-dei-paschi-di-siena.gif',
            '01005' => '85-banca-nazionale-del-lavoro.gif',
            '05696' => '92-banca-popolare-di-sondrio.gif',
            '03268' => '30-banca-sella.gif',
            '01015' => '54-banco-di-sardegna.gif',
            '05424' => '77-banca-popolare-di-bari.gif',
            '03104' => '59-deutsche-bank.gif',
            '19275' => '25-compass-banca.gif',
            '05856' => '83-banca-popolare-dell-alto-adige.gif',
            '03084' => '21-banca-cesare-ponti.gif',
            '05385' => '24-banca-popolare-di-puglia-basilicata.gif',
        ];
    }

    /** @return array<string, array{0: string, 1: string}> */
    private function colorMap(): array
    {
        return [
            '03440' => ['#c41e3a', '#ffffff'],
            // Approximations of the AideXa palette; Endeka is invented outright.
            '03625' => ['#6b2fbe', '#ffffff'],
            self::FICTIONAL_BANCA_ABI => ['#0f6f6c', '#ffffff'],
            '03032' => ['#114A07', '#ffffff'],
            '06085' => ['#8b0000', '#ffffff'],
            '05387' => ['#015B62', '#ffffff'],
            '02008' => ['#e2001a', '#ffffff'],
            '06230' => ['#008f4c', '#ffffff'],
            '03069' => ['#008652', '#ffffff'],
            '05034' => ['#007863', '#ffffff'],
            '01030' => ['#c41e3a', '#ffffff'],
            '01005' => ['#008552', '#ffffff'],
            '05696' => ['#00a3b9', '#ffffff'],
            '03268' => ['#008652', '#ffffff'],
            '01015' => ['#00512c', '#ffffff'],
            '05424' => ['#8b0000', '#ffffff'],
            '03104' => ['#0018a8', '#ffffff'],
            '19275' => ['#e31837', '#ffffff'],
            '05856' => ['#0066b3', '#ffffff'],
            '03084' => ['#1a3d6e', '#ffffff'],
            '05385' => ['#0E2339', '#ffffff'],
        ];
    }

    protected function summary(): void
    {
        $statusCounts = Tiraggio::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $this->components->twoColumnDetail('<fg=green>Banks</>', (string) Banca::query()->count());
        $this->components->twoColumnDetail('<fg=green>Products</>', (string) Prodotto::query()->count());
        $this->components->twoColumnDetail('<fg=green>Interfaces</>', (string) Interfaccia::query()->count());
        $this->components->twoColumnDetail('<fg=green>Branches</>', (string) Filiale::query()->count());
        $this->components->twoColumnDetail('<fg=green>Users</>', (string) User::query()->count());
        $this->components->twoColumnDetail('<fg=green>Storici / Previsionali</>', Storico::query()->count().' / '.Previsionale::query()->count());
        $this->components->twoColumnDetail('<fg=green>Trattenute / Proiezioni</>', Trattenuta::query()->count().' / '.Proiezione::query()->count());

        $this->newLine();
        $this->components->twoColumnDetail('<options=bold>Tiraggi per status</>', '');
        foreach (self::TIRAGGIO_STATUSES as $status) {
            $this->components->twoColumnDetail('  '.$status, (string) ($statusCounts[$status] ?? 0));
        }

        // Multi-tiraggio users: count and tiraggi-per-user distribution.
        $mtUsers = User::query()
            ->whereHas('banca.prodotto', fn ($q) => $q->where('multi_tiraggio', true))
            ->where('user_id', 'LIKE', '%_MT%')
            ->get();

        if ($mtUsers->isNotEmpty()) {
            $this->newLine();
            $this->components->twoColumnDetail('<options=bold>Multi-tiraggio users</>', (string) $mtUsers->count());

            $tCountDistribution = [];
            foreach ($mtUsers as $user) {
                $count = Tiraggio::query()->where('user_id', $user->user_id)->count();
                $tCountDistribution[$count] = ($tCountDistribution[$count] ?? 0) + 1;
            }
            ksort($tCountDistribution);
            foreach ($tCountDistribution as $count => $users) {
                $this->components->twoColumnDetail('  '.$count.' tiraggi', (string) $users.' users');
            }

            // Esito distribution across accepted/paid tiraggi of MT users.
            $mtTiraggi = Tiraggio::query()
                ->whereIn('user_id', $mtUsers->pluck('user_id'))
                ->whereIn('status', ['accepted', 'paid'])
                ->with('proiezioni')
                ->get();

            $esitoCounts = ['in_linea' => 0, 'lieve_ritardo' => 0, 'grave_ritardo' => 0];
            foreach ($mtTiraggi as $tiraggio) {
                $esito = $tiraggio->esito;
                if (isset($esitoCounts[$esito])) {
                    $esitoCounts[$esito]++;
                }
            }
            $this->components->twoColumnDetail('<options=bold>MT esito distribution</>', '');
            foreach ($esitoCounts as $esito => $count) {
                $this->components->twoColumnDetail('  '.$esito, (string) $count);
            }

            // Blocked users: check newTiraggioBlockReason for each MT user.
            $blockedReasons = [];
            foreach ($mtUsers as $user) {
                $reason = $user->newTiraggioBlockReason();
                if ($reason !== null) {
                    $blockedReasons[$reason] = ($blockedReasons[$reason] ?? 0) + 1;
                }
            }
            $this->components->twoColumnDetail('<options=bold>MT blocked from new requests</>', '');
            if (empty($blockedReasons)) {
                $this->components->twoColumnDetail('  (none)', '0');
            } else {
                foreach ($blockedReasons as $reason => $count) {
                    $this->components->twoColumnDetail('  '.$reason, (string) $count);
                }
            }
        }

        $cadenzaPropria = Tiraggio::query()
            ->join('users', 'users.user_id', '=', 'tiraggi.user_id')
            ->join('prodotti', 'prodotti.banca_abi', '=', 'users.banca_abi')
            ->whereColumn('tiraggi.frequenza_mesi_conguaglio', '!=', 'prodotti.frequenza_mesi_conguaglio')
            ->count();
        $this->components->twoColumnDetail('Conguaglio cadence differing from the product', (string) $cadenzaPropria);

        // Coherence violation checks.
        $violations = 0;

        // Check: sum of percentuale per user's committed tiraggi <= the product's combined cap.
        $pctSums = Tiraggio::query()
            ->whereIn('status', Tiraggio::COMMITTED_STATUSES)
            ->join('users', 'users.user_id', '=', 'tiraggi.user_id')
            ->join('prodotti', 'prodotti.banca_abi', '=', 'users.banca_abi')
            ->selectRaw('tiraggi.user_id, SUM(tiraggi.percentuale) as total_pct')
            ->groupBy('tiraggi.user_id', 'prodotti.percentuale_massima_combinata')
            ->havingRaw('total_pct > LEAST(COALESCE(prodotti.percentuale_massima_combinata, ?), ?)', [
                Prodotto::MULTI_TIRAGGIO_PERCENTUALE_MAX,
                Prodotto::MULTI_TIRAGGIO_PERCENTUALE_MAX,
            ])
            ->get();
        $violations += $pctSums->count();

        // Check: sum of importo_deliberato per delibera <= max_atp_effettivo.
        $importoSums = Tiraggio::query()
            ->whereNotNull('importo_deliberato')
            ->where('importo_deliberato', '>', 0)
            ->selectRaw('delibera_id, SUM(importo_deliberato) as total_importo')
            ->groupBy('delibera_id')
            ->havingRaw('total_importo > (SELECT max_atp_effettivo FROM delibere WHERE delibere.delibera_id = tiraggi.delibera_id)')
            ->get()
            // A single tiraggio past 12 months may use the maggiorated plafond.
            ->reject(function ($row): bool {
                $tiraggi = Tiraggio::with('delibera', 'user.banca.prodotto')
                    ->where('delibera_id', $row->delibera_id)
                    ->where('importo_deliberato', '>', 0)
                    ->get();

                if ($tiraggi->count() !== 1) {
                    return false;
                }

                $tiraggio = $tiraggi->first();

                return (int) $row->total_importo <= TiraggioLimits::plafondPerMesi(
                    (int) $tiraggio->delibera->max_atp_effettivo,
                    (int) $tiraggio->mesi,
                    $tiraggio->user?->banca?->prodotto?->percentuale_maggiorativa,
                );
            });
        $violations += $importoSums->count();

        $this->newLine();
        if ($violations > 0) {
            $this->components->twoColumnDetail('<fg=red>Coherence violations</>', (string) $violations);
            foreach ($pctSums as $row) {
                $this->components->twoColumnDetail('  percentuale sum > combined cap', $row->user_id);
            }
            foreach ($importoSums as $row) {
                $this->components->twoColumnDetail('  importo sum > plafond', 'delibera '.$row->delibera_id);
            }
        } else {
            $this->components->twoColumnDetail('<fg=green>Coherence violations</>', '0');
        }

        $this->newLine();
        $this->components->info('Backpack: borreca@humanbit.com and the other @humanbit.com accounts, password from BACKPACK_ADMIN_PASSWORD.');
        $this->components->info('Console: po.{abi}@seed.cashberry.it / filiale{n}.{abi}@seed.cashberry.it — password = email local part (e.g. po.01005).');
        $this->components->info('Platform: cliente{nnn}.{abi}@seed.cashberry.it / mt{nn}.{abi}@seed.cashberry.it — password = user_id (SAML/OIDC only, no console access).');
    }
}
