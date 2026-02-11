<?php

namespace App\Console\Commands\Database;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use App\Console\Commands\Database\ConfirmStyle;
use Illuminate\Support\Facades\Log;

class MigrateFresh extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'migrate:fresh:safe {--p= : Project name to confirm operation in production}
                           {--seed : Seed the database with records}
                           {--preserve-data : Save database data before dropping tables and restore it after migration}
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
	 * Terminal width for formatting output
	 *
	 * @var int
	 */
	protected $terminalWidth = 144;

	/**
	 * Temporary directory for preserve-data dumps
	 *
	 * @var string|null
	 */
	protected $preserveTempDir = null;

	/**
	 * Get the terminal width dynamically
	 *
	 * @return int
	 */
	protected function getTerminalWidth()
	{
		// If already detected, use the stored value
		static $width = null;

		if ($width === null) {
			// Try to get the width with tput
			@exec("tput cols 2>/dev/null", $output, $exitCode);
			if ($exitCode === 0 && !empty($output[0]) && is_numeric($output[0])) {
				$width = (int) $output[0];
			} else {
				// Fallback to the default width if tput is not available
				$width = $this->terminalWidth;
			}
		}

		return $width;
	}

	/**
	 * Format output line with dots and status
	 *
	 * @param string $text
	 * @param string $status
	 * @param string $statusColor
	 * @param int|null $durationMs
	 * @return string
	 */
	protected function formatLine($text, $status, $statusColor = "yellow", $durationMs = null)
	{
		// Get the actual terminal width
		$termWidth = $this->getTerminalWidth();

		// Add a 2-character offset to avoid reaching the border
		$termWidth -= 2;

		// Calculate prefix and suffix length (without formatting)
		$prefix = "  " . $text . " ";
		$prefixLength = strlen($prefix);

		// Calculate suffix length
		$suffixLength = 0;
		if ($durationMs !== null) {
			// Attach "ms" to the numeric value
			$suffixLength = strlen(" " . $durationMs . "ms " . $status);
		} else {
			$suffixLength = strlen(" " . $status);
		}

		// Calculate how many dots are needed to reach exactly the end
		$dotsCount = $termWidth - $prefixLength - $suffixLength;

		// Generate the output with gray dots
		if ($durationMs !== null) {
			return $prefix .
				"<fg=gray>" .
				str_repeat(".", $dotsCount) .
				"</> <fg=gray>" .
				$durationMs .
				"ms</> <fg=" .
				$statusColor .
				";options=bold>" .
				$status .
				"</>";
		} else {
			return $prefix .
				"<fg=gray>" .
				str_repeat(".", $dotsCount) .
				"</> <fg=" .
				$statusColor .
				";options=bold>" .
				$status .
				"</>";
		}
	}

	/**
	 * Display the production environment banner
	 */
	protected function displayProductionBanner()
	{
		// Get the terminal width
		$termWidth = $this->getTerminalWidth();

		// Apply an offset of two spaces on left and right
		$bannerWidth = $termWidth - 4;

		$this->newLine();
		$this->output->writeln("  <fg=black;bg=yellow>" . str_repeat(" ", $bannerWidth) . "</>  ");
		$this->output->writeln(
			"  <fg=black;bg=yellow>" . $this->centerText("APPLICATION IN PRODUCTION.", $bannerWidth) . "</>  "
		);
		$this->output->writeln("  <fg=black;bg=yellow>" . str_repeat(" ", $bannerWidth) . "</>  ");
	}

	/**
	 * Center a text within a width
	 */
	protected function centerText($text, $width)
	{
		$padding = max(0, ($width - strlen($text)) / 2);
		return str_repeat(" ", floor($padding)) . $text . str_repeat(" ", ceil($padding));
	}

	/**
	 * Check if at least one backup file already exists
	 */
	protected function hasExistingBackups($backupDir): bool
	{
		if (!File::exists($backupDir)) {
			return false;
		}

		foreach (File::files($backupDir) as $file) {
			$extension = strtolower($file->getExtension());
			if (in_array($extension, ["sql", "gz"])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine if we are in production with a non-localhost APP_URL
	 */
	protected function isNonLocalProduction(): bool
	{
		if (!App::environment("production")) {
			return false;
		}

		$appUrl = config("app.url");
		if (empty($appUrl)) {
			return false;
		}

		return !preg_match("/(localhost|127\\.0\\.0\\.1|::1)/i", $appUrl);
	}

	/**
	 * Execute the console command.
	 */
	public function handle()
	{
		$preserveData = (bool) $this->option("preserve-data");
		$seedRequested = (bool) $this->option("seed");

		if ($preserveData && $seedRequested) {
			$this->components->error("Options --preserve-data and --seed cannot be used together.");
			$this->components->warn("Please re-run using only one of the two options.");
			return self::FAILURE;
		}

		// STEP 1: ALWAYS backup passwords BEFORE dropping tables
		$this->backupPasswords();

		// Check if we are in production environment
		if (App::environment("production")) {
			// Get project name from the command option
			$projectOption = $this->option("p");

			// Check if project name is provided
			if (empty($projectOption)) {
				$this->components->error("Project name is required with the --p option in production environment.");
				$this->components->info('Usage: <fg=green>php artisan migrate:fresh:safe --p="project_name"</>');
				return self::FAILURE;
			}

			// Get project name from .env
			$actualProjectName = config("app.name");

			// Check if project name matches
			if ($projectOption !== $actualProjectName) {
				$this->components->error("The provided project name does not match the APP_NAME in your .env file.");
				$this->line("Provided name: <fg=red>" . $projectOption . "</>");
				$this->line("Actual name: <fg=green>" . $actualProjectName . "</>");
				return self::FAILURE;
			}

			// Display production banner (only once at the beginning)
			$this->displayProductionBanner();

			// First confirmation request with the new text
			$confirmStyle = new ConfirmStyle($this->input, $this->output);
			if (!$confirmStyle->askConfirmation("Are you sure you want to run this command?", false)) {
				$this->components->warn("Command cancelled.");
				return self::SUCCESS;
			}

			// Additional warning with 5 second countdown
			$this->newLine();
			$this->components->warn("Final warning: The database will be reset in:");
			for ($i = 5; $i > 0; $i--) {
				$this->output->write("\r{$i} seconds remaining...");
				sleep(1);
			}
			$this->output->writeln("");
			$this->newLine();

			// Main warning message moved to the second request
			$this->components->warn(
				"You are about to RESET THE ENTIRE DATABASE in PRODUCTION environment. All data will be lost!"
			);

			// We use our custom class for interactive confirmation
			$confirmStyle = new ConfirmStyle($this->input, $this->output);
			if (!$confirmStyle->askConfirmation("Are you ABSOLUTELY SURE you want to continue?", false)) {
				$this->components->warn("Command cancelled.");
				return self::SUCCESS;
			}
		}

		// Final guard for production on non-localhost URLs
		if ($this->isNonLocalProduction()) {
			$this->components->warn(
				"ENV di produzione con APP_URL non localhost rilevato. Operazione potenzialmente pericolosa in collaudo/produzione!"
			);
			$confirmStyle = new ConfirmStyle($this->input, $this->output);
			if (!$confirmStyle->askConfirmation("Vuoi continuare definitivamente?", false)) {
				$this->components->warn("Command cancelled.");
				return self::SUCCESS;
			}
		}

		// Check if we need to preserve data
		$backupDir = storage_path("backups/database");
		$forcedBackup = false;
		$hasBackups = $this->hasExistingBackups($backupDir);

		// === Prompt con lo STESSO stile (ConfirmStyle) per il backup ===
		if ($preserveData) {
			$this->components->info("Preserve-data flag detected: automatic backup + restore will run.");
			$performBackup = true;
		} elseif (!$hasBackups) {
			$this->components->warn("No existing backups found. Creating an initial backup is mandatory.");
			$performBackup = true;
			$forcedBackup = true;
		} else {
			$confirmStyle = new ConfirmStyle($this->input, $this->output);
			$performBackup = $confirmStyle->askConfirmation("Create a database backup before continuing?", false);
		}

		// Create database backup (se confermato)
		if ($performBackup) {
			$result = $this->call("db:backup");
			if ($result !== self::SUCCESS) {
				$this->components->error("Failed to create database backup. Migration aborted for safety.");
				return self::FAILURE;
			}
			$this->newLine();
		} elseif (!$forcedBackup) {
			$this->components->warn("Skipping database backup as requested.");
			$this->newLine();
		} else {
			$this->newLine();
		}

		$dataBackup = [];

		if ($preserveData) {
			$this->components->info("Preserve-data: dumping current table contents before migration...");
			$this->preserveTempDir = storage_path("app/migrate_preserve/" . Str::uuid());
			File::ensureDirectoryExists($this->preserveTempDir, 0755, true);

			try {
				// Get all table names using a more compatible approach
				$tables = [];
				$connection = DB::connection();

				// Get table names directly with a query that works across Laravel versions
				$tableResults = $connection->select("SHOW TABLES");

				// Convert result to a simple array of table names
				foreach ($tableResults as $tableRow) {
					$tableName = reset($tableRow); // Get first value from the row object (table name)
					$tables[] = $tableName;
				}

				foreach ($tables as $tableName) {
					// Skip Laravel system tables
					if (
						in_array($tableName, [
							"migrations",
							"failed_jobs",
							"password_reset_tokens",
							"personal_access_tokens",
						])
					) {
						continue;
					}

					$tableFile = $this->preserveTempDir . "/" . $tableName . ".jsonl";
					$handle = fopen($tableFile, "w");
					$hasRows = false;

					foreach (DB::table($tableName)->cursor() as $row) {
						$hasRows = true;
						fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL);
					}

					fclose($handle);

					if ($hasRows) {
						$dataBackup[$tableName] = $tableFile;
					} else {
						File::delete($tableFile);
					}
				}
			} catch (\Exception $e) {
				$this->components->error("Failed to backup table data: " . $e->getMessage());

				// Prompt con ConfirmStyle, come gli altri
				$confirmStyle = new ConfirmStyle($this->input, $this->output);
				if (!$confirmStyle->askConfirmation("Continue without data preservation?", false)) {
					return self::FAILURE;
				}
				$preserveData = false; // Disable data preservation if there was an error
				if ($this->preserveTempDir && File::exists($this->preserveTempDir)) {
					File::deleteDirectory($this->preserveTempDir);
					$this->preserveTempDir = null;
				}
			}
			$this->components->info("Preserve-data: collected " . count($dataBackup) . " tables for restoration.");
		}

		// Build migrate:fresh command as separate process to bypass production overrides
		$phpBinary = PHP_BINARY ?: "php";
		$command = "LARAVEL_MIGRATE_ORIGINAL=true " . escapeshellarg($phpBinary) . " artisan migrate:fresh --ansi --force";

		if ($database = $this->option("database")) {
			$command .= " --database=" . escapeshellarg($database);
		}

		$paths = (array) $this->option("path");
		if (!empty($paths)) {
			foreach ($paths as $path) {
				$command .= " --path=" . escapeshellarg($path);
			}
		}

		if ($this->option("realpath")) {
			$command .= " --realpath";
		}

		if ($schemaPath = $this->option("schema-path")) {
			$command .= " --schema-path=" . escapeshellarg($schemaPath);
		}

		if ($this->option("seed")) {
			$command .= " --seed";
		}

		if ($this->option("drop-views")) {
			$command .= " --drop-views";
		}

		if ($this->option("drop-types")) {
			$command .= " --drop-types";
		}

		$this->components->info("Starting migrate:fresh execution...");
		Log::info("[migrate:fresh:safe] Executing command: {$command}");

		$exitCode = 0;
		passthru($command, $exitCode);
		Log::info("[migrate:fresh:safe] migrate:fresh exit code: {$exitCode}");

		if ($exitCode !== 0) {
			$this->components->error("migrate:fresh failed (exit code {$exitCode}). Aborting restore.");
			Log::error("[migrate:fresh:safe] migrate:fresh failed with exit code {$exitCode}");
			return self::FAILURE;
		}

		$this->components->info("migrate:fresh completed successfully.");

		// Restore data if needed
		if ($preserveData && !empty($dataBackup)) {
			$this->output->writeln($this->formatLine("Restoring table data", "RUNNING", "yellow"));

			try {
				$startTime = microtime(true);

				// Disable foreign key checks to avoid insertion order issues
				DB::statement("SET FOREIGN_KEY_CHECKS=0");

				foreach ($dataBackup as $tableName => $filePath) {
					// Check if table still exists after migration
					if (!Schema::hasTable($tableName)) {
						$this->components->warn(
							"Table {$tableName} no longer exists after migration. Data cannot be restored."
						);
						continue;
					}

					// Get current table structure
					$columns = Schema::getColumnListing($tableName);

					if (!File::exists($filePath)) {
						$this->components->warn("Preserve-data file missing for {$tableName}, skipping restore.");
						continue;
					}

					$handle = fopen($filePath, "r");
					$batch = [];

					while (($line = fgets($handle)) !== false) {
						$line = trim($line);
						if ($line === "") {
							continue;
						}

						$row = json_decode($line, true);
						if (!is_array($row)) {
							continue;
						}

						$rowData = [];

						// Only include columns that exist in the new schema
						foreach ($row as $column => $value) {
							if (in_array($column, $columns)) {
								$rowData[$column] = $value;
							}
						}

						if (!empty($rowData)) {
							$batch[] = $rowData;

							if (count($batch) >= 500) {
								DB::table($tableName)->insert($batch);
								$batch = [];
							}
						}
					}

					if (!empty($batch)) {
						DB::table($tableName)->insert($batch);
					}

					fclose($handle);
					File::delete($filePath);
				}

				// Re-enable foreign key checks
				DB::statement("SET FOREIGN_KEY_CHECKS=1");

				$elapsedTime = round((microtime(true) - $startTime) * 1000);
				$this->output->writeln($this->formatLine("Restoring table data", "DONE", "green", $elapsedTime));
				if ($this->preserveTempDir && File::exists($this->preserveTempDir)) {
					File::deleteDirectory($this->preserveTempDir);
					$this->preserveTempDir = null;
				}
			} catch (\Exception $e) {
				$this->output->writeln($this->formatLine("Restoring table data", "FAILED", "red"));
				$this->components->error("Failed to restore data: " . $e->getMessage());
			}
		}

		if ($this->preserveTempDir && File::exists($this->preserveTempDir)) {
			File::deleteDirectory($this->preserveTempDir);
			$this->preserveTempDir = null;
		}

		return self::SUCCESS;
	}

	/**
	 * Backup existing hashed passwords before dropping tables
	 *
	 * @return void
	 */
	private function backupPasswords(): void
	{
		$this->newLine();
		$this->components->info("BACKUP: Saving existing hashed passwords before dropping tables...");

		$cacheFile = storage_path("app/cache/user_passwords_backup.json");

		// Create cache directory if not exists
		$cacheDir = dirname($cacheFile);
		if (!file_exists($cacheDir)) {
			mkdir($cacheDir, 0755, true);
		}

		try {
			// Check if users table exists
			if (!Schema::hasTable("users")) {
				$this->components->warn("   Users table does not exist yet (first migration)");
				$this->components->info("   Skipping password backup");
				$this->newLine();
				return;
			}

			// Check if table has any data
			$totalUsers = DB::table("users")->count();
			$this->line("Current users in database: <fg=cyan>{$totalUsers}</>");

			if ($totalUsers === 0) {
				$this->components->warn("   No users to backup (empty table)");
				$this->newLine();
				return;
			}

			// Get users with bcrypt hashed passwords
			// Use LIKE for better compatibility - bcrypt always starts with $2y$, $2a$, $2b$, or $2x$
			$users = DB::table("users")
				->select("email", "password")
				->where(function ($query) {
					$query
						->where("password", "like", "\$2y\$%")
						->orWhere("password", "like", "\$2a\$%")
						->orWhere("password", "like", "\$2b\$%")
						->orWhere("password", "like", "\$2x\$%");
				})
				->get();

			$this->line("Found <fg=cyan>" . $users->count() . "</> users with bcrypt passwords");

			if ($users->isEmpty()) {
				$this->components->warn("   No hashed passwords found (all plain text or first import)");
				$this->newLine();
				return;
			}

			// Build cache array indexed by email
			$passwordCache = [];
			foreach ($users as $user) {
				if ($user->email) {
					$passwordCache[$user->email] = $user->password;
				}
			}

			// Save to JSON file (survives migrate:fresh!)
			file_put_contents($cacheFile, json_encode($passwordCache, JSON_PRETTY_PRINT));

			$this->line("Backed up <fg=green>" . count($passwordCache) . "</> hashed passwords");
			$this->line("File: <fg=gray>storage/app/cache/user_passwords_backup.json</>");
			$this->line("These passwords will be reused if email matches in CSV");
			$this->newLine();
		} catch (\Exception $e) {
			$this->components->error("Warning: Could not backup passwords: " . $e->getMessage());
			$this->newLine();
		}
	}
}
