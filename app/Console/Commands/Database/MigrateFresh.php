<?php

namespace App\Console\Commands\Database;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;

class MigrateFresh extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'migrate:fresh:safe {--p= : Project name to confirm operation in production}
                           {--seed : Seed the database with records}
                           {--drop-views : Drop all views}
                           {--drop-types : Drop all types}
                           {--path=* : The path(s) to the migrations files to be executed}
                           {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
                           {--schema-path= : The path to a schema dump file}
                           {--database= : The database connection to use}
                           {--force : Force the operation to run when in production}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Drop all tables and re-run all migrations with additional safety checks in production";

	/**
	 * Execute the console command.
	 */
	public function handle()
	{
		// Check if we are in production environment
		if (App::environment("production")) {
			// Get project name from the command option
			$projectOption = $this->option("p");

			// Check if project name is provided
			if (empty($projectOption)) {
				$this->error("ERROR: Project name is required with the --p option in production environment.");
				$this->info('Usage: php artisan migrate:fresh:safe --p="project_name"');
				return Command::FAILURE;
			}

			// Get project name from .env
			$actualProjectName = env("APP_NAME");

			// Check if project name matches
			if ($projectOption !== $actualProjectName) {
				$this->error("ERROR: The provided project name does not match the APP_NAME in your .env file.");
				$this->line("Provided name: " . $projectOption);
				$this->line("Actual name: " . $actualProjectName);
				return Command::FAILURE;
			}

			// Confirm the action in production environment
			if (
				!$this->confirm(
					"WARNING: You are about to RESET THE ENTIRE DATABASE in PRODUCTION environment.\nAll data will be lost! Are you sure you want to continue?"
				)
			) {
				$this->info("Operation cancelled.");
				return Command::SUCCESS;
			}

			// Additional warning with 5 second countdown
			$this->warn("Final warning: The database will be reset in:");
			for ($i = 5; $i > 0; $i--) {
				$this->output->write("\r{$i} seconds remaining...");
				sleep(1);
			}
			$this->output->writeln("");

			if (!$this->confirm("Are you ABSOLUTELY SURE you want to continue?")) {
				$this->info("Operation cancelled.");
				return Command::SUCCESS;
			}

			// Create a database backup
			$this->info("Creating database backup before proceeding...");
			$backupResult = $this->call("db:backup");

			if ($backupResult !== Command::SUCCESS) {
				if (!$this->confirm("Database backup failed. Do you want to continue anyway?")) {
					$this->info("Operation cancelled.");
					return Command::SUCCESS;
				}
				$this->warn("Proceeding without backup. This is risky!");
			}
		}

		// Build command parameters
		$parameters = [];

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

		if ($this->option("seed")) {
			$parameters["--seed"] = true;
		}

		if ($this->option("drop-views")) {
			$parameters["--drop-views"] = true;
		}

		if ($this->option("drop-types")) {
			$parameters["--drop-types"] = true;
		}

		// Execute the actual migrate:fresh command
		$this->info("Running database migration...");

		// Use call with the fully qualified class name to avoid recursion
		putenv("LARAVEL_MIGRATE_ORIGINAL=true");
		$exitCode = $this->call("Illuminate\Database\Console\Migrations\FreshCommand", $parameters);
		putenv("LARAVEL_MIGRATE_ORIGINAL=false");

		if ($exitCode === 0) {
			$this->info("Database has been successfully reset and migrated.");
		} else {
			$this->error("Migration failed with exit code: " . $exitCode);
		}

		return $exitCode;
	}
}
