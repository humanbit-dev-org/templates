<?php

namespace App\Services\Cache;

use App\Models\User;

/**
 * Central list of the application cache keys that depend on model data.
 *
 * Observers and the post-import flush (CacheFlush) ask this class which keys
 * to forget, so the invalidation rules live in one place instead of being
 * repeated at every write path.
 *
 * TTL values live in config/cache_ttl.php and are accessed via config().
 * Simple keys used in a single place (e.g. "seo:{lang}:{page}", dropped by
 * MetadataObserver) stay inline where they are used.
 *
 * NOTE: Cache::tags() is NOT used. phpredis has known bugs with Laravel tags
 * (confirmed Laravel 12, September 2025). Cache::forget() with explicit key
 * enumeration is the chosen invalidation strategy.
 */
final class CacheKeyService
{
    /**
     * Keys built from a user's data, forgotten whenever that user changes.
     *
     * The template caches nothing per user: add here the keys of any cache
     * that reads user attributes (e.g. "profile:{$user->getKey()}").
     *
     * @return list<string>
     */
    public static function forUser(User $user): array
    {
        return [];
    }

    /**
     * Keys built from the rows of a model, forgotten after a bulk import of
     * that model (CacheFlush::afterImport()).
     *
     * @param  class-string  $modelClass
     * @return list<string>
     */
    public static function forModel(string $modelClass): array
    {
        return [];
    }
}
