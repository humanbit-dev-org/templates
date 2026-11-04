<?php

namespace App\Jobs;

use App\Models\ScheduledJobRun;
use App\Services\ScheduledJobs\ScheduledJobRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

/**
 * A run launched from the Scheduled Jobs page. Off the request on purpose:
 * the commands may take longer than the browser and the proxy wait.
 *
 * A real run takes the scheduler's own overlap mutex when the event declares
 * withoutOverlapping, so a manual run and the scheduled one never overlap:
 * whichever comes second is skipped. Dry runs change nothing and take no lock.
 */
class RunScheduledCommand implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public int $timeout = 300;

	// One attempt: a second one would launch the command again.
	public int $tries = 1;

	public function __construct(public int $runId)
	{
		// Hash tag required: without it the job lands on "queues:default" while
		// the supervisor pops "queues:{default}" on standalone Redis.
		$this->onQueue("{default}");
	}

	public function handle(ScheduledJobRegistry $registry): void
	{
		$run = ScheduledJobRun::find($this->runId);

		if ($run === null || $run->status !== ScheduledJobRun::STATUS_QUEUED) {
			return;
		}

		$definition = $registry->definition($run->name);

		if ($definition === null || ($run->dry_run && !$definition["dry_run"])) {
			$this->close($run, ScheduledJobRun::STATUS_FAILED, null, "{$run->name} non è lanciabile in questa modalità.");

			return;
		}

		$run->update(["status" => ScheduledJobRun::STATUS_RUNNING, "started_at" => now()]);

		$event = $run->dry_run ? null : $registry->event($run->name);
		$locked = $event !== null && $event->withoutOverlapping;

		if ($locked && !$event->mutex->create($event)) {
			$this->close(
				$run,
				ScheduledJobRun::STATUS_SKIPPED,
				null,
				'Saltato: un\'esecuzione schedulata è ancora in corso.'
			);

			return;
		}

		try {
			if (isset($definition["job"]) && $run->dry_run) {
				$this->close($run, ScheduledJobRun::STATUS_SUCCESS, 0, (new ($definition["job"])())->dryRun());

				return;
			}

			if (isset($definition["job"])) {
				dispatch(new ($definition["job"])());
				$this->close(
					$run,
					ScheduledJobRun::STATUS_SUCCESS,
					0,
					class_basename($definition["job"]) . " accodato su Horizon."
				);

				return;
			}

			$buffer = new BufferedOutput();
			$exitCode = Artisan::call(
				$run->name,
				$registry->arguments($run->name, $run->parameters ?? [], $run->dry_run),
				$buffer
			);

			$this->close(
				$run,
				$exitCode === 0 ? ScheduledJobRun::STATUS_SUCCESS : ScheduledJobRun::STATUS_FAILED,
				$exitCode,
				$buffer->fetch()
			);
		} catch (Throwable $e) {
			$this->close($run, ScheduledJobRun::STATUS_FAILED, null, $e->getMessage());
		} finally {
			if ($locked) {
				$event->mutex->forget($event);
			}
		}
	}

	/**
	 * Timeout or a worker crash: handle() never closed the run.
	 */
	public function failed(?Throwable $e): void
	{
		ScheduledJobRun::query()
			->whereKey($this->runId)
			->whereIn("status", [ScheduledJobRun::STATUS_QUEUED, ScheduledJobRun::STATUS_RUNNING])
			->update([
				"status" => ScheduledJobRun::STATUS_FAILED,
				"output" => ScheduledJobRun::tail($e?->getMessage() ?? "Job interrotto."),
				"finished_at" => now(),
			]);
	}

	private function close(ScheduledJobRun $run, string $status, ?int $exitCode, ?string $output): void
	{
		$run->update([
			"status" => $status,
			"exit_code" => $exitCode,
			"output" => ScheduledJobRun::tail($output),
			"finished_at" => now(),
		]);
	}
}
