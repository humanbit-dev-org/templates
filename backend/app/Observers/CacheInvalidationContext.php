<?php

namespace App\Observers;

/**
 * Static context flag to suppress Eloquent Observer cache invalidation
 * during bulk import operations.
 *
 * Problem: when ImportCsvAction::importCsv() processes 5,000 rows via Eloquent
 * create/update, the Observers would call Cache::forget() on the same keys
 * 5,000 times — correct but wasteful. The post-import flush handles it once.
 *
 * Usage in import methods that use Eloquent (not raw DB::statement):
 *
 *   CacheInvalidationContext::suppress();
 *   try {
 *       // ... bulk Eloquent operations
 *   } finally {
 *       CacheInvalidationContext::restore();
 *   }
 *   // Post-import flush here (explicit, once)
 *   CacheFlush::afterImport($modelClass);
 *
 * NOT needed for operations that bypass Eloquent (DB::statement, query builder
 * update()) — those never trigger Observers anyway.
 *
 * Thread safety: this is a PHP static property, scoped to the current FPM
 * worker process. It is safe because each import job runs in its own worker
 * and the flag is always restored in a finally block.
 */
final class CacheInvalidationContext
{
	private static bool $suppressed = false;

	/** Suppress Observer cache invalidation for the current process */
	public static function suppress(): void
	{
		self::$suppressed = true;
	}

	/** Restore normal Observer cache invalidation */
	public static function restore(): void
	{
		self::$suppressed = false;
	}

	/** Returns true when Observers should skip cache invalidation */
	public static function isSuppressed(): bool
	{
		return self::$suppressed;
	}
}
