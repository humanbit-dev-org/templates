<?php

namespace App\Jobs\Maintenance;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RehashPasswordsChunkJob implements ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable, SerializesModels;

    private const USER_LOCK_TTL_SECONDS = 900;

    /**
     * Number of times the job may be attempted before failing permanently.
     * Each retry replays the full chunk inside a fresh transaction.
     */
    public int $tries = 3;

    /**
     * Timeout in seconds for a single chunk job.
     * Argon2id is slow by design; 200 users * ~0.5s = ~100s worst case.
     */
    public int $timeout = 300;

    /**
     * @param  array<int, string>  $userIds  List of usernames (users primary key) to process in this chunk.
     * @param  string  $cacheKey  Redis key used to track global progress.
     * @param  bool  $dryRun  When true, derive+hash but do not persist.
     */
    public function __construct(
        private readonly array $userIds,
        private readonly string $cacheKey,
        private readonly bool $dryRun = false,
    ) {}

    public function handle(): void
    {
        Log::channel('import')->info('RehashChunk: handle() called', [
            'usernames_count' => count($this->userIds),
            'cache_key' => $this->cacheKey,
            'batch_id' => $this->batch()?->id,
            'batch_cancelled' => $this->batch()?->cancelled(),
        ]);

        // Bail out early if the whole batch has been cancelled from the Command.
        if ($this->batch()?->cancelled()) {
            return;
        }

        $pdo = DB::connection()->getPdo();
        $updates = [];
        $ids = [];
        $lockedUserIds = [];
        $skipped = 0;
        $skippedLocked = 0;

        try {
            foreach ($this->userIds as $userId) {
                if (! $this->acquireUserLock($userId)) {
                    $skippedLocked++;

                    continue;
                }

                $lockedUserIds[] = $userId;

                try {
                    // The derivation-aware argon2id driver (DerivingArgon2IdHasher)
                    // applies the HKDF pepper inside Hash::make; the raw username is
                    // the password seed, deriving here would double-derive.
                    $hashed = Hash::make($userId);

                    $updates[] = 'WHEN '.$pdo->quote((string) $userId)
                               .' THEN '.$pdo->quote($hashed);
                    $ids[] = $userId;
                } catch (\Throwable $e) {
                    // Log and skip this user; do not abort the whole chunk.
                    Log::channel('import')->error('RehashChunk: hashing failed', [
                        'username' => $userId,
                        'error' => $e->getMessage(),
                    ]);
                    $skipped++;
                }
            }

            if (! $this->dryRun && count($updates) > 0) {
                // Wrap the entire batch UPDATE in a transaction.
                // On any DB error the transaction rolls back and Horizon retries the job.
                DB::transaction(function () use ($pdo, $updates, $ids) {
                    $idsQuoted = implode(',', array_map(
                        fn (string $id) => $pdo->quote($id),
                        $ids
                    ));

                    $sql = 'UPDATE users'
                         .' SET password = CASE username '
                         .implode(' ', $updates)
                         .' END'
                         .' WHERE username IN ('.$idsQuoted.')';

                    DB::statement($sql);
                });
            }

            // Increment the shared Redis counter so the Command can render progress.
            // INCRBY is atomic; no race conditions across parallel workers.
            Cache::increment($this->cacheKey, count($ids));
        } finally {
            foreach ($lockedUserIds as $lockedUserId) {
                $this->releaseUserLock($lockedUserId);
            }
        }

        if ($skipped > 0 || $skippedLocked > 0) {
            Log::channel('import')->warning('RehashChunk: chunk completed with skipped users', [
                'skipped' => $skipped,
                'skipped_locked' => $skippedLocked,
                'chunk' => count($this->userIds),
            ]);
        }
    }

    /**
     * Handle a permanent job failure (after all retries exhausted).
     * Log the failed usernames so they can be replayed manually.
     */
    public function failed(\Throwable $e): void
    {
        Log::channel('import')->critical('RehashChunk: job failed permanently', [
            'usernames' => $this->userIds,
            'error' => $e->getMessage(),
        ]);
    }

    public static function lockKeyForUserId(string $userId): string
    {
        return 'rehash:user-lock:'.$userId;
    }

    private function acquireUserLock(string $userId): bool
    {
        return Cache::add(self::lockKeyForUserId($userId), 1, self::USER_LOCK_TTL_SECONDS);
    }

    private function releaseUserLock(string $userId): void
    {
        Cache::forget(self::lockKeyForUserId($userId));
    }
}
