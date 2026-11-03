<?php

namespace App\Console\Commands;

use App\Models\Filiale;
use App\Models\Prodotto;
use App\Models\Proiezione;
use App\Models\Role;
use App\Models\Tiraggio;
use App\Models\User;
use App\Services\Sandbox\SandboxContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The sandbox variant of app:init.
 *
 * Same seeding machinery, different target: the sandbox schema, reachable
 * only through the /sandbox/* routes. The dataset is the demo set the API
 * documentation exercises — one fake bank per product shape declared in
 * config('sandbox.banks'), with clients covering every tiraggio status so
 * each documented endpoint has something to act on.
 *
 * Always rebuilds: the data is fake, deterministic and date-relative, so a
 * fresh build is the only trustworthy state — and migrate:fresh keeps the
 * schema in step with the migrations for free.
 *
 * Runs on the admin connection: migrate:fresh needs DDL, and the limited
 * app user exists to protect the per-request rollback, not the seeder.
 */
class SandboxSeed extends AppInit
{
    protected $signature = 'sandbox:seed
                            {--users=40    : Platform users generated for each sandbox bank}
                            {--force       : Skip the confirmation prompt}
                            {--fresh-dump  : Reload the schema and reference data from the dump first}
                            {--keep-schema : Align the existing schema instead of rebuilding it from scratch}';

