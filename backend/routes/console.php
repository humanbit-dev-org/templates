<?php

use App\Jobs\CheckImportFiles;
use App\Jobs\CheckNewProiezioni;
use App\Jobs\DatabaseDumpScpTransfer;
use Illuminate\Support\Facades\Schedule;

// Scheduled jobs

// Check import files and dispatch processing jobs - every 5 minutes
Schedule::job(new CheckImportFiles())->everyThirtyMinutes()->withoutOverlapping(60);

// Check for new proiezioni and dispatch insertion job - every minute
Schedule::job(new CheckNewProiezioni())->weeklyOn(2, "00:00")->withoutOverlapping(60);

// Database dump SCP transfer - daily at 2:00 AM
// Schedule::job(new DatabaseDumpScpTransfer)
//     ->dailyAt('02:00')
//     ->withoutOverlapping(60);

Schedule::job(new DatabaseDumpScpTransfer())->everyMinute()->withoutOverlapping(60);
