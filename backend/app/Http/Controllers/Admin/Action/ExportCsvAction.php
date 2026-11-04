<?php

namespace App\Http\Controllers\Admin\Action;

use App\Http\Concerns\WritesCsvExport;
use App\Http\Controllers\Controller;
use App\Support\Backpack\Handler\FilterHandler;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExportCsvAction extends Controller
{
	use WritesCsvExport;

	public function exportCrudToCsv($crud)
	{
		// Increase execution time and memory limit for large exports
		set_time_limit(0); // No timeout
		ini_set("memory_limit", "512M"); // Increase memory limit

		// Build the fully qualified model class name dynamically
		$modelClass = "App\\Models\\" . Str::studly($crud);

		// Check if the model class exists before proceeding
		if (!class_exists($modelClass)) {
			throw new NotFoundHttpException("Model not found");
		}

		// Create model instance
		$model = new $modelClass();

		// Get query builder from model class
		$query = $modelClass::query();

		// Apply filters directly to the query using FilterHandler
		FilterHandler::applyFiltersToQuery($query, $model);

		// Generate CSV file path and name
		$csvFileName = strtolower(class_basename($modelClass)) . "_export_" . now()->format("Ymd_His") . ".csv";
		$csvPath = storage_path("app/" . $csvFileName);

		// Open file handle early
		$handle = fopen($csvPath, "w");

		// Disable model events for better performance during export
		$modelClass::unsetEventDispatcher();

		// Check if we have data
		if ($query->count() === 0) {
			fclose($handle);
			unlink($csvPath);
			throw new NotFoundHttpException("No data to export");
		}

		// Process data in chunks to avoid memory issues (larger chunks for better performance)
		$isFirstChunk = true;
		$headers = [];

		$query->chunk(5000, function ($entries) use ($handle, &$isFirstChunk, &$headers) {
			foreach ($entries as $entry) {
				$baseData = $entry->toArray();

				// Build headers only once from first entry
				if ($isFirstChunk) {
					$headers = array_keys($baseData);
					fputcsv($handle, $headers, escape: "\\");
					$isFirstChunk = false;
				}

				// Build row data
				$rowData = [];

				foreach ($baseData as $column => $value) {
					// Convert arrays/objects to JSON strings for CSV compatibility
					if (is_array($value) || is_object($value)) {
						$value = json_encode($value);
					}

					if (in_array($column, ["tan", "taeg"], true)) {
						$value = $this->formatCsvRateForExport($value);
					}

					$rowData[] = $value;
				}

				fputcsv($handle, $rowData, escape: "\\");
			}
		});

		fclose($handle);

		// Get download token from request for loading indicator
		$downloadToken = request()->get("downloadToken");

		// Set cookie to signal download has started (for loading indicator)
		if ($downloadToken) {
			setcookie("downloadToken", $downloadToken, time() + 60, "/"); // 1 minute expiry
		}

		// Return downloadable response and delete file after download
		return response()->download($csvPath)->deleteFileAfterSend(true);
	}
}
