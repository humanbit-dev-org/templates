<?php

namespace App\Http\Controllers\Admin\Helper;

use Illuminate\Support\Str;
use App\Http\Controllers\Controller;

class ExportCsvController extends Controller
{
	public function exportCrudToCsv($crud)
	{
		// Build the fully qualified model class name dynamically
		$modelClass = "App\\Models\\" . Str::studly($crud);

		// Check if the model class exists before proceeding
		if (!class_exists($modelClass)) {
			return response()->json(["error" => "Model not found"], 404);
		}

		$entries = $modelClass::all();

		if ($entries->isEmpty()) {
			return response()->json(["error" => "No data to export"], 404);
		}

		// Generate CSV file path and name
		$csvFileName = strtolower(class_basename($modelClass)) . "_export_" . now()->format("Ymd_His") . ".csv";
		$csvPath = storage_path("app/" . $csvFileName);

		// Write CSV data
		$handle = fopen($csvPath, "w");
		fputcsv($handle, array_keys($entries->first()->toArray())); // Headers

		foreach ($entries as $entry) {
			fputcsv($handle, $entry->toArray());
		}

		fclose($handle);

		// Return downloadable response and delete file after download
		return response()->download($csvPath)->deleteFileAfterSend(true);
	}
}
