<?php

namespace App\Console\Commands\Database;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\ArrayInput;

class BlockMigrateFresh extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = "migrate:fresh {--database=} {--path=*} {--realpath} {--schema-path=} {--seed} {--seeder=} {--step} {--drop-views} {--drop-types} {--force} {--p=}";

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Blocked in production for safety. Use migrate:fresh:safe instead.";

	/**
	 * Execute the console command.
	 */
	public function handle(): int
	{
		// Allow bypass if LARAVEL_MIGRATE_ORIGINAL is set (used by safe commands)
		if (getenv("LARAVEL_MIGRATE_ORIGINAL") || isset($_ENV["LARAVEL_MIGRATE_ORIGINAL"])) {
			// Execute the original migrate:fresh command directly
			$originalCommand = app(\Illuminate\Database\Console\Migrations\FreshCommand::class);
			$originalCommand->setLaravel($this->getLaravel());
			$originalCommand->setApplication($this->getApplication());

			// Create a new input with only valid options for the original command (exclude --p)
			$validOptions = ["--force" => true];

			// Copy valid options from current input (exclude Laravel internal options)
			$excludedOptions = [
				"p",
				"command",
				"force",
				"help",
				"quiet",
				"verbose",
				"version",
				"ansi",
				"no-ansi",
				"no-interaction",
				"env",
			];

			foreach ($this->options() as $option => $value) {
				// Only include options that are valid for the original command
				if (!in_array($option, $excludedOptions)) {
					if ($value === true) {
						$validOptions["--{$option}"] = true;
					} elseif (is_array($value) && !empty($value)) {
						// For array options like --path, pass as array
						$validOptions["--{$option}"] = $value;
					} elseif ($value !== false && !is_null($value) && $value !== "" && !is_array($value)) {
						$validOptions["--{$option}"] = $value;
					}
				}
			}

			$originalCommand->setInput(new ArrayInput($validOptions, $originalCommand->getDefinition()));
			$originalCommand->setOutput($this->output);
			// Initialize components factory
			$originalCommand->components = $this->getLaravel()->make(\Illuminate\Console\View\Components\Factory::class, [
				"output" => $this->output,
			]);
			return $originalCommand->handle();
		}

		$this->error("For safety reasons, migrate:fresh is blocked in production.");

		// Build the suggested command with all original options
		$suggestedCommand = 'php artisan migrate:fresh:safe --p="project_name"';

		// Add all passed options
		foreach ($this->options() as $option => $value) {
			// Exclude command, p (already added) and Laravel internal options
			if (
				!in_array($option, [
					"command",
					"p",
					"help",
					"quiet",
					"verbose",
					"version",
					"ansi",
					"no-ansi",
					"no-interaction",
					"env",
				])
			) {
				if ($value === true) {
					$suggestedCommand .= " --{$option}";
				} elseif (is_array($value) && !empty($value)) {
					foreach ($value as $arrValue) {
						if (!empty($arrValue)) {
							$suggestedCommand .= " --{$option}=\"{$arrValue}\"";
						}
					}
				} elseif ($value !== false && !is_null($value) && $value !== "" && !is_array($value)) {
					$suggestedCommand .= " --{$option}=\"{$value}\"";
				}
			}
		}

		$this->info("Use <fg=green>" . $suggestedCommand . "</> instead, which includes safety checks.");

		return Command::FAILURE;
	}
}
