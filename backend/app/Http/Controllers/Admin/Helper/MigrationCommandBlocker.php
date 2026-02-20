<?php

namespace App\Http\Controllers\Admin\Helper;

use App\Console\Commands\Database\BlockMigrate;
use App\Console\Commands\Database\BlockMigrateFresh;
use App\Http\Controllers\Controller;
use Illuminate\Console\Application;
use Illuminate\Support\Facades\Artisan;

/**
 * Helper class to block dangerous migration commands in production
 * and suggest safe alternatives.
 */
class MigrationCommandBlocker extends Controller
{
	/**
	 * Register blocked migration commands for production environment.
	 * Blocks migrate:fresh and migrate commands, suggesting safe alternatives.
	 */
	public static function registerBlockedCommands(): void
	{
		if (config("app.env") !== "production") {
			return;
		}

		// Allow bypass if LARAVEL_MIGRATE_ORIGINAL is set (used by safe commands)
		if (getenv("LARAVEL_MIGRATE_ORIGINAL") || isset($_ENV["LARAVEL_MIGRATE_ORIGINAL"])) {
			return;
		}

		// Register blocking commands using Artisan::command()
		// Use the BlockMigrateFresh and BlockMigrate classes logic directly
		Artisan::command(
			"migrate:fresh {--database=} {--path=*} {--realpath} {--schema-path=} {--seed} {--seeder=} {--step} {--drop-views} {--drop-types} {--force} {--p=}",
			function () {
				return (new BlockMigrateFresh())
					->setLaravel(app())
					->setInput($this->input)
					->setOutput($this->output)
					->handle();
			}
		);

		Artisan::command(
			"migrate {--database=} {--force} {--path=*} {--realpath} {--pretend} {--seed} {--seeder=} {--step} {--isolated}",
			function () {
				return (new BlockMigrate())->setLaravel(app())->setInput($this->input)->setOutput($this->output)->handle();
			}
		);
	}
}
