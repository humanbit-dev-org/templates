<?php

namespace App\Console\Commands;

use App\Jobs\Maintenance\RehashPasswordsChunkJob;
use App\Models\User;
use App\Services\Auth\PasswordDerivationService;
use App\Services\Auth\RehashPasswordBatchCleanupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Rehash user passwords via Laravel Horizon job batches.
 *
 * Parallelism is handled by Horizon workers on the "rehash" queue.
 * Each job processes one chunk inside its own DB transaction, so
 * a failure only affects that chunk and Horizon retries it automatically.
 *
 * Progress is tracked via an atomic Redis counter (Cache::increment).
 *
 * Dry-run mode runs fully in-process (no Horizon involvement) to avoid
 * side effects while still giving an accurate count and timing estimate.
 */
class RehashPasswords extends Command
{
	protected $signature = 'auth:rehash-passwords
                            {--dry-run    : Derive only, no DB writes, no Horizon jobs dispatched}
                            {--only-empty : Only process users with NULL or empty password}
                            {--no-wait    : Dispatch the batch and return, without polling its progress}
                            {--chunk=200  : Number of users per job}';

	protected $description = "Rehash all (or empty) user passwords via Horizon batch jobs";

	/**
	 * TTL for the Redis progress key.
	 * Long enough to cover the full run; cleaned up on completion.
	 */
	private const CACHE_TTL_SECONDS = 7200;

	private const STALE_BATCH_AGE_SECONDS = 900;

	private const DISPATCH_LOCK_SECONDS = 1800;

