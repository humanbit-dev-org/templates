<?php

namespace App\Support\ApiSandbox;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Endpoint declarations for the API sandbox page.
 *
 * Hybrid: the structural truth (paths, params, types, response codes) comes
 * from the Scramble-generated OpenAPI spec — so new params/codes appear
 * automatically. Curated text (titles, descriptions, examples, and codes the
 * static analysis cannot infer) lives in OVERRIDES and is merged on top.
 *
 * Fallback: if spec generation fails, the overrides alone still render a
 * complete page.
 */
class EndpointsCatalog
{
    private const SPEC_CACHE_KEY = 'api_sandbox_openapi_spec';
    private const SPEC_CACHE_TTL = 600;

    /**
     * Default copy for response codes the spec reports but the overrides do not
     * describe. The overrides always win when present.
     */
    private const RESPONSE_DEFAULTS = [
        200 => 'Operazione riuscita.',
        204 => 'Nessun contenuto in risposta.',
        400 => 'Richiesta non valida.',
        401 => 'Token di autenticazione mancante o non valido.',
        403 => 'Operazione non consentita.',
        404 => 'Risorsa non trovata.',
        419 => 'Sessione scaduta.',
        422 => 'Dati non validi.',
        500 => 'Errore interno del server.',
    ];

    /**
     * @param  string  $displayPrefix  Prefix shown in place of '/api' on the
     *                                 rendered paths — '/api/v1' renders the
     *                                 same reference for the portal surface.
     * @return array<int, array<string, mixed>>
     */
    public static function all(string $displayPrefix = '/api'): array
    {
        $spec = self::spec();
        $overrides = self::overrides();

        return array_values(array_map(
            fn (string $key, array $override) => self::buildEntry($key, $override, $spec, $displayPrefix),
            array_keys($overrides),
            $overrides,
        ));
    }

