<?php

namespace App\Console\Commands\Database;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Helper\Table;
use App\Console\Commands\Database\ConfirmStyle;
use App\Console\Commands\Database\MenuStyle;

class DatabaseRestore extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'db:restore 
                            {--p= : Project name to confirm operation in production}
                            {--latest : Use the most recent backup file (will ask for confirmation)}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Restore database from a backup file";

	/**
	 * Backup directory path
	 *
	 * @var string
	 */
	protected $backupDir;

	/**
	 * List of available backup files
	 *
	 * @var array
	 */
	protected $backupFiles = [];

	/**
	 * Execute the console command.
	 */
	public function handle()
	{
		$this->backupDir = storage_path("app/db-backups");

		// Check if we are in production environment and verify project name early
		if (App::environment("production")) {
			// Verify project name before doing anything else
			if (!$this->verifyProjectName()) {
				return Command::FAILURE;
			}
		}

		// Load backup files without showing detailed output
		$this->loadBackupFiles();

		if (empty($this->backupFiles)) {
			$this->components->error("No backup files found in {$this->backupDir}");
			return Command::FAILURE;
		}

		// Show a summary instead of multiple separate messages
		$environment = App::environment("production") ? "PRODUCTION" : "development";
		$this->components->info(
			"Database Restore: Found " . count($this->backupFiles) . " backup files in {$environment} environment"
		);

		// Check if we are in production environment
		if (App::environment("production")) {
			// Display production banner only once at the beginning
			$this->displayProductionBanner();

			// Show warnings for production environment
			if (!$this->confirmProductionRestore()) {
				$this->components->warn("Command cancelled.");
				return Command::SUCCESS;
			}
		}

		// Get the backup file to restore
		$backupFile = $this->getBackupFileToRestore($this->backupFiles);
		if (empty($backupFile)) {
			// Non mostriamo il messaggio qui perché è già mostrato nei metodi interni
			return Command::SUCCESS;
		}

		// Perform the restore
		return $this->restoreDatabase($backupFile);
	}

	/**
	 * Load all backup files from the backup directory
	 */
	protected function loadBackupFiles()
	{
		// Check if backup directory exists
		if (!File::exists($this->backupDir)) {
			$this->components->error("Backup directory not found: {$this->backupDir}");
			return;
		}

		// Get all files from the backup directory
		$allFiles = File::files($this->backupDir);

		// Filter to only .sql and .gz files
		$backupFiles = [];
		$uniqueFilenames = [];

		foreach ($allFiles as $file) {
			$filename = $file->getFilename();
			$extension = pathinfo($filename, PATHINFO_EXTENSION);

			if (($extension === "sql" || $extension === "gz") && !in_array($filename, $uniqueFilenames)) {
				$uniqueFilenames[] = $filename;
				$backupFiles[] = $file;
			}
		}

		if (empty($backupFiles)) {
			return;
		}

		// Sort array of files by filename in reverse order
		// This works because our naming convention has date_time in filename
		usort($backupFiles, function ($a, $b) {
			return strcmp($b->getFilename(), $a->getFilename());
		});

		$this->backupFiles = $backupFiles;
	}

	/**
	 * Get the file creation time, falling back to modified time if not available
	 */
	protected function getFileCreationTime($filePath)
	{
		// Try to get creation time (not available on all systems)
		if (function_exists("filectime")) {
			$ctime = filectime($filePath);
			if ($ctime !== false) {
				return $ctime;
			}
		}

		// Fall back to modification time
		return filemtime($filePath);
	}

	/**
	 * Verify project name in production
	 */
	protected function verifyProjectName()
	{
		// Get project name from the command option
		$projectOption = $this->option("p");

		// Check if project name is provided
		if (empty($projectOption)) {
			$this->components->error("Project name is required with the --p option in production environment.");
			$this->components->info('Usage: <fg=green>php artisan db:restore --p="project_name"</>');
			return false;
		}

		// Get project name from .env
		$actualProjectName = env("APP_NAME");

		// Check if project name matches
		if ($projectOption !== $actualProjectName) {
			$this->components->error("The provided project name does not match the APP_NAME in your .env file.");
			$this->line("Provided name: <fg=red>" . $projectOption . "</>");
			$this->line("Actual name: <fg=green>" . $actualProjectName . "</>");
			return false;
		}

		return true;
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
	 * Show warnings for production environment and ask for confirmation
	 */
	protected function confirmProductionRestore()
	{
		$confirm = new ConfirmStyle($this->input, $this->output);

		// Ask for confirmation without showing the warning initially
		if ($confirm->askConfirmation("Are you sure you want to run this command?", false)) {
			// Show the warning only after confirmation, instead of the info message
			$this->components->warn("All current data will be OVERWRITTEN and cannot be recovered.");
			return true;
		}

		return false;
	}

	/**
	 * Get backup file to restore
	 */
	protected function getBackupFileToRestore(array $backup_files)
	{
		if (empty($backup_files) || count($backup_files) === 0) {
			$this->error("No backup files found in " . $this->backupDir);
			return null;
		}

		if ($this->option("latest")) {
			$latestFile = $backup_files[0];
			$formattedDate = $this->extractDateFromFilename($latestFile->getFilename());
			$fileSize = $this->formatBytes($latestFile->getSize());

			$this->info("Most recent backup file:");
			$this->table(
				["Name", "Date", "Size"],
				[[$latestFile->getFilename(), "<fg=green>{$formattedDate}</>", $fileSize]]
			);

			$confirmStyle = new ConfirmStyle($this->input, $this->output);

			if (!$confirmStyle->askConfirmation("Do you want to restore this backup?", true)) {
				// Terminiamo il comando se l'utente seleziona "No"
				$this->components->warn("Command cancelled.");
				return null;
			}

			return $latestFile->getPathname();
		}

		return $this->selectBackupFile($backup_files);
	}

	/**
	 * Let the user select a backup file from the list
	 */
	protected function selectBackupFile(array $backup_files)
	{
		// Ensure backup files are unique based on name
		$uniqueFiles = [];
		$uniqueNames = [];

		foreach ($backup_files as $file) {
			$filename = $file->getFilename();
			if (!in_array($filename, $uniqueNames)) {
				$uniqueNames[] = $filename;
				$uniqueFiles[] = $file;
			}
		}

		$backup_files = $uniqueFiles;

		$options = [];
		$fileDetails = [];

		// The first file is always the most recent because they are already sorted by name/date
		$newestFile = $backup_files[0]->getFilename();

		foreach ($backup_files as $index => $file) {
			$filename = $file->getFilename();
			$formattedDate = $this->extractDateFromFilename($filename);
			$fileSize = $this->formatBytes($file->getSize());

			// Add a visual indicator only for the most recent file
			$isNewest = $filename === $newestFile;
			$prefix = $isNewest ? "<fg=green>⬤</> " : "  ";

			// If it's the most recent file, also color the date in green
			$displayDate = $isNewest ? "<fg=green>{$formattedDate}</>" : $formattedDate;

			$displayText = sprintf("%s%s | %s | %s", $prefix, $filename, $displayDate, $fileSize);

			$options[] = $displayText;
			$fileDetails[] = [
				"path" => $file->getPathname(),
				"name" => $filename,
				"date" => $formattedDate,
				"size" => $fileSize,
				"isNewest" => $isNewest,
			];
		}

		if (empty($options)) {
			$this->error("No backup files available.");
			return null;
		}

		$menuStyle = new MenuStyle($this->input, $this->output);
		$this->info("Select a backup file to restore:");
		$selectedIndex = $menuStyle->select("Use arrow keys and press Enter to select", $options);

		if ($selectedIndex < 0 || $selectedIndex >= count($backup_files)) {
			$this->error("Invalid selection.");
			return null;
		}

		// Show details of the selected file
		$selected = $fileDetails[$selectedIndex];

		// In the table, add an indication in gray if it's the most recent file
		// and color the date in green if it's the most recent file
		$name = $selected["name"];
		$date = $selected["date"];
		if ($selected["isNewest"]) {
			$name = $name;
			$date = "<fg=green>{$date}</>";
		}

		$this->info("Selected backup file:");
		$this->table(["Name", "Date", "Size"], [[$name, $date, $selected["size"]]]);

		// Ask for confirmation before proceeding
		$confirmStyle = new ConfirmStyle($this->input, $this->output);

		if (!$confirmStyle->askConfirmation("Do you want to restore this backup?", true)) {
			$this->components->warn("Command cancelled.");
			return null;
		}

		return $backup_files[$selectedIndex]->getPathname();
	}

	/**
	 * Extract date from backup filename
	 * Expected format: project_YYYY-MM-DD_HH-MM-SS.sql
	 */
	protected function extractDateFromFilename($filename)
	{
		// Expected format: templates_2025-04-09_17-14-39.sql
		if (preg_match("/(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2}-\d{2})/", $filename, $matches)) {
			$date = $matches[1];
			$time = str_replace("-", ":", $matches[2]);
			return "$date $time";
		}
		return "Date not available";
	}

	/**
	 * Format file size with unit
	 */
	protected function formatBytes($bytes)
	{
		$units = ["B", "KB", "MB", "GB", "TB"];
		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);
		$bytes /= pow(1024, $pow);
		return round($bytes, 2) . " " . $units[$pow];
	}

	/**
	 * Restore the database from backup file
	 */
	protected function restoreDatabase($backupFile)
	{
		// Get database connection details from config
		$connection = config("database.default");
		$host = config("database.connections.{$connection}.host");
		$port = config("database.connections.{$connection}.port");
		$database = config("database.connections.{$connection}.database");
		$username = config("database.connections.{$connection}.username");
		$password = config("database.connections.{$connection}.password");

		$filename = basename($backupFile);
		$fileExt = pathinfo($filename, PATHINFO_EXTENSION);

		// Check if we're dealing with a compressed file
		$isCompressed = $fileExt === "gz";

		$this->components->info("Restoring database from backup: " . $filename);

		// Check if mysql client is available
		$this->line($this->formatLine("Checking MySQL client availability", "RUNNING"));
		$startTime = microtime(true);
		exec("which mysql", $output, $returnVar);
		$mysqlAvailable = $returnVar === 0;
		$endTime = microtime(true);
		$duration = round($endTime - $startTime, 2) * 1000;
		$this->line($this->formatLine("Checking MySQL client availability", "DONE", "green", $duration));
		$this->newLine();

		if (!$mysqlAvailable) {
			// Try to install mysql client
			$installSuccess = false;

			if ($this->isDebianBased()) {
				$this->line($this->formatLine("Installing MySQL client (Debian/Ubuntu)", "RUNNING"));
				$startTime = microtime(true);
				system("apt-get update && apt-get install -y default-mysql-client", $installResult);
				$installSuccess = $installResult === 0;
				$endTime = microtime(true);
				$duration = round($endTime - $startTime, 2) * 1000;
				if ($installSuccess) {
					$this->line($this->formatLine("Installing MySQL client (Debian/Ubuntu)", "DONE", "green", $duration));
				} else {
					$this->line($this->formatLine("Installing MySQL client (Debian/Ubuntu)", "FAILED", "red"));
				}
				$this->newLine();
			} elseif ($this->isRedHatBased()) {
				$this->line($this->formatLine("Installing MySQL client (RedHat/CentOS)", "RUNNING"));
				$startTime = microtime(true);
				system("yum install -y mysql", $installResult);
				$installSuccess = $installResult === 0;
				$endTime = microtime(true);
				$duration = round($endTime - $startTime, 2) * 1000;
				if ($installSuccess) {
					$this->line($this->formatLine("Installing MySQL client (RedHat/CentOS)", "DONE", "green", $duration));
				} else {
					$this->line($this->formatLine("Installing MySQL client (RedHat/CentOS)", "FAILED", "red"));
				}
				$this->newLine();
			}

			if (!$installSuccess) {
				$this->components->error("Could not install MySQL client. Please install it manually.");
				return Command::FAILURE;
			}
		}

		// Final confirmation
		$confirmStyle = new ConfirmStyle($this->input, $this->output);

		if (!$confirmStyle->askConfirmation("Ready to restore database from {$filename}. Continue?", true)) {
			$this->components->warn("Command cancelled.");
			return Command::SUCCESS;
		}

		// Format the output line in the same style as DatabaseBackup
		$this->line($this->formatLine("Restoring database", "RUNNING"));
		$startTime = microtime(true);

		// Build the mysql command
		$mysqlCommand = sprintf(
			"mysql -h %s -P %s -u %s",
			escapeshellarg($host),
			escapeshellarg($port),
			escapeshellarg($username)
		);

		// Add password if it exists
		if (!empty($password)) {
			$mysqlCommand .= " -p" . escapeshellarg($password);
		}

		// Add database name
		$mysqlCommand .= " " . escapeshellarg($database);

		// Create the full command based on whether the file is compressed or not
		if ($isCompressed) {
			$command = "gzip -dc " . escapeshellarg($backupFile) . " | " . $mysqlCommand;
		} else {
			$command = $mysqlCommand . " < " . escapeshellarg($backupFile);
		}

		// Execute the restore process with error handling
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
			$this->line($this->formatLine("Restoring database", "FAILED", "red"));
			$this->components->error("Failed to start restore process");
			return Command::FAILURE;
		}

		// Close STDIN
		fclose($pipes[0]);

		// Capture output and errors
		$output = stream_get_contents($pipes[1]);
		$error = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		// Get exit code
		$exitCode = proc_close($process);

		$endTime = microtime(true);
		$duration = round($endTime - $startTime, 2);

		if ($exitCode !== 0) {
			$this->line($this->formatLine("Restoring database", "FAILED", "red"));
			$this->components->error("Failed to restore database:");
			$this->newLine();
			$this->line("<fg=red>" . ($error ? $error : "Unknown error") . "</fg=red>");
			return Command::FAILURE;
		}

		$this->line($this->formatLine("Restoring database", "DONE", "green", $duration * 1000));
		$this->newLine();
		$this->components->info("Database has been successfully restored from {$filename}");
		$this->line("<fg=gray>Restore completed in {$duration} seconds</>");

		return Command::SUCCESS;
	}

	/**
	 * Format output line with dots and status
	 * Consistent with the style in DatabaseBackup
	 */
	protected function formatLine($text, $status, $statusColor = "yellow", $durationMs = null)
	{
		// Get the terminal width dynamically
		$termWidth = $this->getTerminalWidth();

		// Add a 2-character offset to avoid hitting the edge
		$termWidth -= 2;

		// Calculate prefix and suffix length (without formatting)
		$prefix = "  " . $text . " ";
		$prefixLength = strlen($prefix);

		// Calculate suffix length
		$suffixLength = 0;
		if ($durationMs !== null) {
			// Add "ms" to the numeric value
			$suffixLength = strlen(" " . $durationMs . "ms " . $status);
		} else {
			$suffixLength = strlen(" " . $status);
		}

		// Calculate how many dots are needed to reach the exact end
		$dotsCount = $termWidth - $prefixLength - $suffixLength;

		// Generate output with gray dots
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
	 * Get the terminal width dynamically
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
				// Fallback to default width if tput is not available
				$width = 144;
			}
		}

		return $width;
	}

	/**
	 * Check if we're on a Debian/Ubuntu based system
	 */
	protected function isDebianBased()
	{
		if (file_exists("/etc/debian_version")) {
			return true;
		}

		exec("command -v apt-get", $output, $returnVar);
		return $returnVar === 0;
	}

	/**
	 * Check if we're on a RedHat/CentOS based system
	 */
	protected function isRedHatBased()
	{
		if (file_exists("/etc/redhat-release")) {
			return true;
		}

		exec("command -v yum", $output, $returnVar);
		return $returnVar === 0;
	}
}
