<?php

namespace App\Console\Commands;

use App\Services\Auth\RehashPasswordBatchCleanupService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Cancels stuck auth:rehash-passwords bus batches (unfinished in job_batches).
 *
 * Scheduled with a minimum age so in-flight legitimate runs are not cancelled.
 */
class CancelStuckRehashPasswordBatches extends Command
{
    protected $signature = 'auth:cancel-stuck-rehash-batches
                            {--hours=24 : Cancel only batches created at least this many hours ago}
                            {--dry-run : List the batches that would be cancelled, cancelling nothing}';

    protected $description = 'Cancel unfinished auth:rehash-passwords batches older than N hours';

    public function handle(RehashPasswordBatchCleanupService $cleanup): int
    {
        $hours = max(1, (int) $this->option('hours'));

        if ($this->option('dry-run')) {
            return $this->preview($cleanup, $hours);
        }

        $cancelled = $cleanup->cancelIncompleteBatches($hours * 3600);

        if ($cancelled === 0) {
            $this->info('No matching stuck rehash batches to cancel.');

            return self::SUCCESS;
        }

        $this->info("Cancelled {$cancelled} stuck rehash batch(es) (age >= {$hours}h).");

        return self::SUCCESS;
    }

    private function preview(RehashPasswordBatchCleanupService $cleanup, int $hours): int
    {
        $batches = $cleanup->incompleteBatches($hours * 3600);

        if ($batches->isEmpty()) {
            $this->info("[DRY RUN] No stuck rehash batches older than {$hours}h.");

            return self::SUCCESS;
        }

        $this->table(
            ['Batch', 'Created', 'Jobs', 'Pending', 'Failed'],
            $batches->map(fn (object $batch) => [
                $batch->id,
                Carbon::createFromTimestamp($batch->created_at)->setTimezone(config('app.timezone'))->format('Y-m-d H:i'),
                $batch->total_jobs,
                $batch->pending_jobs,
                $batch->failed_jobs,
            ])->all(),
        );
        $this->info("[DRY RUN] {$batches->count()} batch(es) would be cancelled (age >= {$hours}h). Nothing changed.");

        return self::SUCCESS;
    }
}
