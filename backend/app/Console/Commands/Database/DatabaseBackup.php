<?php

namespace App\Console\Commands\Database;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
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
		$backupDir = storage_path("backups/database");
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

		$projectName = config("app.name");
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

			// Off the host: a backup that lives on the machine you are about to
			// migrate protects against the mistake, not against losing the host.
			$this->uploadToS3(file_exists($backupPath . ".gz") ? $backupPath . ".gz" : $backupPath);

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
	 * Dump the database with mysqldump.
	 *
	 * Was a hand-written dump in PHP, ~200 lines, while mysqldump sits in the
	 * image. Three things it got wrong that this does not:
	 *
	 *   - it paged with LIMIT/OFFSET, so reaching offset 45.000 made MySQL walk
	 *     45.000 rows again — quadratic on the way down the table;
	 *   - it escaped with addslashes(), which knows nothing about the charset and
	 *     nothing about binary columns, so a dump could come back unreadable;
	 *   - it held each table's INSERTs in a string before writing.
	 *
	 * --single-transaction takes a consistent snapshot without locking writers,
	 * --quick streams row by row, --hex-blob keeps binary data intact. The pulse
	 * tables are skipped, exactly as `make db-dump` skips them.
	 *
	 * The password goes through MYSQL_PWD, never on the command line, where any
	 * other process on the host could read it out of ps.
	 */
	protected function mysqlBackup($backupPath)
	{
		$connection = config("database.default");
		$database = config("database.connections.{$connection}.database");

		$binary = $this->dumpBinary();

		if ($binary === null) {
			$this->components->error("Neither mysqldump nor mariadb-dump is available in this image.");

			return false;
		}

		$arguments = [
			$binary,
			"--host=" . config("database.connections.{$connection}.host"),
			"--port=" . config("database.connections.{$connection}.port"),
			"--user=" . config("database.connections.{$connection}.username"),
			"--single-transaction",
			"--quick",
			"--hex-blob",
			"--no-tablespaces",
			"--default-character-set=utf8mb4",
		];

		// Same exclusions as `make db-dump`: pulse is telemetry, it is regenerated
		// and it dwarfs the rest of the dump.
		foreach (["pulse_aggregates", "pulse_entries", "pulse_values"] as $skip) {
			$arguments[] = "--ignore-table={$database}.{$skip}";
		}

		$arguments[] = $database;

		$this->line($this->formatLine("Dumping with " . basename($binary), "RUNNING"));
		$startTime = microtime(true);

		$process = proc_open(
			$arguments,
			[1 => ["file", $backupPath, "w"], 2 => ["pipe", "w"]],
			$pipes,
			null,
			["MYSQL_PWD" => (string) config("database.connections.{$connection}.password")] + $_ENV
		);

		if (!is_resource($process)) {
			$this->components->error("Could not start {$binary}.");

			return false;
		}

		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		$exit = proc_close($process);

		$duration = round(microtime(true) - $startTime, 2);

		if ($exit !== 0) {
			$this->line($this->formatLine("Dumping with " . basename($binary), "FAILED", "red", $duration));
			$this->components->error(trim($stderr) ?: "mysqldump exited with {$exit}");
			@unlink($backupPath);

			return false;
		}

		$this->line($this->formatLine("Dumping with " . basename($binary), "DONE", "green", $duration));
		$this->newLine();

		return true;
	}

	/**
	 * mariadb-dump is the same tool under its newer name.
	 */
	protected function dumpBinary(): ?string
	{
		foreach (["mysqldump", "mariadb-dump"] as $candidate) {
			$path = trim((string) shell_exec("command -v " . escapeshellarg($candidate) . " 2>/dev/null"));

			if ($path !== "") {
				return $path;
			}
		}

		return null;
	}

	/**
	 * Ship the dump to the same place `make db-dump` puts it.
	 *
	 * The s3 disk is rooted at AWS_PREFIX, so writing to dump/<file> lands on
	 * s3://$AWS_BUCKET/$AWS_PREFIX/dump/<file> — the one location, whether the
	 * dump was taken by the Makefile or from here.
	 */
	protected function s3IsConfigured(): bool
	{
		foreach (["bucket", "key", "secret"] as $key) {
			$value = trim((string) config("filesystems.disks.s3.{$key}"));

			// The env file ships these as commented placeholders ("# Storage
			// bucket name"), which arrive here as ordinary strings — so an
			// emptiness check alone would call this configured.
			if ($value === "" || str_starts_with($value, "#")) {
				return false;
			}
		}

		return true;
	}

	protected function uploadToS3(string $path): void
	{
		if (!$this->s3IsConfigured()) {
			$this->line("<fg=gray>S3 not configured — backup kept locally only.</>");

			return;
		}

		$name = basename($path);

		$this->line($this->formatLine("Uploading to S3", "RUNNING"));
		$startTime = microtime(true);

		$stream = fopen($path, "r");

		try {
			// Streamed, not read into memory: these files run to hundreds of MB.
			Storage::disk("s3")->put("dump/{$name}", $stream);

			// Confirmed, not assumed: a put() that quietly does nothing is worse
			// than one that fails, because it reads as a backup you do not have.
			if (!Storage::disk("s3")->exists("dump/{$name}")) {
				throw new \RuntimeException("the object is not there after upload");
			}
		} catch (\Throwable $e) {
			$this->line($this->formatLine("Uploading to S3", "FAILED", "red"));
			$this->components->error("Upload failed: " . $e->getMessage());

			return;
		} finally {
			if (is_resource($stream)) {
				fclose($stream);
			}
		}

		$duration = round(microtime(true) - $startTime, 2);
		$this->line($this->formatLine("Uploading to S3", "DONE", "green", $duration));
		$this->line("<fg=gray>Remote:</> dump/{$name}");
		$this->newLine();
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
	protected function cleanupOldBackups($backupDir, $keep = 3)
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