	public function handle(PasswordDerivationService $derivation, RehashPasswordBatchCleanupService $batchCleanup): int
	{
		$dryRun = (bool) $this->option("dry-run");
		$onlyEmpty = (bool) $this->option("only-empty");
		$noWait = (bool) $this->option("no-wait");
		$chunkSize = max(1, (int) $this->option("chunk"));

<<<<<<< HEAD
		// Build base query:
		// - Always exclude Backpack admins (manually set passwords, not HKDF-derived).
		// - Optionally restrict to users with no password set.
		$query = User::whereNull("backpack_role_id")->orderBy("user_id")->select("user_id");
=======
        // Build base query:
        // - Always exclude Backpack admins (manually set passwords, not HKDF-derived).
        // - Optionally restrict to users with no password set.
        $query = User::whereNull('backpack_role_id')
            ->orderBy('username')
            ->select('username');
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		if ($onlyEmpty) {
			$query->where(function ($q) {
				$q->whereNull("password")->orWhere("password", "");
			});
		}

		$total = $query->count();

		if ($total === 0) {
			$this->info("No users to process.");

			return self::SUCCESS;
		}

		$mode = $onlyEmpty ? "users with empty/null password" : "all regular users";
		$this->info(($dryRun ? "[DRY RUN] " : "") . "Found {$total} {$mode} to process.");
		$this->newLine();

		// Dry-run: run fully in-process, no Horizon jobs dispatched.
		// Derives (but does not hash or persist) each user to give
		// an accurate timing estimate with zero side effects.
		if ($dryRun) {
			$bar = $this->output->createProgressBar($total);
			$bar->start();

<<<<<<< HEAD
			$query->chunk($chunkSize, function ($users) use ($bar, $derivation) {
				foreach ($users as $user) {
					// Derive only — no Hash::make(), no DB write.
					$derivation->derive($user->user_id);
					$bar->advance();
				}
			});
=======
            $query->chunk($chunkSize, function ($users) use ($bar, $derivation) {
                foreach ($users as $user) {
                    // Derive only — no Hash::make(), no DB write.
                    $derivation->derive($user->username);
                    $bar->advance();
                }
            });
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

			$bar->finish();
			$this->newLine(2);
			$this->info("[DRY RUN] {$total} users would be updated. No changes made.");

			return self::SUCCESS;
		}

		$dispatchLock = Cache::lock("auth:rehash-passwords:dispatch-lock", self::DISPATCH_LOCK_SECONDS);

		if (!$dispatchLock->get()) {
			$this->warn("Skipped: another auth:rehash-passwords run is already active.");

			return self::SUCCESS;
		}

		try {
			// Real run: build one Job per chunk and dispatch as a Bus Batch to Horizon.
			$cacheKey = "rehash_progress_" . Str::uuid();

			$jobs = [];
			$query->chunk($chunkSize, function ($users) use (&$jobs, $cacheKey) {
				// Extract plain user_id strings; no full model needed in the job payload.
				$ids = $users->pluck("user_id")->all();
				$jobs[] = new RehashPasswordsChunkJob($ids, $cacheKey);
			});

<<<<<<< HEAD
			$totalJobs = count($jobs);
			$this->info("Dispatching {$totalJobs} jobs (chunk size: {$chunkSize}) to the [rehash] queue...");
			$this->newLine();
=======
            $jobs = [];
            $query->chunk($chunkSize, function ($users) use (&$jobs, $cacheKey) {
                // Extract plain username strings; no full model needed in the job payload.
                $ids = $users->pluck('username')->all();
                $jobs[] = new RehashPasswordsChunkJob($ids, $cacheKey);
            });
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

			// Do not cancel recent/in-flight runs from other imports. We only close
			// "zombie" batches that never started any job and are stale enough.
			$cancelledStale = $batchCleanup->cancelStaleUnstartedBatches(self::STALE_BATCH_AGE_SECONDS);
			if ($cancelledStale > 0) {
				$minutes = (int) floor(self::STALE_BATCH_AGE_SECONDS / 60);
				$this->warn("Cancelled {$cancelledStale} stale unstarted rehash batch(es) older than {$minutes} minutes.");
				$this->newLine();
			}

			// If dispatch and workers disagree on Redis key prefix (APP_NAME) or default queue
			// connection, Horizon shows batches while jobs never run — log effective values once.
			$queueRedisConnection = (string) config("queue.connections.redis.connection", "queues");
			$queueRedisPrefix = (string) config(
				"database.redis.{$queueRedisConnection}.prefix",
				config("database.redis.clusters.{$queueRedisConnection}.0.prefix", "(none)")
			);
			$this->line(
				"Queue diagnostics — APP_ENV: <fg=cyan>" .
					config("app.env") .
					"</>" .
					" | APP_NAME: <fg=cyan>" .
					config("app.name") .
					"</>" .
					" | queue.default: <fg=cyan>" .
					config("queue.default") .
					"</>" .
					" | redis queues prefix: <fg=cyan>" .
					$queueRedisPrefix .
					"</>"
			);
			$this->newLine();

			Cache::put($cacheKey, 0, self::CACHE_TTL_SECONDS);

			// Dispatch as a Laravel Bus Batch so we get built-in tracking,
			// cancellation support, and the ->cancelled() guard inside each job.
			// Force the redis queue driver: default QUEUE_CONNECTION may be "database"
			// (see config/queue.php), but Horizon only processes queue.connections.redis.
			$batch = Bus::batch($jobs)
				->name("auth:rehash-passwords")
				->onConnection("redis")
				->onQueue("{rehash}")
				->allowFailures() // partial failures do not cancel the whole batch
				->dispatch();

			$horizonBatchesUrl =
				rtrim(config("app.url"), "/") . "/" . trim(config("horizon.path", "horizon"), "/") . "/batches";

			$this->info("Batch ID: {$batch->id}");
			$this->line(
				"Horizon → Batches: {$horizonBatchesUrl} — filter <fg=cyan>auth:rehash-passwords</> (queue <fg=cyan>rehash</>)."
			);
			$this->newLine();

			// Progress key left to its TTL: the jobs still increment it.
			if ($noWait) {
				Log::channel("import")->info("auth:rehash-passwords dispatched without waiting", [
					"batch_id" => $batch->id,
					"users" => $total,
					"jobs" => $totalJobs,
					"only_empty" => $onlyEmpty,
				]);

				return self::SUCCESS;
			}

			// Fail fast when batch is created in DB but queue payloads are not visible
			// on the expected Redis connection/keyspace. This avoids hanging forever at 0%.
			// Jobs already picked up by a worker live in the :reserved sorted set, not
			// the list — checking llen alone false-fails when workers reserve all jobs
			// within the propagation window.
			$queueRedisConnection = (string) config("queue.connections.redis.connection", "queues");
			$queueRedisPrefix = (string) config("database.redis.{$queueRedisConnection}.prefix", "(missing)");
			$queueListKey = "queues:{rehash}";

			usleep(1_500_000); // allow small propagation window
			$batch = $batch->fresh();
			$queueRedis = Redis::connection($queueRedisConnection);
			$pendingDepth = (int) $queueRedis->llen($queueListKey);
			$reservedDepth = (int) $queueRedis->zcard($queueListKey . ":reserved");
			$delayedDepth = (int) $queueRedis->zcard($queueListKey . ":delayed");

			if (
				$pendingDepth === 0 &&
				$reservedDepth === 0 &&
				$delayedDepth === 0 &&
				$batch->pendingJobs === $batch->totalJobs &&
				$batch->failedJobs === 0
			) {
				$this->error(
					"Batch rows created but no jobs found in Redis (pending, reserved and delayed all empty) " .
						"on expected connection. Likely queue-connection/prefix mismatch between dispatcher and workers."
				);
				$this->line(
					"Queue runtime — redis connection: <fg=cyan>" .
						$queueRedisConnection .
						"</>" .
						" | prefix: <fg=cyan>" .
						$queueRedisPrefix .
						"</>" .
						" | key: <fg=cyan>" .
						$queueListKey .
						"</>" .
						" | pending: <fg=cyan>" .
						$pendingDepth .
						"</>" .
						" | reserved: <fg=cyan>" .
						$reservedDepth .
						"</>" .
						" | delayed: <fg=cyan>" .
						$delayedDepth .
						"</>"
				);
				$this->line(
					"Check on worker + invoker: " .
						"<fg=cyan>config('queue.connections.redis.connection')</>, " .
						"<fg=cyan>config('database.redis.<connection>.prefix')</>, " .
						"<fg=cyan>config('horizon.prefix')</>."
				);

				return self::FAILURE;
			}

			// Poll Redis every 500ms and render a progress bar in the terminal.
			$bar = $this->output->createProgressBar($total);
			$bar->start();

			$lastDone = 0;

			while (true) {
				usleep(500_000); // 0.5 seconds

				$done = (int) Cache::get($cacheKey, 0);

				// Advance the bar only by the delta since last poll.
				if ($done > $lastDone) {
					$bar->advance($done - $lastDone);
					$lastDone = $done;
				}

				// Reload batch state from DB to check completion/failure.
				$batch = $batch->fresh();

				if ($batch->finished()) {
					// Advance bar to 100% in case last increment was not polled.
					$bar->advance($total - $lastDone);
					break;
				}
			}

			$bar->finish();
			$this->newLine(2);

			// Cleanup Redis key regardless of outcome.
			Cache::forget($cacheKey);

			if ($batch->failedJobs > 0) {
				$this->warn("Completed with {$batch->failedJobs} failed job(s). Check Horizon dashboard and import logs.");

				return self::FAILURE;
			}

			$this->info("Done. {$total} users updated.");

			return self::SUCCESS;
		} finally {
			$dispatchLock->release();
		}
	}
}
