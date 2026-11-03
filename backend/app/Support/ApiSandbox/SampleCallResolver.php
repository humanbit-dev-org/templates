<?php

namespace App\Support\ApiSandbox;

use App\Models\Filiale;
use App\Models\Prodotto;
use App\Models\Proiezione;
use App\Models\Tiraggio;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Builds ready-to-fire demo calls for the /api-sandbox buttons.
 *
 * Each operation resolves a random valid target inside ONE sandbox bank —
 * it runs inside EnterSandbox, so Eloquent already points at sandbox_rays —
 * and returns the real /sandbox/* path plus a body that passes the
 * endpoint's validation. Null means no suitable row exists.
 *
 * Which operations exist depends on the bank's product shape:
 * estinzione-parziale only makes sense where the product is a conguaglio
 * one (the durata-parametrica bank — or nowhere, when its flag is toggled
 * off for the request).
 */
class SampleCallResolver
{
    private const OPERATIONS = [
        'perfeziona',
        'rifiuta',
        'abbandona',
        'estinzione',
        'estinzione-parziale',
        'stato-cliente',
        'eleggibili-attiva',
        'eleggibili-sospendi',
        'eleggibili-anagrafica',
    ];

    /** @return array<int, string> */
    public static function operations(): array
    {
        return self::OPERATIONS;
    }

    /**
     * The operations a bank's current product config can actually run.
     *
     * @return array<int, string>
     */
    public static function operationsForBank(string $abi): array
    {
        $conguaglio = (bool) Prodotto::query()
            ->where('banca_abi', $abi)
            ->value('conguaglio_periodico');

        return $conguaglio
            ? self::OPERATIONS
            : array_values(array_diff(self::OPERATIONS, ['estinzione-parziale']));
    }

    /** @return array{method: string, path: string, body: array<string, mixed>|null}|null */
    public static function resolve(string $operation, string $abi): ?array
    {
        if (! in_array($operation, self::OPERATIONS, true) || ! array_key_exists($abi, config('sandbox.banks', []))) {
            return null;
        }

        return self::{Str::camel($operation)}($abi);
    }

    /** @return array{method: string, path: string, body: array<string, mixed>|null}|null */
    private static function perfeziona(string $abi): ?array
    {
        $tiraggio = self::requestedTiraggio($abi);

        if ($tiraggio === null) {
            return null;
        }

        $body = [
            // A random code stays unique forever: nothing is ever committed.
            'numero_pratica' => 'DEMO-'.Str::upper(Str::random(8)),
            // The bank cannot deliberate more than the merchant asked.
            'importo_deliberato' => (int) $tiraggio->importo_richiesto,
            'data_delibera' => Carbon::parse($tiraggio->data_richiesta)->max(now())->toDateString(),
        ];

        // Durata parametrica: the operator picks the duration and the
        // percentuale is derived — echo the request's own (step-snapped) mesi.
        if ($tiraggio->haDurataParametrica()) {
            $body['mesi'] = (int) $tiraggio->mesi;
        } else {
            $body['percentuale'] = (int) $tiraggio->percentuale;
        }

        return [
            'method' => 'PATCH',
            'path' => "/sandbox/business-console/tiraggi/users/{$tiraggio->user_id}/perfeziona",
            'body' => $body,
        ];
    }

    /** @return array{method: string, path: string, body: array<string, mixed>|null}|null */
    private static function rifiuta(string $abi): ?array
    {
        return self::requestedCall($abi, 'rifiuta');
    }

    /** @return array{method: string, path: string, body: array<string, mixed>|null}|null */
    private static function abbandona(string $abi): ?array
    {
        return self::requestedCall($abi, 'abbandona');
    }

    /** @return array{method: string, path: string, body: array<string, mixed>|null}|null */
    private static function estinzione(string $abi): ?array
    {
        $tiraggio = self::acceptedTiraggio($abi);

        if ($tiraggio === null) {
            return null;
        }

        return [
            'method' => 'PATCH',
            'path' => "/sandbox/business-console/tiraggi/{$tiraggio->numero_pratica}/estinzione",
            'body' => null,
        ];
    }

    /** @return array{method: string, path: string, body: array<string, mixed>|null}|null */
    private static function estinzioneParziale(string $abi): ?array
    {
        // Only a conguaglio tiraggio with settlements still due can take a
        // partial repayment — the endpoint 422s on anything else. Try a few
        // random candidates: some may already have their conguagli settled.
        $tiraggios = Tiraggio::query()
            ->where('status', 'accepted')
            ->whereNotNull('numero_pratica')
            ->whereHas('user', fn ($q) => $q->where('banca_abi', $abi))
            ->whereExists(
                fn ($q) => $q->from('proiezioni')
                    ->whereColumn('proiezioni.tiraggio_numero_pratica', 'tiraggi.numero_pratica')
                    ->where('conguaglio', '>', 0)
            )
            ->inRandomOrder()
            ->limit(10)
            ->get();

        $tiraggio = $tiraggios->first(
            fn (Tiraggio $tiraggio): bool => $tiraggio->isConguaglio() && self::conguaglioCap($tiraggio) > 0
        );

        if ($tiraggio === null) {
            return null;
        }

        $cap = self::conguaglioCap($tiraggio);

        return [
            'method' => 'PATCH',
            'path' => "/sandbox/business-console/tiraggi/{$tiraggio->numero_pratica}/estinzione-parziale",
            'body' => [
                'importo_versato' => round($cap * (mt_rand(10, 50) / 100), 2),
                'data_versamento' => now()->toDateString(),
            ],
        ];
    }

