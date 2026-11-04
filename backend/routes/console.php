<?php

use App\Models\ScheduledJobRun;
use Illuminate\Support\Facades\Schedule;

// Scheduled jobs

Schedule::command('horizon:snapshot')->everyFiveMinutes();

// pulse:check grows ~2 MB/h and hit memory_limit every ~2 days on staging:
// restart pulse:check / pulse:work hourly (they exit cleanly and are relaunched).
Schedule::command('pulse:restart')->hourly();

// Generate passwords for users still without one (e.g. rows inserted outside Eloquent).
Schedule::command('auth:rehash-passwords --only-empty --no-wait')->dailyAt('03:30')->withoutOverlapping(60);

// Stuck rehash batches (unfinished in job_batches) older than N hours — not on every
// auth:rehash-passwords run, to avoid cancelling a still-valid batch from another import.
Schedule::command('auth:cancel-stuck-rehash-batches --hours=24')->dailyAt('04:15');

// Run history of the Scheduled Jobs page: 30 days. Skipped while the monitor is
// off (config/scheduler_monitor.php), when its table may not even exist.
Schedule::command('model:prune', ['--model' => [ScheduledJobRun::class]])
    ->dailyAt('04:30')
    ->onOneServer()
    ->when(fn () => (bool) config('scheduler_monitor.enabled'));

// Rebuild the sandbox demo data weekly (Saturday 00:00) so it stays aligned
// with the migrations. sandbox.enabled is already false in production and
// integration.
if (config('sandbox.enabled')) {
    Schedule::command('sandbox:seed --force')->weeklyOn(6, '00:00')->withoutOverlapping(60);
}
