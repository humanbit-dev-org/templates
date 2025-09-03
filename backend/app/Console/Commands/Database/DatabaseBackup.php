<?php

namespace App\Console\Commands\Database;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DatabaseBackup extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = "db:backup";

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Create a MySQL/MariaDB database backup";

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
	 * Execute the console command.
	 */
	public function handle()
	{
		$this->components->info("Creating database backup...");

		// Get database connection details from config
		$connection = config("database.default");
		$driver = config("database.connections.{$connection}.driver");

		// Check if we are using MySQL/MariaDB
		if (!in_array($driver, ["mysql", "mariadb"])) {
			$this->components->error("This backup command only supports MySQL/MariaDB databases. Current driver: {$driver}");
			return Command::FAILURE;
		}

		// Check if backup directory exists, create if not
		$backupDir = storage_path("app/db-backups");
		if (!File::exists($backupDir)) {
			$this->line($this->formatLine("Creating backup directory", "RUNNING"));
			$startTime = microtime(true);
			$result = File::makeDirectory($backupDir, 0755, true);
			$endTime = microtime(true);
			$duration = round($endTime - $startTime, 2);
			if ($result) {
				$this->line($this->formatLine("Creating backup directory", "DONE", "green", $duration));
			} else {
				$this->line($this->formatLine("Creating backup directory", "FAILED", "red"));
			}
			$this->newLine();
		}

		// Generate backup filename with timestamp using the correct timezone
		$timezone = "Europe/Rome";
		$this->line("<fg=gray>Using timezone:</> {$timezone}");

		// Create Carbon instance with explicit timezone
		$now = Carbon::now($timezone);
		$this->line("<fg=gray>Current time:</> " . $now->format("Y-m-d H:i:s"));
		$this->newLine();

		$timestamp = $now->format("Y-m-d_H-i-s");

		$projectName = env("APP_NAME", "laravel");
		$safeProjectName = Str::slug($projectName);
		$filename = "{$safeProjectName}_{$timestamp}.sql";
		$backupPath = "{$backupDir}/{$filename}";

		try {
			// Preparing the MySQL backup
			$this->line($this->formatLine("Preparing MySQL backup", "RUNNING"));
			$startTime = microtime(true);
			usleep(200000); // Small pause to show RUNNING
			$endTime = microtime(true);
			$duration = round($endTime - $startTime, 2);
			$this->line($this->formatLine("Preparing MySQL backup", "DONE", "green", $duration));
			$this->newLine();

			// Run the actual backup
			$result = $this->mysqlBackup($backupPath);
			if (!$result) {
				return Command::FAILURE;
			}

			$this->components->info("Database backup created successfully: {$backupPath}");

			// Create a compressed version if the file is large
			$fileSize = round(File::size($backupPath) / 1024 / 1024, 2);
			$this->line("<fg=gray>Backup size:</> {$fileSize} MB");
			$this->newLine();

			if ($fileSize > 5) {
				// If larger than 5MB
				$this->line($this->formatLine("Compressing large backup file", "RUNNING"));
				$startTime = microtime(true);
				$compressResult = $this->compressBackup($backupPath);
				$endTime = microtime(true);
				$duration = round($endTime - $startTime, 2);
				if ($compressResult) {
					$this->line($this->formatLine("Compressing large backup file", "DONE", "green", $duration));
				} else {
					$this->line($this->formatLine("Compressing large backup file", "FAILED", "red"));
				}
				$this->newLine();
			}

			// Cleanup old backups (keep last 5)
			$this->line($this->formatLine("Cleaning up old backup files", "RUNNING"));
			$startTime = microtime(true);
			$deleted = $this->cleanupOldBackups($backupDir);
			$endTime = microtime(true);
			$duration = round($endTime - $startTime, 2);
			$this->line($this->formatLine("Cleaning up old backup files", "DONE", "green", $duration));

			if ($deleted > 0) {
				$this->line("  Deleted {$deleted} old backup files");
			}
			$this->newLine();

			return Command::SUCCESS;
		} catch (\Exception $e) {
			$this->components->error("Backup failed: " . $e->getMessage());
			return Command::FAILURE;
		}
	}

	/**
	 * Perform a MySQL/MariaDB database backup using PHP
	 */
	protected function mysqlBackup($backupPath)
	{
		// Use PHP-based backup directly (no mysqldump dependency)
		return $this->mysqlBackupWithPHP($backupPath);
	}

	/**
	 * Create a MySQL backup using PHP
	 */
	protected function mysqlBackupWithPHP($backupPath)
	{
		$connection = config("database.default");
		$host = config("database.connections.{$connection}.host");
		$port = config("database.connections.{$connection}.port");
		$database = config("database.connections.{$connection}.database");
		$username = config("database.connections.{$connection}.username");
		$password = config("database.connections.{$connection}.password");

		try {
			// Connect to database
			$this->line($this->formatLine("Connecting to database", "RUNNING"));
			$startTime = microtime(true);

			$pdo = new \PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, [
				\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
			]);

			$endTime = microtime(true);
			$duration = round($endTime - $startTime, 2);
			$this->line($this->formatLine("Connecting to database", "DONE", "green", $duration));
			$this->newLine();

			// Get all tables
			$this->line($this->formatLine("Reading database structure", "RUNNING"));
			$startTime = microtime(true);

			$tables = [];
			$result = $pdo->query("SHOW TABLES");
			while ($row = $result->fetch(\PDO::FETCH_NUM)) {
				$tables[] = $row[0];
			}
			$tableCount = count($tables);

			$endTime = microtime(true);
			$duration = round($endTime - $startTime, 2);
			$this->line($this->formatLine("Reading database structure", "DONE", "green", $duration));
			$this->newLine();

			if (empty($tables)) {
				$this->line("No tables found in database. Creating empty backup file.");
				file_put_contents($backupPath, "-- No tables found in database {$database}\n");
				return true;
			}

			$this->line("Found {$tableCount} tables to backup");

			// Write header
			$output = "-- MySQL Database Backup\n";
			$output .= "-- Database: {$database}\n";
			$output .= "-- Generated: " . date("Y-m-d H:i:s") . "\n";
			$output .= "-- Created by DatabaseBackup Command\n\n";
			$output .= "SET FOREIGN_KEY_CHECKS = 0;\n";
			$output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
			$output .= "SET AUTOCOMMIT = 0;\n";
			$output .= "START TRANSACTION;\n\n";
			file_put_contents($backupPath, $output);

			$progress = $this->output->createProgressBar($tableCount);
			$progress->start();

			// Process each table
			foreach ($tables as $table) {
				$tableOutput = "";

				// Get create statement
				$stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
				$createRow = $stmt->fetch(\PDO::FETCH_NUM);
				$tableOutput .= "-- Table structure for table `{$table}`\n";
				$tableOutput .= "DROP TABLE IF EXISTS `{$table}`;\n";
				$tableOutput .= $createRow[1] . ";\n\n";

				// Get data count
				$countStmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
				$rowCount = $countStmt->fetchColumn();

				if ($rowCount > 0) {
					$tableOutput .= "-- Dumping data for table `{$table}`\n";
					$tableOutput .= "LOCK TABLES `{$table}` WRITE;\n";

					// Get data in chunks to avoid memory issues
					$limit = 1000;
					$offset = 0;

					while ($offset < $rowCount) {
						$result = $pdo->query("SELECT * FROM `{$table}` LIMIT {$limit} OFFSET {$offset}");
						$rows = $result->fetchAll(\PDO::FETCH_NUM);

						if (!empty($rows)) {
							$tableOutput .= "INSERT INTO `{$table}` VALUES ";
							$rowValues = [];

							foreach ($rows as $row) {
								$values = [];
								foreach ($row as $value) {
									if (is_null($value)) {
										$values[] = "NULL";
									} elseif (is_numeric($value)) {
										$values[] = $value;
									} else {
										$values[] = "'" . addslashes($value) . "'";
									}
								}
								$rowValues[] = "(" . implode(",", $values) . ")";
							}

							$tableOutput .= implode(",\n", $rowValues) . ";\n";
						}

						$offset += $limit;
					}

					$tableOutput .= "UNLOCK TABLES;\n";
				}

				$tableOutput .= "\n";

				// Write to file in batches to avoid memory issues
				file_put_contents($backupPath, $tableOutput, FILE_APPEND);

				$progress->advance();
			}

			$progress->finish();
			$this->newLine(2);

			// Write footer
			$footer = "COMMIT;\n";
			$footer .= "SET FOREIGN_KEY_CHECKS = 1;\n";
			$footer .= "-- Backup completed successfully\n";
			file_put_contents($backupPath, $footer, FILE_APPEND);

			return true;
		} catch (\Exception $e) {
			throw new \Exception("Database backup failed: " . $e->getMessage());
		}
	}

	/**
	 * Compress the backup file using gzip
	 */
	protected function compressBackup($backupPath)
	{
		$startTime = microtime(true);

		// Execute the compression command
		$command = "gzip -9 -f " . escapeshellarg($backupPath);

		$process = proc_open(
			$command,
			[
				0 => ["pipe", "r"],
				1 => ["pipe", "w"],
				2 => ["pipe", "w"],
			],
			$pipes
		);

		if (!is_resource($process)) {
			$this->components->warn("Could not compress backup file: failed to start process");
			return false;
		}

		foreach ($pipes as $pipe) {
			fclose($pipe);
		}

		$exitCode = proc_close($process);

		$endTime = microtime(true);
		$this->line("<fg=gray>Compression completed in </>" . round($endTime - $startTime, 2) . " seconds");

		if (file_exists($backupPath . ".gz")) {
			$this->line("Backup compressed: " . $backupPath . ".gz");
			return true;
		} else {
			$this->components->warn("Could not compress backup file");
			return false;
		}
	}

	/**
	 * Clean up old backups, keeping only the most recent ones
	 */
	protected function cleanupOldBackups($backupDir, $keep = 5)
	{
		// Collect all backup files and sort them by filename (which contains timestamp)
		$backupFiles = collect(File::files($backupDir))->sortByDesc(function ($file) {
			return $file->getFilename();
		});

		if ($backupFiles->count() <= $keep) {
			return 0;
		}

		$deleted = 0;
		$backupFiles->slice($keep)->each(function ($file) use (&$deleted) {
			File::delete($file->getPathname());
			$deleted++;
		});

		return $deleted;
	}
}
