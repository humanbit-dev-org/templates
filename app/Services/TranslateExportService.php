<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use App\Models\Translate;

class TranslateExportService
{
	public static function exportTranslations()
	{
		// Fetch all records
		$records = Translate::all();

		// Prepare JSON files
		$italianData = [];
		$englishData = [];

		foreach ($records as $record) {
			if ($record->it) {
				$italianData[$record->code] = $record->it;
			}
			if ($record->en) {
				$englishData[$record->code] = $record->en;
			}
			if ($record->text_it) {
				$italianData[$record->code] = $record->text_it;
			}
			if ($record->text_en) {
				$englishData[$record->code] = $record->text_en;
			}
		}

		// Define file paths
		$itPath = base_path("frontend/lang/it.json");
		$enPath = base_path("frontend/lang/en.json");

		// Ensure directories exist
		File::ensureDirectoryExists(dirname($itPath));
		File::ensureDirectoryExists(dirname($enPath));

		// Save JSON files
		File::put($itPath, json_encode($italianData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
		File::put($enPath, json_encode($englishData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

		// Redirect with success message
		return redirect()->back()->with("success", "Translations exported successfully!");
	}
}
