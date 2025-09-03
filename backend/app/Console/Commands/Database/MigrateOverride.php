<?php

namespace App\Console\Commands\Database;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use App\Console\Commands\Database\ConfirmStyle;

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
	 * Execute the console command.
	 */
	public function handle()
	{
		// Check if we are in production environment
		if (App::environment("production")) {
			// Display production banner immediately at the start
			$this->displayProductionBanner();

			// We use our custom class for interactive confirmation
			$confirmStyle = new ConfirmStyle($this->input, $this->output);
			if (!$confirmStyle->askConfirmation("Do you want to continue with the migration?", false)) {
				$this->components->warn("Command cancelled.");
				return Command::FAILURE;
			}

			// Create database backup
			$result = $this->call("db:backup");
			if ($result !== Command::SUCCESS) {
				$this->components->error("Failed to create database backup. Migration aborted for safety.");
				return Command::FAILURE;
			}
			$this->newLine();
		} else {
			$this->components->info("Running database migration...");
		}

		// Prepare the command for direct execution
		$command = "LARAVEL_MIGRATE_ORIGINAL=true php artisan migrate";

		// Add --ansi to force colors
		$command .= " --ansi";

		// Always add --force to avoid further confirmations
		$command .= " --force";

		// Add options from the original command
		if ($this->option("database")) {
			$command .= " --database=" . escapeshellarg($this->option("database"));
		}

		if ($this->option("path")) {
			$paths = $this->option("path");
			foreach ($paths as $path) {
				$command .= " --path=" . escapeshellarg($path);
			}
		}

		if ($this->option("realpath")) {
			$command .= " --realpath";
		}

		if ($this->option("schema-path")) {
			$command .= " --schema-path=" . escapeshellarg($this->option("schema-path"));
		}

		if ($this->option("pretend")) {
			$command .= " --pretend";
		}

		if ($this->option("seed")) {
			$command .= " --seed";
		}

		if ($this->option("step")) {
			$command .= " --step";
		}

		if ($this->option("isolated")) {
			$command .= " --isolated";
		}

		// Execute the command directly, preserving the original output
		passthru($command, $exitCode);

		if ($exitCode !== 0) {
			return Command::FAILURE;
		}

		return Command::SUCCESS;
	}
}
