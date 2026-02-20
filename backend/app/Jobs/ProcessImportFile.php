<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Http\Controllers\Admin\Helper\ImportJobService;
use App\Http\Controllers\Admin\Helper\Core\LogRotationHelper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Process Import File Job
 * Handles automated CSV file imports via queue
 * Uses ImportJobService for import logic
 */
class ProcessImportFile implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	// Increase timeout for long-running imports (10 minutes)
	public $timeout = 600;

	// Increase tries for transient failures
	public $tries = 3;

	protected string $filePath;
	protected string $subfolder;
	protected string $modelName;

	public function __construct(string $filePath, string $subfolder, string $modelName)
	{
		$this->filePath = $filePath;
		$this->subfolder = $subfolder;
		$this->modelName = $modelName;
	}

	public function handle()
	{
		$startTime = microtime(true);

		// Ensure log directory exists and rotate old logs (keep last 3)
		LogRotationHelper::prepareImportLog($this->subfolder, 3);

		// File lives in pending/
		$csvPath = database_path("import/{$this->subfolder}/pending/{$this->filePath}");
		$processedFolder = database_path("import/{$this->subfolder}/processed");

		if (!file_exists($csvPath)) {
			Log::channel("import")->error("File not found in pending: {$csvPath}");
			Log::channel($this->subfolder)->error("File not found in pending: {$csvPath}");
			$this->delete();
			return;
		}

		try {
			$modelClass = "App\\Models\\{$this->modelName}";

			// Pass 'subfolder/pending' to service, so it reads from the new location
			$subfolderForService = "{$this->subfolder}/pending";

			// Log start of import to both channels
			$jobStartTime = microtime(true);
			Log::channel("import")->info(
				"Starting import for {$this->subfolder}/{$this->filePath} (Job queued at: " . date("Y-m-d H:i:s") . ")"
			);
			Log::channel($this->subfolder)->info("Starting import for {$this->filePath}");

			// Use ImportJobService instead of ImportCsvController
			$result = ImportJobService::import($this->filePath, $subfolderForService, $modelClass, null);

			$importDuration = round(microtime(true) - $jobStartTime, 2);
			Log::channel("import")->info("Import completed in {$importDuration}s for {$this->subfolder}/{$this->filePath}");

			if ($result) {
				if (!file_exists($processedFolder)) {
					mkdir($processedFolder, 0755, true);
				}

				// Move from pending to processed
				rename($csvPath, "{$processedFolder}/{$this->filePath}");

				$successMessage = sprintf(
					"Import completed for file in import/%s/%s at %s. Processed: %d, Created: %d, Updated: %d, Skipped: %d, Errors: %d",
					$this->subfolder,
					$this->filePath,
					now(),
					$result["processed"] ?? 0,
					$result["created"] ?? 0,
					$result["updated"] ?? 0,
					$result["skipped"] ?? 0,
					$result["errors"] ?? 0
				);

				// Log to both channels
				Log::channel("import")->info($successMessage);
				Log::channel($this->subfolder)->info($successMessage);
				Log::channel("import")->info("File moved to import/" . $this->subfolder . "/processed/" . $this->filePath);
				Log::channel($this->subfolder)->info("File moved to processed/" . $this->filePath);
			} else {
				$errorMessage = "Import failed for {$this->filePath}";
				Log::channel("import")->error($errorMessage);
				Log::channel($this->subfolder)->error($errorMessage);
				throw new \RuntimeException($errorMessage);
			}
		} catch (\Exception $e) {
			$errorMessage = "Import failed for {$this->filePath}: " . $e->getMessage();
			Log::channel("import")->error($errorMessage);
			Log::channel($this->subfolder)->error($errorMessage);
			throw $e;
		}
	}
}
