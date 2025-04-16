<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command("inspire", function () {
	$this->comment(Inspiring::quote());
})
	->purpose("Display an inspiring quote")
	->hourly();

// In production, we completely block potentially dangerous commands
// but allow execution through our security wrappers
if (app()->environment("production") && !getenv("LARAVEL_MIGRATE_ORIGINAL")) {
	// Block migrate:fresh with a warning
	Artisan::command(
		"migrate:fresh {--database=} {--path=*} {--realpath} {--schema-path=} {--seed} {--seeder=} {--step} {--drop-views} {--drop-types} {--force} {--p=}",
		function () {
			$this->components->error("For safety reasons, migrate:fresh is blocked in production.");

			// Build the suggested command with all original options
			$command = 'php artisan migrate:fresh:safe --p="project_name"';

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
						$command .= " --{$option}";
					} elseif (is_array($value) && !empty($value)) {
						foreach ($value as $arrValue) {
							if (!empty($arrValue)) {
								$command .= " --{$option}=\"{$arrValue}\"";
							}
						}
					} elseif ($value !== false && !is_null($value) && $value !== "" && !is_array($value)) {
						$command .= " --{$option}=\"{$value}\"";
					}
				}
			}

			$this->components->info("Use <fg=green>" . $command . "</> instead, which includes safety checks.");

			return 1;
		}
	)->describe("Blocked in production for safety. Use migrate:fresh:safe instead.");

	// Also block the standard migrate command
	Artisan::command(
		"migrate {--database=} {--force} {--path=*} {--realpath} {--pretend} {--seed} {--seeder=} {--step} {--isolated}",
		function () {
			$this->components->error("For safety reasons, standard migrate is blocked in production.");

			// Build the suggested command with all original options
			$command = "php artisan migrate:safe";

			// Add all passed options
			foreach ($this->options() as $option => $value) {
				// Exclude command and Laravel internal options
				if (
					!in_array($option, [
						"command",
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
						$command .= " --{$option}";
					} elseif (is_array($value) && !empty($value)) {
						foreach ($value as $arrValue) {
							if (!empty($arrValue)) {
								$command .= " --{$option}=\"{$arrValue}\"";
							}
						}
					} elseif ($value !== false && !is_null($value) && $value !== "" && !is_array($value)) {
						$command .= " --{$option}=\"{$value}\"";
					}
				}
			}

			$this->components->info("Use <fg=green>" . $command . "</> instead, which includes safety checks.");

			return 1;
		}
	)->describe("Blocked in production for safety. Use migrate:safe instead.");
}
