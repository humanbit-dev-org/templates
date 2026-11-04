<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Explicit post-import cache flush helper.
 *
 * Called once at the end of every import operation — whether from:
 * - ImportJobService::import() (job-based)
 * - ImportCsvAction::importCsv() (Backpack UI)
 *
 * This is the safety net for all write paths that bypass Eloquent Observers
 * (DB::statement, query builder updates) and for Eloquent paths where the
 * Observer suppression flag was set to avoid per-row redundancy.
 *
 * Pattern: call after DB::commit(), before returning the result.
 */
final class CacheFlush
{
    /**
     * Forget every cache key that depends on the imported model.
     *
     * @param  class-string  $modelClass
     */
    public static function afterImport(string $modelClass): void
    {
        $keys = CacheKeyService::forModel($modelClass);

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        if ($keys !== []) {
            Log::debug('CacheFlush: post-import invalidation', [
                'model' => class_basename($modelClass),
                'keys' => count($keys),
            ]);
        }
    }
}