    /** @return array{method: string, path: string, body: array<string, mixed>|null}|null */
    private static function statoCliente(string $abi): ?array
    {
        $user = self::randomClient($abi);

        if ($user === null) {
            return null;
        }

        return [
            'method' => 'GET',
            'path' => "/sandbox/dashboard/users/{$user->user_id}",
            'body' => null,
        ];
    }

    /** @return array{method: string, path: string, body: array<string, mixed>|null}|null */
    private static function eleggibiliAttiva(string $abi): ?array
    {
        $prodotto = Prodotto::query()->where('banca_abi', $abi)->first();
        $filialeId = Filiale::query()->where('banca_abi', $abi)->inRandomOrder()->value('filiale_id');

        if ($prodotto === null || $filialeId === null) {
            return null;
        }

        // Activation has to pass the product target: skip candidates that
        // would only demo a 422. Normal-looking PC/MT clients are preferred
        // over the ERR error-demo users.
        $targetOk = fn (User $user) => $user->checkProductTarget($prodotto)['eleggibile'];
        $suspended = fn () => User::query()
            ->where('sospeso', true)
            ->where('user_id', 'like', 'ABI'.$abi.'_%')
            ->inRandomOrder();

        $user = $suspended()->where('user_id', 'not like', '%\_ERR%')->get()->first($targetOk)
            ?? $suspended()->get()->first($targetOk);

        if ($user === null) {
            return null;
        }

        return [
            'method' => 'PATCH',
            'path' => "/sandbox/business-console/eleggibili/{$user->user_id}",
            'body' => [
                'sospeso' => false,
                'max_atp_effettivo' => min(30000, (int) $prodotto->max_atp),
                // decimal:2,3 — strings, because JSON floats can't express "4.50".
                'tan' => number_format(min(4.50, (float) $prodotto->max_tan), 2, '.', ''),
                'taeg' => number_format(min(5.50, (float) $prodotto->max_taeg), 2, '.', ''),
                'filiale_id' => $filialeId,
            ],
        ];
    }

    /** @return array{method: string, path: string, body: array<string, mixed>|null}|null */
    private static function eleggibiliSospendi(string $abi): ?array
    {
        // Suspension is refused while the client has tiraggi in flight.
        $user = User::query()
            ->where('sospeso', false)
            ->where('user_id', 'like', 'ABI'.$abi.'_%')
            ->whereDoesntHave('tiraggi', fn ($q) => $q->whereIn('status', ['requested', 'accepted']))
            ->inRandomOrder()
            ->first();

        if ($user === null) {
            return null;
        }

        return [
            'method' => 'PATCH',
            'path' => "/sandbox/business-console/eleggibili/{$user->user_id}",
            'body' => ['sospeso' => true],
        ];
    }

    /** @return array{method: string, path: string, body: array<string, mixed>|null}|null */
    private static function eleggibiliAnagrafica(string $abi): ?array
    {
        $user = self::randomClient($abi);

        if ($user === null) {
            return null;
        }

        return [
            'method' => 'PATCH',
            'path' => "/sandbox/business-console/eleggibili/{$user->user_id}",
            'body' => [
                'ateco' => '47.11.00',
                'provincia' => 'MI',
                'codice_postale' => '20121',
            ],
        ];
    }

    /**
     * The most a partial repayment can cover on this tiraggio: the smaller of
     * the outstanding debt and the conguagli still due.
     */
    private static function conguaglioCap(Tiraggio $tiraggio): float
    {
        $residuoDebito = max(0, (float) $tiraggio->importo_deliberato - (float) $tiraggio->trattenuta_totale);
        $conguaglioResiduo = max(
            0,
            (float) $tiraggio->getConguagliPrevisti()->sum('conguaglio')
                - (float) $tiraggio->getConguagli()->sum('importo_versato')
        );

        return min($residuoDebito, $conguaglioResiduo);
    }

    private static function requestedTiraggio(string $abi): ?Tiraggio
    {
        return Tiraggio::query()
            ->where('status', 'requested')
            ->whereHas('user', fn ($q) => $q->where('banca_abi', $abi))
            ->inRandomOrder()
            ->first();
    }

    private static function acceptedTiraggio(string $abi): ?Tiraggio
    {
        return Tiraggio::query()
            ->where('status', 'accepted')
            ->whereNotNull('numero_pratica')
            ->whereHas('user', fn ($q) => $q->where('banca_abi', $abi))
            ->inRandomOrder()
            ->first();
    }

    /** @return array{method: string, path: string, body: array<string, mixed>|null}|null */
    private static function requestedCall(string $abi, string $action): ?array
    {
        $tiraggio = self::requestedTiraggio($abi);

        if ($tiraggio === null) {
            return null;
        }

        return [
            'method' => 'PATCH',
            'path' => "/sandbox/business-console/tiraggi/users/{$tiraggio->user_id}/{$action}",
            'body' => null,
        ];
    }

    private static function randomClient(string $abi): ?User
    {
        // Prefer normal PC/MT demo clients; the ERR users are error demos.
        $base = fn () => User::query()
            ->where('user_id', 'like', 'ABI'.$abi.'_%')
            ->inRandomOrder();

        return $base()->where('user_id', 'not like', '%\_ERR%')->first()
            ?? $base()->first();
    }
}
