<?php

namespace App\Console\Commands\Log;

use Illuminate\Console\Command;

class ClearLogFile extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = "log:clear";

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Clear Laravel log";

	/**
	 * Execute the console command.
	 */
	public function handle()
	{
		if ($this->confirm("Are you sure you want to clear the log file?")) {
			exec('echo "" > ' . storage_path("logs/laravel.log"));
			$this->info("Logs have been cleared");
			return;
		}
		$this->info("Operation cancelled");
		return;
	}
}