    private static function spec(): array
    {
        try {
            return Cache::remember(self::SPEC_CACHE_KEY, self::SPEC_CACHE_TTL, function () {
                /** @var OpenApi $openApi */
                $openApi = app(Generator::class)->setThrowExceptions(false)();

                return $openApi->toArray();
            });
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $override
     * @param  array<string, mixed>  $spec
     * @param  string  $displayPrefix  Replaces the '/api' segment on rendered paths.
     * @return array<string, mixed>
     */
    private static function buildEntry(string $key, array $override, array $spec, string $displayPrefix): array
    {
        [$method, $path] = explode(' ', $key, 2);
        $operation = $spec['paths'][self::specPath($path)][strtolower($method)] ?? null;

        return [
            'id' => $override['id'],
            'title' => $override['title'],
            'method' => $method,
            'path' => $displayPrefix.substr($path, strlen('/api')),
            'description' => $override['description'],
            'path_params' => $operation !== null
                ? self::mergePathParams($operation, $override['path_params'] ?? [])
                : ($override['path_params'] ?? []),
            'body_params' => $operation !== null
                ? self::mergeBodyParams($operation, $override['body_params'] ?? [], $spec)
                : ($override['body_params'] ?? []),
            'responses' => self::mergeResponses($operation, $override['responses'] ?? []),
            'operations' => $override['operations'] ?? [],
        ];
    }

    /**
     * Spec paths carry no "/api" prefix; our display paths do.
     */
    private static function specPath(string $path): string
    {
        return preg_replace('#^/api#', '', $path);
    }

    /**
     * Path params from the spec, descriptions looked up in the override list
     * by name. Override params absent from the spec are appended so curated
     * content never disappears.
     *
     * @param  array<string, mixed>  $operation
     * @param  array<int, array<string, mixed>>  $overrideParams
     * @return array<int, array<string, mixed>>
     */
    private static function mergePathParams(array $operation, array $overrideParams): array
    {
        $byName = collect($overrideParams)->keyBy('name');
        $merged = [];

        foreach ($operation['parameters'] ?? [] as $param) {
            if (($param['in'] ?? null) !== 'path') {
                continue;
            }

            $curated = $byName->pull($param['name']);

            $merged[] = [
                'name' => $param['name'],
                'type' => self::normalizeType($param['schema'] ?? []),
                'required' => $param['required'] ?? true,
                'description' => $curated['description'] ?? '',
            ];
        }

        return [...$merged, ...$byName->values()->all()];
    }

    /**
     * Body params from the request schema (resolved through $ref into
     * components.schemas), descriptions looked up in the override list.
     *
     * @param  array<string, mixed>  $operation
     * @param  array<int, array<string, mixed>>  $overrideParams
     * @param  array<string, mixed>  $spec
     * @return array<int, array<string, mixed>>
     */
    private static function mergeBodyParams(array $operation, array $overrideParams, array $spec): array
    {
        $schema = $operation['requestBody']['content']['application/json']['schema'] ?? null;

        if (isset($schema['$ref'])) {
            $name = str_replace('#/components/schemas/', '', $schema['$ref']);
            $schema = $spec['components']['schemas'][$name] ?? null;
        }

        if (! isset($schema['properties'])) {
            return $overrideParams;
        }

        $required = $schema['required'] ?? [];
        $byName = collect($overrideParams)->keyBy('name');
        $merged = [];

        foreach ($schema['properties'] as $name => $property) {
            $curated = $byName->pull($name);

            $merged[] = [
                'name' => $name,
                'type' => self::normalizeType($property),
                'required' => in_array($name, $required, true),
                'description' => $curated['description'] ?? '',
            ];
        }

        return [...$merged, ...$byName->values()->all()];
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private static function normalizeType(array $schema): string
    {
        $format = $schema['format'] ?? null;

        if (in_array($format, ['date', 'date-time'], true)) {
            return 'date';
        }

        $type = $schema['type'] ?? 'string';
        $type = is_array($type)
            ? (collect($type)->first(fn ($t) => $t !== 'null') ?? 'mixed')
            : $type;

        return (string) $type;
    }

    /**
     * Union of spec-reported codes and override-declared codes (the overrides
     * carry codes static analysis cannot infer, e.g. scope 403s). Sorted
     * ascending; override description/example win, defaults fill the rest.
     *
     * @param  array<string, mixed>|null  $operation
     * @param  array<int, array<string, mixed>>  $overrideResponses
     * @return array<int, array<string, mixed>>
     */
    private static function mergeResponses(?array $operation, array $overrideResponses): array
    {
        $byCode = collect($overrideResponses)->keyBy('status');
        $specCodes = collect($operation['responses'] ?? [])->keys()->map('intval');
        $codes = $specCodes->merge($byCode->keys())->unique()->sort()->values();

        return $codes->map(function (int $code) use ($byCode) {
            $curated = $byCode->get($code) ?? [];

            return [
                'status' => $code,
                'description' => $curated['description'] ?? (self::RESPONSE_DEFAULTS[$code] ?? ''),
            ];
        })->all();
    }

    /**
     * Curated content, keyed by "METHOD /api/path". Params lists serve both as
     * description sources for the merge and as the full fallback when the spec
     * is unavailable.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function overrides(): array
    {
        return [
            'PATCH /api/business-console/eleggibili/{user}' => [
                'id' => 'eleggibile-attiva-sospendi',
                'title' => 'Attiva / sospendi cliente eleggibile',
                'description' => 'Attiva o sospende un cliente eleggibile, e ne aggiorna i dati anagrafici. Con sospeso=false il cliente viene attivato: i parametri della delibera (max_atp_effettivo, tan, taeg) e la filiale diventano obbligatori, e viene creata o aggiornata una delibera in stato open. Con sospeso=true il cliente viene sospeso; l\'operazione è vietata se il cliente ha tiraggi in stato requested o accepted. Senza sospeso, i soli dati anagrafici vengono salvati. In fase di attivazione il cliente deve inoltre rispettare il target del prodotto configurato sulla banca.',
                'path_params' => [
                    ['name' => 'user', 'type' => 'string', 'required' => true, 'description' => 'Identificativo del cliente eleggibile.'],
                ],
                'body_params' => [
                    ['name' => 'sospeso', 'type' => 'boolean', 'required' => false, 'description' => 'false per attivare il cliente, true per sospenderlo. Se omesso, vengono solo aggiornati i dati anagrafici.'],
                    ['name' => 'max_atp_effettivo', 'type' => 'integer', 'required' => true, 'description' => 'Plafond massimo erogabile. Obbligatorio solo con sospeso=false.'],
                    ['name' => 'tan', 'type' => 'number', 'required' => true, 'description' => 'TAN della delibera (2-3 decimali, 0-100). Obbligatorio solo con sospeso=false.'],
                    ['name' => 'taeg', 'type' => 'number', 'required' => true, 'description' => 'TAEG della delibera (2-3 decimali, 0-100). Obbligatorio solo con sospeso=false.'],
                    ['name' => 'filiale_id', 'type' => 'string', 'required' => true, 'description' => 'Filiale di riferimento del cliente. Obbligatorio solo con sospeso=false.'],
                    ['name' => 'ateco', 'type' => 'string', 'required' => false, 'description' => 'Codice ATECO del cliente (max 20 caratteri, normalizzato dal server).'],
                    ['name' => 'provincia', 'type' => 'string', 'required' => false, 'description' => 'Sigla della provincia (2 caratteri).'],
                    ['name' => 'codice_postale', 'type' => 'string', 'required' => false, 'description' => 'CAP del cliente (5 caratteri).'],
                ],
                'operations' => [
                    ['key' => 'eleggibili-attiva', 'label' => 'Attiva'],
                    ['key' => 'eleggibili-sospendi', 'label' => 'Sospendi'],
                    ['key' => 'eleggibili-anagrafica', 'label' => 'Solo anagrafica'],
                ],
                'responses' => [
                    ['status' => 200, 'description' => 'Cliente aggiornato. Restituisce i dati completi del cliente.'],
                    ['status' => 401, 'description' => 'Token di autenticazione mancante o non valido.'],
                    ['status' => 403, 'description' => "Il cliente non appartiene alla banca dell'operatore."],
                    ['status' => 404, 'description' => 'Cliente non trovato.'],
                    ['status' => 422, 'description' => 'Dati non validi, cliente fuori target, prodotto non configurato sulla banca, oppure sospensione vietata perché il cliente ha tiraggi in corso.'],
                ],
            ],
            'PATCH /api/business-console/tiraggi/users/{user_id}/perfeziona' => [
                'id' => 'conferma-delibera',
                'title' => 'Conferma delibera',
                'description' => 'Conferma la delibera di un tiraggio in stato requested, perfezionando la richiesta di finanziamento. Il tiraggio passa a stato accepted e vengono generate le proiezioni di rimborso.',
                'path_params' => [
                    ['name' => 'user_id', 'type' => 'string', 'required' => true, 'description' => 'Identificativo del cliente titolare del tiraggio.'],
                ],
                'body_params' => [
                    ['name' => 'numero_pratica', 'type' => 'string', 'required' => true, 'description' => 'Numero di pratica assegnato dalla banca. Deve essere univoco.'],
                    ['name' => 'importo_deliberato', 'type' => 'integer', 'required' => true, 'description' => 'Importo deliberato del finanziamento.'],
                    ['name' => 'data_delibera', 'type' => 'date', 'required' => true, 'description' => 'Data della delibera. Deve essere uguale o successiva alla data di richiesta.'],
                    ['name' => 'percentuale', 'type' => 'integer', 'required' => false, 'description' => 'Percentuale di trattenuta sul transato. Limiti min/max configurati sulla banca. Non usata nei prodotti a durata parametrica.'],
                    ['name' => 'mesi', 'type' => 'integer', 'required' => false, 'description' => 'Durata di rientro in mesi (1-18). Rilevante nei prodotti a durata parametrica.'],
                    ['name' => 'tan', 'type' => 'number', 'required' => false, 'description' => 'TAN applicato (2-3 decimali). Se omesso, viene usato il valore della delibera.'],
                    ['name' => 'taeg', 'type' => 'number', 'required' => false, 'description' => 'TAEG applicato (2-3 decimali). Se omesso, viene usato il valore della delibera.'],
                ],
                'operations' => [
                    ['key' => 'perfeziona', 'label' => 'Conferma delibera'],
                ],
                'responses' => [
                    ['status' => 200, 'description' => 'Delibera confermata.'],
                    ['status' => 400, 'description' => 'user_id mancante.'],
                    ['status' => 401, 'description' => 'Token di autenticazione mancante o non valido.'],
                    ['status' => 404, 'description' => 'Utente, tiraggio o delibera non trovati.'],
                    ['status' => 422, 'description' => 'Dati non validi, oppure impossibile calcolare le proiezioni per mancanza di previsionali.'],
                ],
            ],
            'PATCH /api/business-console/tiraggi/users/{user_id}/rifiuta' => [
                'id' => 'rifiuta-delibera',
                'title' => 'Rifiuta delibera',
                'description' => 'Rifiuta la delibera del tiraggio in stato requested del cliente. Il tiraggio passa a stato rejected.',
                'path_params' => [
                    ['name' => 'user_id', 'type' => 'string', 'required' => true, 'description' => 'Identificativo del cliente titolare del tiraggio.'],
                ],
                'operations' => [
                    ['key' => 'rifiuta', 'label' => 'Rifiuta delibera'],
                ],
                'responses' => [
                    ['status' => 204, 'description' => 'Tiraggio rifiutato. Nessun contenuto in risposta.'],
                    ['status' => 400, 'description' => 'user_id mancante.'],
                    ['status' => 401, 'description' => 'Token di autenticazione mancante o non valido.'],
                    ['status' => 404, 'description' => 'Utente non trovato o nessun tiraggio in stato requested.'],
                ],
            ],
            'PATCH /api/business-console/tiraggi/users/{user_id}/abbandona' => [
                'id' => 'abbandona-richiesta',
                'title' => 'Abbandona richiesta',
                'description' => 'Abbandona la richiesta di finanziamento del cliente. Il tiraggio in stato requested passa a stato dropped.',
                'path_params' => [
                    ['name' => 'user_id', 'type' => 'string', 'required' => true, 'description' => 'Identificativo del cliente titolare del tiraggio.'],
                ],
                'operations' => [
                    ['key' => 'abbandona', 'label' => 'Abbandona richiesta'],
                ],
                'responses' => [
                    ['status' => 204, 'description' => 'Richiesta abbandonata. Nessun contenuto in risposta.'],
                    ['status' => 400, 'description' => 'user_id mancante.'],
                    ['status' => 401, 'description' => 'Token di autenticazione mancante o non valido.'],
                    ['status' => 404, 'description' => 'Utente non trovato o nessun tiraggio in stato requested.'],
                ],
            ],
            'PATCH /api/business-console/tiraggi/{tiraggio_pratica}/estinzione' => [
                'id' => 'estinzione-totale',
                'title' => 'Estinzione anticipata totale',
                'description' => 'Estingue anticipatamente e integralmente un tiraggio in stato accepted. La trattenuta residua viene registrata e il tiraggio passa a stato paid. Se non esiste già, viene creato un nuovo tiraggio in stato open per il cliente.',
                'path_params' => [
                    ['name' => 'tiraggio_pratica', 'type' => 'string', 'required' => true, 'description' => 'Numero di pratica del tiraggio da estinguere.'],
                ],
                'operations' => [
                    ['key' => 'estinzione', 'label' => 'Estinzione totale'],
                ],
                'responses' => [
                    ['status' => 200, 'description' => 'Tiraggio estinto.'],
                    ['status' => 400, 'description' => 'Numero di pratica mancante.'],
                    ['status' => 401, 'description' => 'Token di autenticazione mancante o non valido.'],
                    ['status' => 403, 'description' => "Il tiraggio non appartiene alla banca dell'operatore."],
                    ['status' => 404, 'description' => 'Tiraggio non trovato o non in stato accepted.'],
                ],
            ],
            'PATCH /api/business-console/tiraggi/{tiraggio_pratica}/estinzione-parziale' => [
                'id' => 'estinzione-parziale',
                'title' => 'Estinzione anticipata parziale',
                'description' => "Registra un versamento parziale (conguaglio) su un tiraggio in stato accepted. Disponibile solo su prodotti a conguaglio periodico. L'importo non può superare il debito residuo né i conguagli ancora da versare.",
                'path_params' => [
                    ['name' => 'tiraggio_pratica', 'type' => 'string', 'required' => true, 'description' => 'Numero di pratica del tiraggio.'],
                ],
                'body_params' => [
                    ['name' => 'importo_versato', 'type' => 'number', 'required' => true, 'description' => 'Importo del versamento. Deve essere maggiore di zero.'],
                    ['name' => 'data_versamento', 'type' => 'date', 'required' => false, 'description' => 'Data in cui il cliente ha versato. Non può essere nel futuro. Default: oggi.'],
                ],
                'operations' => [
                    ['key' => 'estinzione-parziale', 'label' => 'Estinzione parziale', 'conguaglio_only' => true],
                ],
                'responses' => [
                    ['status' => 200, 'description' => 'Versamento registrato.'],
                    ['status' => 401, 'description' => 'Token di autenticazione mancante o non valido.'],
                    ['status' => 403, 'description' => "Il tiraggio non appartiene alla banca dell'operatore."],
                    ['status' => 404, 'description' => 'Tiraggio non trovato o non in stato accepted.'],
                    ['status' => 422, 'description' => 'Importo non valido, tiraggio già rimborsato, prodotto non a conguaglio, o movimento già presente nella stessa data contabile.'],
                ],
            ],
            'GET /api/dashboard/users/{user_id}' => [
                'id' => 'stato-cliente',
                'title' => 'Stato cliente',
                'description' => 'Restituisce lo stato completo del cliente: anagrafica, filiale, storico mensile degli ultimi 12 mesi, rating, elenco dei tiraggi e dettaglio dei finanziamenti attivi con forecast, baseline e trattenute mensili.',
                'path_params' => [
                    ['name' => 'user_id', 'type' => 'string', 'required' => true, 'description' => 'Identificativo del cliente.'],
                ],
                'operations' => [
                    ['key' => 'stato-cliente', 'label' => 'Stato cliente'],
                ],
                'responses' => [
                    ['status' => 200, 'description' => 'Dettaglio del cliente.'],
                    ['status' => 401, 'description' => 'Token di autenticazione mancante o non valido.'],
                    ['status' => 403, 'description' => "Il cliente non appartiene all'ambito dell'operatore."],
                    ['status' => 404, 'description' => 'Cliente non trovato.'],
                ],
            ],
        ];
    }
}
