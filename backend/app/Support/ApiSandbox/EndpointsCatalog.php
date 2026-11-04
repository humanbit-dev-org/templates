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
     * The template ships with no documented endpoint: add one entry per route
     * exposed through the sandbox (routes/sandbox.php) or the portal
     * (routes/portal.php). Shape of an entry:
     *
     *     'POST /api/articles/{article}/publish' => [
     *         'id' => 'article-publish',             // anchor and try-it key
     *         'title' => 'Publish an article',
     *         'description' => 'Marks the article as published.',
     *         'path_params' => [
     *             ['name' => 'article', 'type' => 'integer', 'required' => true, 'description' => 'Article id.'],
     *         ],
     *         'body_params' => [
     *             ['name' => 'published_at', 'type' => 'date', 'required' => false, 'description' => 'Defaults to now.'],
     *         ],
     *         'operations' => [
     *             ['key' => 'article-publish', 'label' => 'Publish'],
     *         ],
     *         'responses' => [
     *             ['status' => 200, 'description' => 'Article published.'],
     *             ['status' => 404, 'description' => 'Article not found.'],
     *         ],
     *     ],
     *
     * Each operation key must have a matching sample in SampleCallResolver so
     * the sandbox try-it panel can pre-fill a working call.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function overrides(): array
    {
        return [];
    }
}
