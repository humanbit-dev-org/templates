<?php

namespace App\Console\Commands\Database;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use App\Console\Commands\Database\ConfirmStyle;

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
			@exec('tput cols 2>/dev/null', $output, $exitCode);
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
	protected function formatLine($text, $status, $statusColor = 'yellow', $durationMs = null)
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
			return $prefix . "<fg=gray>" . str_repeat(".", $dotsCount) . "</> <fg=gray>" . $durationMs . "ms</> <fg=" . $statusColor . ";options=bold>" . $status . "</>";
		} else {
			return $prefix . "<fg=gray>" . str_repeat(".", $dotsCount) . "</> <fg=" . $statusColor . ";options=bold>" . $status . "</>";
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
		$this->output->writeln('  <fg=black;bg=yellow>' . str_repeat(' ', $bannerWidth) . '</>  ');
		$this->output->writeln('  <fg=black;bg=yellow>' . $this->centerText('APPLICATION IN PRODUCTION.', $bannerWidth) . '</>  ');
		$this->output->writeln('  <fg=black;bg=yellow>' . str_repeat(' ', $bannerWidth) . '</>  ');
	}

	/**
	 * Center a text within a width
	 */
	protected function centerText($text, $width)
	{
		$padding = max(0, ($width - strlen($text)) / 2);
		return str_repeat(' ', floor($padding)) . $text . str_repeat(' ', ceil($padding));
	}

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
				$this->components->error("Project name is required with the --p option in production environment.");
				$this->components->info('Usage: <fg=green>php artisan migrate:fresh:safe --p="project_name"</>');
				return Command::FAILURE;
			}

			// Get project name from .env
			$actualProjectName = env("APP_NAME");

			// Check if project name matches
			if ($projectOption !== $actualProjectName) {
				$this->components->error("The provided project name does not match the APP_NAME in your .env file.");
				$this->line("Provided name: <fg=red>" . $projectOption . "</>");
				$this->line("Actual name: <fg=green>" . $actualProjectName . "</>");
				return Command::FAILURE;
			}

			// Display production banner (only once at the beginning)
			$this->displayProductionBanner();

			// First confirmation request with the new text
			$confirmStyle = new ConfirmStyle($this->input, $this->output);
			if (!$confirmStyle->askConfirmation("Are you sure you want to run this command?", false)) {
				$this->components->warn("Command cancelled.");
				return Command::SUCCESS;
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
				return Command::SUCCESS;
			}
		}

		// Create database backup
		$result = $this->call("db:backup");
		if ($result !== Command::SUCCESS) {
			$this->components->error("Failed to create database backup. Migration aborted for safety.");
			return Command::FAILURE;
		}
		$this->newLine();

		// Check if we need to preserve data
		$preserveData = $this->option('preserve-data');
		$dataBackup = [];

		if ($preserveData) {
			// No output for backup process - silent operation
			try {
				// Get all table names using a more compatible approach
				$tables = [];
				$connection = DB::connection();

				// Get table names directly with a query that works across Laravel versions
				$tableResults = $connection->select('SHOW TABLES');

				// Convert result to a simple array of table names
				foreach ($tableResults as $tableRow) {
					$tableName = reset($tableRow); // Get first value from the row object (table name)
					$tables[] = $tableName;
				}

				foreach ($tables as $tableName) {
					// Skip Laravel system tables
					if (in_array($tableName, ['migrations', 'failed_jobs', 'password_reset_tokens', 'personal_access_tokens'])) {
						continue;
					}

					// Get the table data
					$data = DB::table($tableName)->get()->toArray();
					if (!empty($data)) {
						$dataBackup[$tableName] = $data;
					}
				}
			} catch (\Exception $e) {
				$this->components->error("Failed to backup table data: " . $e->getMessage());
				if (!$this->confirm("Continue without data preservation?", false)) {
					return Command::FAILURE;
				}
				$preserveData = false; // Disable data preservation if there was an error
			}
		}

		// Prepare the command for direct execution
		$command = "LARAVEL_MIGRATE_ORIGINAL=true php artisan migrate:fresh";

		// Add --ansi to force colors
		$command .= " --ansi";

		// Always add --force to avoid further confirmations
		$command .= " --force";

		// Add options from the original command
		if ($this->option('database')) {
			$command .= " --database=" . escapeshellarg($this->option('database'));
		}

		if ($this->option('path')) {
			$paths = $this->option('path');
			foreach ($paths as $path) {
				$command .= " --path=" . escapeshellarg($path);
			}
		}

		if ($this->option('realpath')) {
			$command .= " --realpath";
		}

		if ($this->option('schema-path')) {
			$command .= " --schema-path=" . escapeshellarg($this->option('schema-path'));
		}

		if ($this->option('seed')) {
			$command .= " --seed";
		}

		if ($this->option('drop-views')) {
			$command .= " --drop-views";
		}

		if ($this->option('drop-types')) {
			$command .= " --drop-types";
		}

		// Execute the command directly, preserving the original output
		passthru($command, $exitCode);

		if ($exitCode !== 0) {
			return Command::FAILURE;
		}

		// Restore data if needed
		if ($preserveData && !empty($dataBackup)) {
			$this->output->writeln($this->formatLine("Restoring table data", "RUNNING", "yellow"));

			try {
				$startTime = microtime(true);

				// Disable foreign key checks to avoid insertion order issues
				DB::statement('SET FOREIGN_KEY_CHECKS=0');

				foreach ($dataBackup as $tableName => $data) {
					// Check if table still exists after migration
					if (!Schema::hasTable($tableName)) {
						$this->components->warn("Table {$tableName} no longer exists after migration. Data cannot be restored.");
						continue;
					}

					// Get current table structure
					$columns = Schema::getColumnListing($tableName);

					foreach ($data as $row) {
						$rowData = [];

						// Only include columns that exist in the new schema
						foreach ((array)$row as $column => $value) {
							if (in_array($column, $columns)) {
								$rowData[$column] = $value;
							}
						}

						// Insert data if we have values
						if (!empty($rowData)) {
							try {
								DB::table($tableName)->insert($rowData);
							} catch (\Exception $e) {
								$this->components->error("Failed to insert row in {$tableName}: " . $e->getMessage());
							}
						}
					}
				}

				// Re-enable foreign key checks
				DB::statement('SET FOREIGN_KEY_CHECKS=1');

				$elapsedTime = round((microtime(true) - $startTime) * 1000);
				$this->output->writeln($this->formatLine("Restoring table data", "DONE", "green", $elapsedTime));
			} catch (\Exception $e) {
				$this->output->writeln($this->formatLine("Restoring table data", "FAILED", "red"));
				$this->components->error("Failed to restore data: " . $e->getMessage());
			}
		}

		return Command::SUCCESS;
	}
}
