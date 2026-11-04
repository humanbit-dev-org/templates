<?php

namespace App\Support\ApiSandbox;

use Illuminate\Support\Str;

/**
 * Builds ready-to-fire demo calls for the API docs sandbox buttons.
 *
 * Each operation resolves a random valid target for ONE sandbox profile —
 * it runs inside EnterSandbox, so Eloquent already points at the sandbox
 * schema — and returns the real /sandbox/* path plus a body that passes the
 * endpoint's validation. Null means no suitable row exists.
 *
 * Adding an operation:
 *   1. add its key to OPERATIONS (the same key used in the 'operations'
 *      list of the EndpointsCatalog entry);
 *   2. add a private static method named after the key in camelCase
 *      (e.g. 'article-publish' → articlePublish(string $profile)) returning
 *      ['method' => 'PATCH', 'path' => '/sandbox/...', 'body' => [...]] or null.
 */
class SampleCallResolver
{
    /** @var list<string> Operation keys the docs page can request a sample for. */
    private const OPERATIONS = [];

    /** @return array<int, string> */
    public static function operations(): array
    {
        return self::OPERATIONS;
    }

    /**
     * The operations a profile can actually run. Every profile runs every
     * operation by default; narrow it here when a profile lacks the data or
     * the permission an operation needs.
     *
     * @return array<int, string>
     */
    public static function operationsForProfile(string $profile): array
    {
        return self::OPERATIONS;
    }

    /** @return array{method: string, path: string, body: array<string, mixed>|null}|null */
    public static function resolve(string $operation, string $profile): ?array
    {
        if (! in_array($operation, self::OPERATIONS, true) || ! array_key_exists($profile, config('sandbox.profiles', []))) {
            return null;
        }

        return self::{Str::camel($operation)}($profile);
    }
}
