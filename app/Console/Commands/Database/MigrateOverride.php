<?php

namespace App\Console\Commands\Database;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;

class MigrateOverride extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'migrate:safe {--database= : The database connection to use}
                {--force : Force the operation to run when in production}
                {--path=* : The path(s) to the migrations files to be executed}
                {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
                {--schema-path= : The path to a schema dump file}
                {--pretend : Dump the SQL queries that would be run}
                {--seed : Indicates if the seed task should be re-run}
                {--step : Force the migrations to be run so they can be rolled back individually}
                {--isolated : Do not run the command if another migration command is already running}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Run the database migrations with automatic backup";

	/**
	 * Execute the console command.
	 */
	public function handle()
	{
		// Check if we are in production environment
		if (App::environment("production")) {
			$this->info("Running migrate in production environment with automatic backup...");

			// Create database backup
			$this->info("Creating database backup before migration...");
			$backupResult = $this->call("db:backup");

			if ($backupResult !== Command::SUCCESS) {
				if (!$this->confirm("Database backup failed. Do you want to continue anyway?")) {
					$this->info("Migration cancelled.");
					return Command::FAILURE;
				}
				$this->warn("Proceeding without backup. This is risky!");
			}
		}

		// Build command string for passthru
		$parameters = [];

		// Add all options as parameters
		if ($this->option("database")) {
			$parameters["--database"] = $this->option("database");
		}

		if ($this->option("force")) {
			$parameters["--force"] = true;
		}

		if ($this->option("path")) {
			$parameters["--path"] = $this->option("path");
		}

		if ($this->option("realpath")) {
			$parameters["--realpath"] = true;
		}

		if ($this->option("schema-path")) {
			$parameters["--schema-path"] = $this->option("schema-path");
		}

		if ($this->option("pretend")) {
			$parameters["--pretend"] = true;
		}

		if ($this->option("seed")) {
			$parameters["--seed"] = true;
		}

		if ($this->option("step")) {
			$parameters["--step"] = true;
		}

		if ($this->option("isolated")) {
			$parameters["--isolated"] = true;
		}

		$this->info("Running database migration...");

		// Use call with the fully qualified class name to avoid recursion
		putenv("LARAVEL_MIGRATE_ORIGINAL=true");
		$exitCode = $this->call("Illuminate\Database\Console\Migrations\MigrateCommand", $parameters);
		putenv("LARAVEL_MIGRATE_ORIGINAL=false");

		if ($exitCode === 0) {
			$this->info("Migration completed successfully.");
		} else {
			$this->error("Migration failed with exit code: " . $exitCode);
		}

		return $exitCode;
	}
}
