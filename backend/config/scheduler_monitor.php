<?php

return [
	/*
    |--------------------------------------------------------------------------
    | Scheduler monitor
    |--------------------------------------------------------------------------
    |
    | Backpack Tools → Scheduled Jobs: the jobs routes/console.php schedules,
    | their run history, and manual runs (dry or real) of the ones
    | ScheduledJobRegistry declares runnable.
    |
    | Off, the backend behaves as before the monitor existed: no menu entry,
    | the pages answer 404, the scheduler is not observed (no run history,
    | the output of its commands left alone) and the history is not pruned.
    | Turn it on only where the scheduled_job_runs migration has run.
    |
    | Set here on purpose, not from env: switching it is a code change.
    |
    */
	"enabled" => false,
];
