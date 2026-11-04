<?php

namespace App\Jobs;

use App\Support\Backpack\CsvHelper;
use App\Support\Backpack\ImportJobService;
use App\Support\Backpack\Log\LogHelper;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessImportFile implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public $timeout = 600;

	public $tries = 3;

	protected string $filePath;

	protected string $subfolder;

	protected string $modelName;

	/**
	 * Enable Bus::batch() for parallel chunk processing.
	 * Set to true for large files (> batchThreshold rows) to leverage multi-core.
	 */
	protected bool $useBatchMode = false;

	/** Number of rows per chunk when using batch mode. */
	protected int $chunkSize = 500;

	/** Minimum rows to trigger batch mode (below this, sequential is faster). */
	protected int $batchThreshold = 1000;

	public function __construct(string $filePath, string $subfolder, string $modelName, bool $useBatchMode = false)
	{
		$fileName = basename($filePath);

		if ($fileName !== $filePath) {
			throw new \InvalidArgumentException("Invalid file name.");
		}

		if (str_contains($fileName, "..") || str_contains($fileName, "/") || str_contains($fileName, "\\")) {
			throw new \InvalidArgumentException("Invalid file name.");
		}

		if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== "csv") {
			throw new \InvalidArgumentException("Only CSV files are allowed.");
		}

		if (str_contains($subfolder, "..") || str_contains($subfolder, "/") || str_contains($subfolder, "\\")) {
			throw new \InvalidArgumentException("Invalid subfolder.");
		}

		$this->filePath = $fileName;
		$this->subfolder = $subfolder;
		$this->modelName = $modelName;
		$this->useBatchMode = $useBatchMode;

		// Hash tag required for Redis Cluster slot consistency.
		$this->onQueue("{import}");
	}

	public function tags(): array
	{
		return [
			"import",
			"subfolder:" . $this->subfolder,
			"model:" . $this->modelName,
			"file:" . $this->filePath,
			"attempt:" . $this->attempts(),
			"mode:" . ($this->useBatchMode ? "batch" : "sequential"),
		];
	}

	public function handle(): void
	{
		LogHelper::prepareImportLog($this->subfolder, 3);

		// Global concurrency control: max 2 imports running simultaneously.
		// Note: there is an inherent race condition between increment and the
		// threshold check — under high concurrency a third job may briefly slip
		// through. This is intentional: the race window is milliseconds and the
		// consequence (a brief third concurrent import) is acceptable.
		$maxConcurrent = 2;
		$runningKey = "import:running-count";
		$currentlyRunning = (int) Cache::store("redis")->increment($runningKey);
		$slotAcquired = true;

		if ($currentlyRunning > $maxConcurrent) {
			Cache::store("redis")->decrement($runningKey);
			$slotAcquired = false;

			Log::channel("import")->info(
				"Import slot full ({$currentlyRunning}/{$maxConcurrent}). " .
					"Releasing job back to queue: {$this->subfolder}/{$this->filePath}"
			);

			$this->release(delay: 30);

			return;
		}

		try {
			$baseFolder = database_path("import/{$this->subfolder}");
			$pendingFolder = "{$baseFolder}/pending";
			$processedFolder = "{$baseFolder}/processed";
			$failedFolder = "{$baseFolder}/failed";
			$fileName = $this->filePath;
			$pendingPath = "{$pendingFolder}/{$fileName}";
			$failedPath = "{$failedFolder}/{$fileName}";

			// Retry support: move from failed back to pending on re-attempt.
			if (!file_exists($pendingPath) && file_exists($failedPath)) {
				Log::channel("import")->info("Retry detected. Moving file failed -> pending", [
					"subfolder" => $this->subfolder,
					"file" => $fileName,
				]);

				ImportJobService::moveBetweenStates($this->subfolder, $fileName, "failed", "pending");
			}

			if (!file_exists($pendingPath)) {
				Log::channel("import")->warning("File not found in pending or failed: {$pendingPath}");

				return;
			}

			// File-level lock to prevent duplicate processing across retries.
			$lockKey = "import:file:{$this->subfolder}:" . sha1($fileName);
			$lock = Cache::lock($lockKey, 650);

			if (!$lock->get()) {
				Log::channel("import")->info("Skip import (locked): {$this->subfolder}/{$fileName}");

				return;
			}

			$jobStartTime = microtime(true);

			try {
				$modelClass = "App\\Models\\{$this->modelName}";
				$rowCount = $this->useBatchMode ? $this->countCsvRows($pendingPath) : 0;
				$shouldUseBatch = $this->useBatchMode && $rowCount >= $this->batchThreshold;

				if ($shouldUseBatch) {
					Log::channel("import")->info(
						"Starting BATCH import for {$this->subfolder}/{$fileName} ({$rowCount} rows)"
					);
					$this->handleBatchImport($pendingPath, $modelClass, $processedFolder, $failedFolder, $fileName);
				} else {
					Log::channel("import")->info("Starting SEQUENTIAL import for {$this->subfolder}/{$fileName}");
					$this->handleSequentialImport($pendingPath, $modelClass, $processedFolder, $fileName, $jobStartTime);
				}
			} catch (Throwable $e) {
				$importDuration = round(microtime(true) - $jobStartTime, 2);

				Log::channel("import")->error(
					"Import failed for {$this->subfolder}/{$fileName} ({$importDuration}s): {$e->getMessage()}"
				);

				throw $e;
			} finally {
				optional($lock)->release();
			}
		} finally {
			// Always release the concurrency slot, even on exception or release().
			if ($slotAcquired) {
				Cache::store("redis")->decrement($runningKey);
			}
		}
	}

	private function handleSequentialImport(
		string $pendingPath,
		string $modelClass,
		string $processedFolder,
		string $fileName,
		float $jobStartTime
	): void {
		$result = ImportJobService::import($fileName, $this->subfolder, $modelClass, null);

		$importDuration = round(microtime(true) - $jobStartTime, 2);

		if (!is_dir($processedFolder)) {
			@mkdir($processedFolder, 0755, true);
		}

		$destPath = "{$processedFolder}/{$fileName}";

		if (@rename($pendingPath, $destPath) === false) {
			throw new \RuntimeException("Cannot move to processed: {$pendingPath} -> {$destPath}");
		}

		$metaPath = $processedFolder . "/" . pathinfo($fileName, PATHINFO_FILENAME) . ".meta.json";

		File::put(
			$metaPath,
			json_encode(
				[
					"duration_seconds" => $importDuration,
					"completed_at" => now()->toIso8601String(),
					"status" => "processed",
					"mode" => "sequential",
					"result" => [
						"processed" => (int) ($result["processed"] ?? 0),
						"created" => (int) ($result["created"] ?? 0),
						"updated" => (int) ($result["updated"] ?? 0),
						"skipped" => (int) ($result["skipped"] ?? 0),
						"errors" => (int) ($result["errors"] ?? 0),
					],
				],
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
			)
		);

		Log::channel("import")->info("Import completed in {$importDuration}s for {$this->subfolder}/{$fileName}");
	}

	private function handleBatchImport(
		string $pendingPath,
		string $modelClass,
		string $processedFolder,
		string $failedFolder,
		string $fileName
	): void {
		$delimiter = CsvHelper::detectDelimiter($pendingPath);
		$handle = fopen($pendingPath, "r");

		if (!$handle) {
			throw new \RuntimeException("Cannot open CSV file: {$pendingPath}");
		}

		$headers = fgetcsv($handle, 0, $delimiter, escape: "\\");

		if (!$headers) {
			fclose($handle);
			throw new \RuntimeException("Invalid CSV format: no headers found");
		}

		$preloadedData = ImportJobService::preloadDataForModelPublic($modelClass);

		// Build all chunk jobs in memory before dispatching.
		// For very large files consider streaming dispatch via $batch->add() in chunks,
		// but note that each add() is a separate DB transaction — batch all at once when
		// file size allows.
		$chunks = [];
		$buffer = [];
		$chunkIndex = 0;

		while (($row = fgetcsv($handle, 0, $delimiter, escape: "\\")) !== false) {
			$buffer[] = $row;

			if (count($buffer) >= $this->chunkSize) {
				$chunks[] = new ProcessImportChunk(
					$this->subfolder,
					$this->modelName,
					$buffer,
					$headers,
					$chunkIndex++,
					$preloadedData
				);
				$buffer = [];
			}
		}

		if (!empty($buffer)) {
			$chunks[] = new ProcessImportChunk(
				$this->subfolder,
				$this->modelName,
				$buffer,
				$headers,
				$chunkIndex,
				$preloadedData
			);
		}

		fclose($handle);

		$totalChunks = count($chunks);
		Log::channel("import")->info("Dispatching batch with {$totalChunks} chunks for {$this->subfolder}/{$fileName}");

		$subfolder = $this->subfolder;

		Bus::batch($chunks)
			->name("import:{$subfolder}:{$fileName}")
			// Hash tag is required on Redis Cluster so all import queue keys
			// (list, reserved, delayed, notify) land on the same slot.
			->onQueue("{import}")
			->allowFailures()
			->finally(function (Batch $batch) use ($fileName, $subfolder, $pendingPath, $processedFolder, $failedFolder) {
				$hasFailures = $batch->failedJobs > 0;
				$targetState = $hasFailures ? "failed" : "processed";
				$targetFolder = $hasFailures ? $failedFolder : $processedFolder;

				if (!is_dir($targetFolder)) {
					@mkdir($targetFolder, 0755, true);
				}

				$destPath = "{$targetFolder}/{$fileName}";

				// Log rename failures explicitly — the @ suppresses PHP warnings
				// but the outcome must be visible in logs.
				if (@rename($pendingPath, $destPath) === false) {
					Log::channel("import")->error("Batch finally: failed to move file to {$targetState}", [
						"from" => $pendingPath,
						"to" => $destPath,
						"batch" => $batch->id,
					]);
				}

				$metaPath = $targetFolder . "/" . pathinfo($fileName, PATHINFO_FILENAME) . ".meta.json";

				File::put(
					$metaPath,
					json_encode(
						[
							"batch_id" => $batch->id,
							"completed_at" => now()->toIso8601String(),
							"status" => $targetState,
							"mode" => "batch",
							"total_jobs" => $batch->totalJobs,
							"processed_jobs" => $batch->processedJobs(),
							"failed_jobs" => $batch->failedJobs,
							"pending_jobs" => $batch->pendingJobs,
						],
						JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
					)
				);

				Log::channel("import")->info(
					"Batch completed for {$subfolder}/{$fileName}: " .
						"{$batch->processedJobs()} processed, {$batch->failedJobs} failed — moved to {$targetState}"
				);
			})
			->dispatch();
	}

	private function countCsvRows(string $csvPath): int
	{
		$delimiter = CsvHelper::detectDelimiter($csvPath);

		return CsvHelper::countCsvRows($csvPath, $delimiter) - 1;
	}

	public function failed(Throwable $e): void
	{
		$pendingFolder = database_path("import/{$this->subfolder}/pending");
		$failedFolder = database_path("import/{$this->subfolder}/failed");
		$pendingPath = "{$pendingFolder}/{$this->filePath}";

		if (!file_exists($pendingPath)) {
			Log::channel("import")->error("Job failed but file not found in pending: {$pendingPath}");

			return;
		}

		if (!is_dir($failedFolder)) {
			@mkdir($failedFolder, 0755, true);
		}

		$destPath = "{$failedFolder}/{$this->filePath}";
		@rename($pendingPath, $destPath);

		$metaPath = $failedFolder . "/" . pathinfo($this->filePath, PATHINFO_FILENAME) . ".meta.json";

		File::put(
			$metaPath,
			json_encode(
				[
					"completed_at" => now()->toIso8601String(),
					"status" => "failed",
					"error" => $e->getMessage(),
				],
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
			)
		);

		Log::channel("import")->error("File moved to failed: {$this->subfolder}/{$this->filePath}");
	}
}
