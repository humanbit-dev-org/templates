<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Logging\EncryptedRotatingFileHandler;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class TestLogRotation extends Command
{
	protected $signature = "log:test-rotation {--days=3 : Number of days to test}";
	protected $description = "Test log rotation by creating old log files and verifying cleanup";

	public function handle()
	{
		$testDays = (int) $this->option("days");
		$testLogDir = storage_path("logs/test_rotation");
		$testLogPath = $testLogDir . "/test.log";

		// Clean up before test
		if (File::exists($testLogDir)) {
			File::deleteDirectory($testLogDir);
		}
		File::makeDirectory($testLogDir, 0755, true);

		$this->info("Creating {$testDays} old log files...");

		// Create old log files (simulating more days than the limit)
		$baseDate = Carbon::now();
		$filesToCreate = $testDays + 2; // Create 2 more files than the limit

		for ($i = 0; $i < $filesToCreate; $i++) {
			$date = $baseDate->copy()->subDays($i)->format("Y-m-d");
			$oldFile = $testLogDir . "/test-{$date}.log";
			File::put($oldFile, "Test log content for {$date}\n");
			// Set file modification time to simulate old files
			touch($oldFile, $baseDate->copy()->subDays($i)->timestamp);
			$this->line("  Created: test-{$date}.log");
		}

		$this->info("\nInitial file count: " . count(glob($testLogDir . "/test-*.log")));

		// Create handler with rotation
		$this->info("\nCreating handler with days={$testDays}...");
		$handler = new EncryptedRotatingFileHandler(path: $testLogPath, days: $testDays, level: \Monolog\Logger::DEBUG);

		// Trigger rotation by writing a new log entry
		$this->info("Writing test log entry to trigger rotation...");
		$record = new \Monolog\LogRecord(
			datetime: new \DateTimeImmutable(),
			channel: "test",
			level: \Monolog\Level::Debug,
			message: "Test rotation message",
			context: [],
			extra: []
		);
		$handler->handle($record);
		$handler->close();

		// Get all log files after rotation
		$logFiles = glob($testLogDir . "/test-*.log");
		$finalCount = count($logFiles);

		$this->info("\nFinal file count: {$finalCount}");
		$this->info("Expected max: {$testDays}");

		if ($finalCount <= $testDays) {
			$this->info("✅ SUCCESS: Rotation working correctly! Old files were deleted.");
			$this->line("\nRemaining files:");
			foreach ($logFiles as $file) {
				$this->line("  - " . basename($file));
			}
		} else {
			$this->error("❌ FAILED: Too many files remaining ({$finalCount} > {$testDays})");
			$this->line("\nRemaining files:");
			foreach ($logFiles as $file) {
				$this->line("  - " . basename($file));
			}
			return 1;
		}

		// Clean up
		File::deleteDirectory($testLogDir);
		$this->info("\nTest completed and cleaned up.");

		return 0;
	}
}
