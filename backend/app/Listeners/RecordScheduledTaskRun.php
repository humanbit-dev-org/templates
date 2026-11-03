<?php

namespace App\Listeners;

use App\Models\ScheduledJobRun;
use App\Services\ScheduledJobs\ScheduledJobRegistry;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes the history of the runs schedule:run starts, for the Scheduled Jobs
 * page: Laravel keeps none of its own.
 *
 * A command whose output goes nowhere (the default /dev/null) is pointed at a
 * temporary file for the run, so its last lines end up in the history.
 * Recording never stops the scheduler: a failed write is logged and dropped.
 * With config/scheduler_monitor.php off, nothing happens at all.
 *
 * Event order from ScheduleRunCommand: Starting, then Finished (also on a
 * non-zero exit, followed by Failed), or Starting then Failed alone when a
 * job callback throws.
 */
class RecordScheduledTaskRun
{
    /** @var array<int, array{run: int, file: string|null}> keyed by spl_object_id of the task */
    private static array $runs = [];

    public function handleStarting(ScheduledTaskStarting $event): void
    {
        // Off: the task runs untouched. Finished and Failed find no entry and return.
        if (! config('scheduler_monitor.enabled')) {
            return;
        }

        $task = $event->task;
        $file = null;

        if (! $task instanceof CallbackEvent && $task->output === $task->getDefaultOutput()) {
            $file = storage_path('logs/scheduled-run-'.Str::uuid().'.log');
            $task->sendOutputTo($file);
        }

        try {
            $run = ScheduledJobRun::create([
                'name' => ScheduledJobRegistry::nameOf($task),
                'trigger' => ScheduledJobRun::TRIGGER_SCHEDULE,
                'status' => ScheduledJobRun::STATUS_RUNNING,
                'started_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Scheduled run not recorded', ['task' => $task->getSummaryForDisplay(), 'error' => $e->getMessage()]);

            return;
        }

        self::$runs[spl_object_id($task)] = ['run' => $run->id, 'file' => $file];
    }

    public function handleFinished(ScheduledTaskFinished $event): void
    {
        $task = $event->task;

        $status = match (true) {
            $task->skippedBecauseOverlapping => ScheduledJobRun::STATUS_SKIPPED,
            (int) $task->exitCode === 0 => ScheduledJobRun::STATUS_SUCCESS,
            default => ScheduledJobRun::STATUS_FAILED,
        };

        $output = $task->skippedBecauseOverlapping
            ? 'Saltato: un\'esecuzione precedente è ancora in corso.'
            : null;

        $this->close($task, $status, $output);
    }

    /**
     * Only reached with the entry still open when a job callback threw: a
     * non-zero exit was already closed as failed by handleFinished.
     */
    public function handleFailed(ScheduledTaskFailed $event): void
    {
        $this->close($event->task, ScheduledJobRun::STATUS_FAILED, $event->exception->getMessage());
    }

    private function close(Event $task, string $status, ?string $message): void
    {
        $entry = self::$runs[spl_object_id($task)] ?? null;

        if ($entry === null) {
            return;
        }

        unset(self::$runs[spl_object_id($task)]);

        $output = $message;

        if ($entry['file'] !== null) {
            $captured = is_file($entry['file']) ? (string) file_get_contents($entry['file']) : '';
            @unlink($entry['file']);
            // schedule:run processes are short-lived, but the event object should
            // not keep pointing at a file that no longer exists.
            $task->sendOutputTo($task->getDefaultOutput());
            $output = trim($captured."\n".($message ?? ''));
        }

        try {
            ScheduledJobRun::query()->whereKey($entry['run'])->update([
                'status' => $status,
                'exit_code' => $task->exitCode,
                'output' => ScheduledJobRun::tail($output),
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Scheduled run not closed', ['run' => $entry['run'], 'error' => $e->getMessage()]);
        }
    }
}
