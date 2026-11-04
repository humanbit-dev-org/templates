<?php

use App\Http\Middleware\RestrictedAccessShield;
use App\Jobs\CheckImportFiles;

return [
	/*
    |--------------------------------------------------------------------------
    | Horizon Name
    |--------------------------------------------------------------------------
    |
    | This name appears in notifications and in the Horizon UI. Unique names
    | can be useful while running multiple instances of Horizon within an
    | application, allowing you to identify the Horizon you're viewing.
    |
    */

	"name" => env("APP_NAME"),

	/*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

	"domain" => null,

	/*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

	"path" => env("HORIZON_PATH", "horizon"),

	/*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

	"use" => env("HORIZON_REDIS_CONNECTION", "queues"),

	/*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

	"prefix" => env("HORIZON_PREFIX", "{horizon}:" . env("REDIS_NAMESPACE", env("APP_ENV", env("APP_NAME"))) . ":"),

	/*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

	"middleware" => ["web", RestrictedAccessShield::class . ":horizon"],

	/*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

	"waits" => [
		"queues:{default}" => 60,
		"queues:{import}" => 120,
		"queues:{scan}" => 10,
		"queues:{export}" => 120,
	],

	/*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

	"trim" => [
		"recent" => 60,
		"pending" => 60,
		"completed" => 60,
		"recent_failed" => 10080,
		"failed" => 10080,
		"monitored" => 10080,
	],

	/*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the list
    | of completed jobs within the Horizon dashboard. This setting may be
    | used to fully remove any noisy jobs from the completed jobs list.
    |
    */

	"silenced" => [CheckImportFiles::class],

	"silenced_tags" => [
		// 'notifications',
	],

	/*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` schedule to define how long to retain metrics.
    |
    */

	"metrics" => [
		"trim_snapshots" => [
			"job" => 24,
			"queue" => 24,
		],
	],

	/*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

	"fast_termination" => false,

	/*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

	"memory_limit" => 64,

	/*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | queued jobs and will be provisioned by Horizon during deployment.
    |
    */

	"defaults" => [
		// 'memory' (MB) is checked only between jobs and must stay below PHP's
		// memory_limit (128M): at 128 the worker hits the fatal error before
		// Horizon can restart it. Supervisors without it default to 128.

		/*
        |----------------------------------------------------------------------
        | Supervisor: Scan Queue
        |----------------------------------------------------------------------
        | Lightweight supervisor for scan jobs (e.g., CheckImportFiles).
        | Single process, restarts every 500 jobs or 1 hour.
        */
		"supervisor-scan" => [
			"connection" => "redis",
			"queue" => ["{scan}"],
			"balance" => "simple",
			"maxProcesses" => 1,
			"maxJobs" => 500,
			"maxTime" => 3600,
			"memory" => 64,
			"tries" => 1,
			"timeout" => 60,
			"nice" => 0,
		],

		/*
        |----------------------------------------------------------------------
        | Supervisor: Default Queue
        |----------------------------------------------------------------------
        | For general application jobs. Separated from import to prevent
        | heavy import jobs from blocking lightweight tasks.
        */
		"supervisor-default" => [
			"connection" => "redis",
			"queue" => ["{default}"],
			"balance" => "auto",
			"autoScalingStrategy" => "time",
			"maxProcesses" => 3,
			"maxJobs" => 50,
			"maxTime" => 1800,
			"memory" => 100,
			"tries" => 3,
			"timeout" => 60,
			"nice" => 0,
		],

		/*
        |----------------------------------------------------------------------
        | Supervisor: Import Queue
        |----------------------------------------------------------------------
        | Dedicated exclusively to CSV import jobs. Isolated and controlled.
        | - maxJobs=1: Worker restarts after each file (releases memory)
        | - maxProcesses=2: Max 2 parallel imports (RAM safety)
        | - memory=300: Watchdog triggers if process exceeds 300MB
        | - nice=5: Slightly lower CPU priority than web requests
        */
		"supervisor-import" => [
			"connection" => "redis",
			"queue" => ["{import}"],
			"balance" => "simple",
			"maxProcesses" => 2,
			"maxJobs" => 1,
			"maxTime" => 0,
			"memory" => 300,
			"tries" => 3,
			"timeout" => 600,
			"nice" => 5,
		],

		"supervisor-rehash" => [
			// Dedicated supervisor for the rehash queue.
			// Isolating it avoids starving other queues during the operation.
			"connection" => "redis",
			"queue" => ["{rehash}"],
			"balance" => "simple",
			// Set this to the number of parallel workers you want.
			// Rule of thumb: (CPU cores / argon2 threads), same logic dell'implementazione precedente.
			"minProcesses" => 1,
			"maxProcesses" => 8,
			"maxJobs" => 100,
			"maxTime" => 3600,
			"memory" => 100,
			"tries" => 3,
			"timeout" => 300,
		],

		/*
        |----------------------------------------------------------------------
        | Supervisor: Export Queue
        |----------------------------------------------------------------------
        | Dedicated to scheduled export jobs (e.g. ExportSelectionFile).
        | Low concurrency, single try, generous timeout for multi-bank CSV runs.
        | maxJobs=1: worker restarts after each export (releases memory).
        */
		"supervisor-export" => [
			"connection" => "redis",
			"queue" => ["{export}"],
			"balance" => "simple",
			"minProcesses" => 1,
			"maxProcesses" => 2,
			"maxJobs" => 1,
			"memory" => 100,
			"tries" => 1,
			"timeout" => 600,
		],
	],

	/*
    |--------------------------------------------------------------------------
    | Environment-Specific Configuration
    |--------------------------------------------------------------------------
    |
    | Override supervisor settings per environment. Production allows more
    | parallel processes; local keeps resource usage minimal.
    |
    */

	"environments" => [
		"production" => [
			"supervisor-scan" => [
				"maxProcesses" => 1,
			],
			"supervisor-default" => [
				"maxProcesses" => 3,
			],
			"supervisor-import" => [
				"maxProcesses" => 2,
			],
			"supervisor-rehash" => [
				"maxProcesses" => 4,
			],
			"supervisor-export" => [
				"maxProcesses" => 2,
			],
		],

		"local" => [
			"supervisor-scan" => [
				"maxProcesses" => 1,
			],
			"supervisor-default" => [
				"maxProcesses" => 2,
			],
			"supervisor-import" => [
				"maxProcesses" => 1,
			],
			"supervisor-rehash" => [
				"maxProcesses" => 2,
			],
			"supervisor-export" => [
				"maxProcesses" => 1,
			],
		],

		/*
        |----------------------------------------------------------------------
        | integration / staging
        |----------------------------------------------------------------------
        | Horizon deploys supervisors only for APP_ENV keys listed here (see
        | `horizon` / `horizon env` and config('horizon.env') ?? config('app.env')).
        */
		"integration" => [
			"supervisor-scan" => [
				"maxProcesses" => 1,
			],
			"supervisor-default" => [
				"maxProcesses" => 3,
			],
			"supervisor-import" => [
				"maxProcesses" => 2,
			],
			"supervisor-rehash" => [
				"maxProcesses" => 4,
			],
			"supervisor-export" => [
				"maxProcesses" => 2,
			],
		],

		"staging" => [
			"supervisor-scan" => [
				"maxProcesses" => 1,
			],
			"supervisor-default" => [
				"maxProcesses" => 3,
			],
			"supervisor-import" => [
				"maxProcesses" => 2,
			],
			"supervisor-rehash" => [
				"maxProcesses" => 4,
			],
			"supervisor-export" => [
				"maxProcesses" => 2,
			],
		],
	],

	/*
    |--------------------------------------------------------------------------
    | File Watcher Configuration
    |--------------------------------------------------------------------------
    |
    | The following list of directories and files will be watched when using
    | the `horizon:listen` command. Whenever any directories or files are
    | changed, Horizon will automatically restart to apply all changes.
    |
    */

	"watch" => [
		"app",
		"bootstrap",
		"config/**/*.php",
		"database/**/*.php",
		"public/**/*.php",
		"resources/**/*.php",
		"routes",
		"composer.lock",
		"composer.json",
		".env",
	],
];
