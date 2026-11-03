<?php

namespace App\Services\Cache;

use App\Services\Cache\BusinessConsoleListCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Explicit post-import cache flush helper.
 *
 * Called once at the end of every import operation — whether from:
 * - ImportJobService::import() (job-based)
 * - ImportCsvAction::importCsv() (Backpack UI)
 * - Business Console import endpoints
 * - InsertProiezioni job
 *
 * This is the safety net for all write paths that bypass Eloquent Observers
 * (DB::statement, query builder updates) and for Eloquent paths where the
 * Observer suppression flag was set to avoid per-row redundancy.
 *
 * Pattern: call after DB::commit(), before returning the result.
 */
final class CacheFlush
{
    
}