    protected $description = 'Rebuild the sandbox demo dataset (fake banks) inside the sandbox schema';

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
     * The sandbox always rebuilds: the "complete existing customers" path is
     * for real data, and there is none here.
     */
    protected function ricostruisce(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    protected function banche(): array
    {
        return collect(config('sandbox.banks'))
            ->mapWithKeys(fn (array $bank, string $abi): array => [$abi => $bank['nome']])
            ->all();
    }

    /**
     * The fake banks are not bound to the real ABI list — the durata
     * parametrica flag is declared per bank in config('sandbox.banks').
     */
    protected function haDurataParametrica(string $abi): bool
    {
        return (bool) (config("sandbox.banks.{$abi}.durata_parametrica") ?? false);
    }

    /**
     * One product shape per bank, overriding whatever the positional rotation
     * above produces — the docs tabs promise an exact configuration each:
     *
     * - 66666: durata parametrica single tiraggio on conguaglio plafond —
     *   AideXa-like, with the maggiorativa cap (50 past 12 months) and a step
     *   dividing both mesi bounds, as ProdottoRequest requires;
     * - 33333: traditional single tiraggio — no durata parametrica, no
     *   conguaglio, no multi-tiraggio (nothing else to demo).
     *
     * Every field pair respects ProdottoRequest: the config must be one the
     * console could actually save.
     */
    protected function prodottoOverrides(): array
    {
        return [
            '66666' => [
                'min_mesi_tiraggio' => 6,
                'max_mesi_tiraggio' => 18,
                'multi_tiraggio' => false,
                'durata_max_delibera' => null,
                'percentuale_ritardo_critico' => null,
                'step_mesi_tiraggio' => 6,
                'percentuale_maggiorativa' => 50,
                'conguaglio_periodico' => true,
                'frequenza_mesi_conguaglio' => 3,
                'metodo_conguaglio' => 'plafond',
            ],
            '33333' => [
                'multi_tiraggio' => false,
                'durata_max_delibera' => null,
                'percentuale_ritardo_critico' => null,
                'step_mesi_tiraggio' => null,
                'percentuale_maggiorativa' => null,
                'conguaglio_periodico' => false,
                'frequenza_mesi_conguaglio' => null,
                'metodo_conguaglio' => null,
            ],
        ];
    }

    /**
     * The sandbox is not a Backpack environment: no operator accounts.
     */
    protected function seedInternalBackpackUsers(): void
    {
    }

    /**
     * Bank staff is reduced to what the sandbox needs: the Product Owner every
     * sandbox token is minted on — one per bank, since tokens act on their own
     * bank's data only. Branches still exist: the seeded clients attach to them.
     */
    protected function seedBankStaff(): void
    {
        $productOwnerRoleId = Role::query()->where('name', 'Product Owner')->value('id');

        $this->components->task('Branches and Product Owners', function () use ($productOwnerRoleId): void {
            foreach (config('sandbox.banks') as $abi => $bank) {
                $filiali = [];

                for ($i = 1; $i <= 3; $i++) {
                    $filialeId = Filiale::composeFilialeId($abi, str_pad((string) $i, 3, '0', STR_PAD_LEFT));

                    Filiale::query()->updateOrCreate(['filiale_id' => $filialeId], [
                        'banca_abi' => $abi,
                        'descrizione' => sprintf('Filiale %d %s', $i, $bank['nome']),
                        'email' => sprintf('filiale%d.%s@seed.cashberry.it', $i, $abi),
                    ]);

                    $filiali[] = $filialeId;
                }

                User::query()->updateOrCreate(
                    ['user_id' => $bank['po']],
                    [
                        'banca_abi' => $abi,
                        'role_id' => $productOwnerRoleId,
                        'ragione_sociale' => 'Product Owner '.$bank['nome'],
                        'email' => sprintf('po.%s@seed.cashberry.it', $abi),
                        // Predictable seed credentials, same convention as the other seeded users.
                        'password' => sprintf('po.%s', $abi),
                        'sospeso' => false,
                    ],
                );

                $this->filialiPerBanca[$abi] = $filiali;
            }
        });
    }

    /**
     * This command IS the sandbox sync: without the no-op, the inherited
     * handle() would call sandbox:seed again from inside itself.
     */
    protected function syncSandbox(): void
    {
    }

    /**
     * Shapes the /api-sandbox buttons need beyond the generic dataset, per
     * bank:
     *
     * - on the durata-parametrica bank, a few accepted tiraggi get a
     *   conguaglio cadence plus pending settlements on their proiezioni —
     *   estinzione-parziale needs something left to pay — and the seeded
     *   durations snap to the product's step (the generic seed does not know
     *   about steps);
     * - on every bank, one suspended client that still passes the product
     *   target — the only suspended users otherwise are ERR error-targets,
     *   which fail it.
     */
    protected function postSeed(): void
    {
        $this->components->task('Sandbox demo shapes', function (): void {
            foreach (config('sandbox.banks') as $abi => $bank) {
                $this->suspendEligibleClient($abi);

                if ($bank['durata_parametrica']) {
                    $this->seedConguaglioShapes($abi);
                    $this->snapDurationsToStep($abi);
                }
            }

            // The ERR ids are error demos on the API too, not only in SAML
            // simulations: with no storici they can never pass the product
            // target, so any eligible-client write on them answers 422, and
            // with no tiraggi every credit call on them answers 404.
            $sandbox = DB::connection('sandbox');
            $sandbox->table('storici')->where('user_id', 'like', '%\_ERR%')->delete();
            $sandbox->table('previsionali')->where('user_id', 'like', '%\_ERR%')->delete();
        });
    }

    /**
     * Pending conguagli on a few accepted tiraggi: every third forecast month
     * carries an unpaid conguaglio, sized well under the outstanding debt.
     */
    private function seedConguaglioShapes(string $abi): void
    {
        Tiraggio::query()
            ->where('status', 'accepted')
            ->whereNotNull('numero_pratica')
            ->whereHas('user', fn ($q) => $q->where('banca_abi', $abi))
            ->inRandomOrder()
            ->limit(5)
            ->get()
            ->each(function (Tiraggio $tiraggio): void {
                $tiraggio->update(['frequenza_mesi_conguaglio' => 3]);

                Proiezione::query()
                    ->where('tiraggio_numero_pratica', $tiraggio->numero_pratica)
                    ->orderBy('anno')
                    ->orderBy('mese')
                    ->get()
                    ->each(function (Proiezione $proiezione, int $i) use ($tiraggio): void {
                        if ($i % 3 !== 2) {
                            return;
                        }

                        $residuo = (float) $tiraggio->importo_deliberato - (float) $tiraggio->trattenuta_totale;
                        $proiezione->update([
                            'conguaglio' => round(min((float) $proiezione->forecast * 0.1, $residuo * 0.1), 2),
                        ]);
                    });
            });
    }

    /**
     * On durata parametrica the merchant picks a duration on the product's
     * step; snap the seeded durations to the nearest valid multiple.
     */
    private function snapDurationsToStep(string $abi): void
    {
        $prodotto = Prodotto::query()->where('banca_abi', $abi)->first();
        $step = (int) ($prodotto?->step_mesi_tiraggio ?? 0);
        $min = (int) ($prodotto?->min_mesi_tiraggio ?? 1);
        $max = (int) ($prodotto?->max_mesi_tiraggio ?? 18);

        if ($step < 2 || $prodotto === null) {
            return;
        }

        Tiraggio::query()
            ->whereHas('user', fn ($q) => $q->where('banca_abi', $abi))
            ->get()
            ->each(function (Tiraggio $tiraggio) use ($step, $min, $max): void {
                $mesi = (int) $tiraggio->mesi;
                $snapped = min($max, max($min, $mesi - ($mesi % $step) ?: $step));

                if ($snapped !== $mesi) {
                    $tiraggio->update(['mesi' => $snapped]);
                }
            });
    }

    /**
     * Prefer a client with no tiraggi in flight (the natural suspendable
     * state); every PC user carries storico+previsionale, so the fallback
     * still passes the target check on activation.
     */
    private function suspendEligibleClient(string $abi): void
    {
        $suspendable = User::query()
            ->where('user_id', 'like', 'ABI'.$abi.'_PC%')
            ->whereDoesntHave('tiraggi', fn ($q) => $q->whereIn('status', ['requested', 'accepted']))
            ->inRandomOrder()
            ->first()
            ?? User::query()
                ->where('user_id', 'like', 'ABI'.$abi.'_PC%')
                ->inRandomOrder()
                ->first();

        $suspendable?->update(['sospeso' => true]);
    }

    protected function summary(): void
    {
        foreach (config('sandbox.banks') as $abi => $bank) {
            $this->components->twoColumnDetail('<fg=green>Bank</>', $bank['nome'].' ('.$abi.')');
        }

        $this->components->twoColumnDetail('<fg=green>Users</>', (string) User::query()->count());
        $this->components->twoColumnDetail('<fg=green>Tiraggi</>', (string) Tiraggio::query()->count());

        $this->newLine();
        $this->components->info('Sandbox POs: '.implode(', ', array_column(config('sandbox.banks'), 'po')).' — mint tokens via POST /sandbox/token');
    }
}
