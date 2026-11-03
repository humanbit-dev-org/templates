<?php

namespace App\Services\Cache;

/**
 * Centralizes dashboard cache key generation.
 *
 * Scope: only the logic that cannot be expressed as a simple inline string:
 * - Dashboard scope key builder (banca vs filiale discrimination)
 * - allDashboardKeys() — the list used by every Observer and post-import flush
 * - allDashboardKeysForBanca() — convenience wrapper for both scopes at once
 *
 * TTL values live in config/cache_ttl.php and are accessed via config().
 * Simple keys (interfaccia, prodotto, seo, filiali, role) are kept as inline
 * strings in the controllers/observers that use them — no indirection for one-liners.
 *
 * NOTE: Cache::tags() is NOT used. phpredis has known bugs with Laravel tags
 * (confirmed Laravel 12, September 2025). Cache::forget() with explicit key
 * enumeration via allDashboardKeys() is the chosen invalidation strategy.
 */
final class CacheKeyService
{
    // =========================================================
    // Scope key builder
    // ---------------------------------------------------------
    // All dashboard keys are scoped to either a banca (product_owner
    // view) or a filiale (filiale manager view). This prevents any
    // cross-tenant cache sharing and enables targeted invalidation.
    // =========================================================

    /**
     * Build the scope discriminator string from a resolved dashboard scope array.
     *
     * @param  array{scope: string|null, banca_abi?: string|null, filiale_id?: string|null}  $scope
     */
    public static function scopeKey(array $scope): string
    {
        if ($scope['scope'] === 'all') {
            return 'all';
        }

        // Should never happen in a fully authenticated context.
        // Returns a non-colliding fallback rather than an empty string.
        return 'unknown';
    }
}
