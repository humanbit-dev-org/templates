<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Metadata;
use Illuminate\Support\Facades\File;

class MetadataExportService
{
	public static function exportMetadata()
	{
		// Fetch all records
		$records = Metadata::all();

		// Prepare JSON files with grouped translations per page
		$italianData = [];
		$englishData = [];

		foreach ($records as $record) {
			$page_id = $record->page_id;
			$page = Page::where("id", $page_id)->first()->name ?? "unknown";

			// Ensure the page exists in the data arrays
			if (!isset($italianData[$page])) {
				$italianData[$page] = [];
			}
			if (!isset($englishData[$page])) {
				$englishData[$page] = [];
			}

			// Ensure the translation key exists for the page
			if (!isset($italianData[$page][$record->code])) {
				$italianData[$page][$record->code] = [];
			}
			if (!isset($englishData[$page][$record->code])) {
				$englishData[$page][$record->code] = [];
			}

			// Assign translations in an object with `it` and `text_it`
			$italianData[$page][$record->code] = [
				"it" => $record->it ?? "",
				"image_path" => $record->image_path ?? "",
			];

			// Assign translations in an object with `en` and `text_en`
			$englishData[$page][$record->code] = [
				"en" => $record->en ?? "",
				"image_path" => $record->image_path ?? "",
			];
		}

		// Define file paths
		$itPath = base_path("frontend/metadata/it.json");
		$enPath = base_path("frontend/metadata/en.json");

		// Ensure directories exist
		File::ensureDirectoryExists(dirname($itPath));
		File::ensureDirectoryExists(dirname($enPath));

		// Save JSON files with grouped translations
		File::put($itPath, json_encode($italianData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
		File::put($enPath, json_encode($englishData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

		// Redirect with success message
		return redirect()->back()->with("success", "Metadata exported successfully!");
	}
}
