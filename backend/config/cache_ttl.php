<?php

/**
 * Cache TTL configuration — application-level cache lifetimes.
 *
 * All values are in seconds. Override via environment variables
 * when a specific environment requires different cache lifetimes
 * (e.g., shorter TTLs in staging to observe invalidation behaviour).
 *
 * Access via: config('cache_ttl.seo')
 * Never use env() directly in application code — always go through config().
 *
 * Add one entry per cached dataset, next to the keys listed in
 * App\Services\Cache\CacheKeyService.
 *
 * Registered automatically by Laravel's config loader (no ServiceProvider needed).
 */

return [

    // =========================================================
    // SEO metadata
    // ---------------------------------------------------------
    // Page title, description, image. Changes only via Backpack
    // admin (MetadataObserver drops the cached entry). 24 hours.
    // =========================================================

    'seo' => (int) env('CACHE_TTL_SEO', 86400),

];
