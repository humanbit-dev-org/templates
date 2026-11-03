<?php

namespace App\Services\Auth;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Keeps job_batches + Horizon from accumulating forever-pending rehash runs.
 *
 * Batch metadata lives in MySQL; without cancellation, stuck runs stay "Pending"
 * even when workers never consumed Redis payloads.
 */
class RehashPasswordBatchCleanupService
{
    public const BATCH_NAME = 'auth:rehash-passwords';

    private const REHASH_QUEUE = 'rehash';

    /**
     * Cancel incomplete batches with the auth:rehash-passwords name.
     *
     * Only rows with created_at strictly older than (now - $minimumAgeSeconds) are selected,
     * so concurrent imports / in-flight runs started recently are never cancelled.
     *
     * @param  int  $minimumAgeSeconds  Must be >= 1 (typically hours * 3600 from schedule).
     * @return int Number of batches cancelled
     */
    public function cancelIncompleteBatches(int $minimumAgeSeconds): int
    {
        $table = config('queue.batching.table', 'job_batches');

        return $this->cancelBatchIds($table, $this->incompleteBatches($minimumAgeSeconds)->pluck('id'));
    }

    /**
     * The batches cancelIncompleteBatches() would cancel, for a dry run.
     *
     * @return Collection<int, object{id: string, total_jobs: int, pending_jobs: int, failed_jobs: int, created_at: int}>
     */
    public function incompleteBatches(int $minimumAgeSeconds): Collection
    {
        $minimumAgeSeconds = max(1, $minimumAgeSeconds);

        return DB::table(config('queue.batching.table', 'job_batches'))
            ->where('name', self::BATCH_NAME)
            ->whereNull('finished_at')
            ->where('created_at', '<', now()->timestamp - $minimumAgeSeconds)
            ->orderBy('created_at')
            ->get(['id', 'total_jobs', 'pending_jobs', 'failed_jobs', 'created_at']);
    }

    /**
     * Cancel stale batches that never started processing any job.
     *
     * This is safe to run before dispatching a new rehash command because it only targets
     * rows where pending_jobs == total_jobs and failed_jobs == 0 (no progress at all).
     *
     * @param  int  $minimumAgeSeconds  Must be >= 1.
     * @return int Number of batches cancelled
     */
    public function cancelStaleUnstartedBatches(int $minimumAgeSeconds): int
    {
        $minimumAgeSeconds = max(1, $minimumAgeSeconds);

        $table = config('queue.batching.table', 'job_batches');

        $ids = DB::table($table)
            ->where('name', self::BATCH_NAME)
            ->whereNull('finished_at')
            ->where('pending_jobs', '=', DB::raw('total_jobs'))
            ->where('failed_jobs', '=', 0)
            ->where('created_at', '<', now()->timestamp - $minimumAgeSeconds)
            ->pluck('id');

        return $this->cancelBatchIds($table, $ids);
    }

    /**
     * Cancel batches by id with robust fallback for non-rehydratable rows.
     *
     * @param  Collection<int, string>  $ids
     */
    private function cancelBatchIds(string $table, $ids): int
    {
        $cancelled = 0;

        foreach ($ids as $id) {
            $id = (string) $id;

            try {
                $batch = Bus::findBatch($id);
            } catch (\Throwable) {
                $this->forceCancelBatchRow($table, $id);
                $this->purgeQueuedJobsForBatchId($id);
                $cancelled++;

                continue;
            }

            if ($batch === null) {
                continue;
            }

            if ($batch->finished()) {
                continue;
            }

            $batch->cancel();
            $this->purgeQueuedJobsForBatchId($id);
            $cancelled++;
        }

        return $cancelled;
    }

    /**
     * Mark a job_batches row finished when the batch object cannot be rehydrated (corrupt options, etc.).
     */
    private function forceCancelBatchRow(string $table, string $id): void
    {
        DB::table($table)->where('id', $id)->update([
            'cancelled_at' => now()->timestamp,
            'finished_at' => now()->timestamp,
        ]);
    }

    /**
     * Remove pending Redis queue payloads that belong to a cancelled rehash batch.
     *
     * This keeps Horizon "Pending Jobs" aligned with batch cancellation and avoids
     * accumulating orphan payloads when workers are not draining the queue.
     */
    private function purgeQueuedJobsForBatchId(string $batchId): void
    {
        try {
            $redis = Redis::connection('queues');
            $marker = "\"batchId\":\"{$batchId}\"";

            $listKey = 'queues:{rehash}';
            $delayedKey = $listKey.':delayed';
            $reservedKey = $listKey.':reserved';

            $listPayloads = $redis->lrange($listKey, 0, -1) ?: [];
            foreach ($listPayloads as $payload) {
                if (str_contains((string) $payload, $marker)) {
                    $redis->lrem($listKey, 0, $payload);
                }
            }

            $delayedPayloads = $redis->zrange($delayedKey, 0, -1) ?: [];
            foreach ($delayedPayloads as $payload) {
                if (str_contains((string) $payload, $marker)) {
                    $redis->zrem($delayedKey, $payload);
                }
            }

            $reservedPayloads = $redis->zrange($reservedKey, 0, -1) ?: [];
            foreach ($reservedPayloads as $payload) {
                if (str_contains((string) $payload, $marker)) {
                    $redis->zrem($reservedKey, $payload);
                }
            }
        } catch (\Throwable) {
            // Best-effort cleanup: batch cancellation must not fail if Redis is temporarily unavailable.
        }
    }
}
