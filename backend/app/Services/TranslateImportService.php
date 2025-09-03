<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Translate;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class TranslateImportService
{
	/**
	 * Import translations from JSON files
	 */
	public static function importTranslations()
	{
		// Check if we should run (only after migrations/seeders)
		if (!self::shouldRunImport()) {
			Log::info("Import skipped - not ready or files not available");
			return false;
		}

		// Define file paths
		$itPath = base_path("frontend/lang/it.json");
		$enPath = base_path("frontend/lang/en.json");

		// Check if both files exist
		if (!File::exists($itPath) || !File::exists($enPath)) {
			Log::warning("Translation files not found for import");
			return false;
		}

		try {
			// Create backup before import
			self::backupJsonFiles();

			// Read and decode JSON files
			$italianData = json_decode(File::get($itPath), true);
			$englishData = json_decode(File::get($enPath), true);

			if (!$italianData || !$englishData) {
				Log::error("Failed to decode translation JSON files");
				self::restoreJsonFiles(); // Restore backup on error
				return false;
			}

			// Disable events to prevent export during import
			Translate::withoutEvents(function () use ($italianData, $englishData) {
				self::processTranslations($italianData, $englishData);
			});

			Log::info("Translations imported successfully from JSON files");

			// Clean old backups (keep only last 5)
			self::cleanOldBackups();

			return true;
		} catch (\Exception $e) {
			Log::error("Error importing translations: " . $e->getMessage());
			self::restoreJsonFiles(); // Restore backup on error
			return false;
		}
	}

	/**
	 * Process and save translations to database
	 */
	private static function processTranslations($italianData, $englishData)
	{
		// Get all pages that exist in either language file
		$allPageNames = array_unique(array_merge(array_keys($italianData), array_keys($englishData)));

		foreach ($allPageNames as $pageName) {
			// Handle special case "all" for global translations (page_id = null)
			$pageId = self::getPageId($pageName);

			// Get translations for this page
			$italianTranslations = $italianData[$pageName] ?? [];
			$englishTranslations = $englishData[$pageName] ?? [];

			// Get all translation codes for this page
			$allCodes = array_unique(array_merge(array_keys($italianTranslations), array_keys($englishTranslations)));

			foreach ($allCodes as $code) {
				self::upsertTranslation(
					$pageId,
					$code,
					$italianTranslations[$code] ?? null,
					$englishTranslations[$code] ?? null
				);
			}
		}
	}

	/**
	 * Get page ID for a page name, handling special "all" case
	 */
	private static function getPageId($pageName)
	{
		// Special case: "all" means global translations with page_id = null
		if ($pageName === "all") {
			return null;
		}

		// Ensure page exists in database
		$page = self::ensurePageExists($pageName);

		if (!$page) {
			Log::warning("Could not create/find page: {$pageName}");
			return null;
		}

		return $page->id;
	}

	/**
	 * Ensure page exists in database, create if needed
	 */
	private static function ensurePageExists($pageName)
	{
		$page = Page::where("name", $pageName)->first();

		if (!$page) {
			// Create page if it doesn't exist
			$page = Page::create([
				"name" => $pageName,
				"title" => ucfirst(str_replace("-", " ", $pageName)),
				"description" => "",
				"order" => Page::max("order") + 1,
				"visible" => true,
			]);

			Log::info("Created page: {$pageName}");
		}

		return $page;
	}

	/**
	 * Create or update translation record
	 */
	private static function upsertTranslation($pageId, $code, $italianData, $englishData)
	{
		$query = Translate::where("code", $code);

		// Handle both null and specific page_id cases
		if ($pageId === null) {
			$query->whereNull("page_id");
		} else {
			$query->where("page_id", $pageId);
		}

		$translation = $query->first();

		$data = [
			"page_id" => $pageId,
			"code" => $code,
			"it" => $italianData["it"] ?? "",
			"text_it" => $italianData["text_it"] ?? "",
			"en" => $englishData["en"] ?? "",
			"text_en" => $englishData["text_en"] ?? "",
		];

		if ($translation) {
			$translation->update($data);
			Log::debug("Updated translation: {$code} for page ID " . ($pageId ?? "global"));
		} else {
			Translate::create($data);
			Log::debug("Created translation: {$code} for page ID " . ($pageId ?? "global"));
		}
	}

	/**
	 * Check if import should run (files exist and system is ready)
	 */
	private static function shouldRunImport()
	{
		$itPath = base_path("frontend/lang/it.json");
		$enPath = base_path("frontend/lang/en.json");

		// Check if files exist
		if (!File::exists($itPath) || !File::exists($enPath)) {
			return false;
		}

		// Check if we're in a migration/seeder context by looking at stack trace
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		foreach ($backtrace as $trace) {
			if (isset($trace["class"])) {
				// Skip if we're in a migration or seeder
				if (
					str_contains($trace["class"], "Migration") ||
					str_contains($trace["class"], "Seeder") ||
					str_contains($trace["class"], "Migrator")
				) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Check if import is needed (JSON files are newer than last DB update)
	 */
	public static function shouldImport()
	{
		if (!self::shouldRunImport()) {
			return false;
		}

		$itPath = base_path("frontend/lang/it.json");
		$enPath = base_path("frontend/lang/en.json");

		// Get last modification time of JSON files
		$jsonLastModified = max(File::lastModified($itPath), File::lastModified($enPath));

		// Get last update time from database
		$lastTranslateUpdate = Translate::max("updated_at");

		if (!$lastTranslateUpdate) {
			return true; // No translations in DB, should import
		}

		return $jsonLastModified > strtotime($lastTranslateUpdate);
	}

	/**
	 * Create backup of JSON files
	 */
	private static function backupJsonFiles()
	{
		$backupDir = base_path("storage/backups/translations");
		File::ensureDirectoryExists($backupDir);

		$timestamp = Carbon::now()->format("Y-m-d_H-i-s");

		$itPath = base_path("frontend/lang/it.json");
		$enPath = base_path("frontend/lang/en.json");

		if (File::exists($itPath)) {
			File::copy($itPath, "{$backupDir}/it_{$timestamp}.json");
		}

		if (File::exists($enPath)) {
			File::copy($enPath, "{$backupDir}/en_{$timestamp}.json");
		}

		Log::info("JSON files backed up with timestamp: {$timestamp}");
	}

	/**
	 * Restore JSON files from backup (latest)
	 */
	private static function restoreJsonFiles()
	{
		$backupDir = base_path("storage/backups/translations");

		if (!File::exists($backupDir)) {
			Log::warning("No backup directory found for restoration");
			return;
		}

		$itBackups = File::glob("{$backupDir}/it_*.json");
		$enBackups = File::glob("{$backupDir}/en_*.json");

		if (empty($itBackups) || empty($enBackups)) {
			Log::warning("No backup files found for restoration");
			return;
		}

		// Get latest backups
		$latestIt = collect($itBackups)->sort()->last();
		$latestEn = collect($enBackups)->sort()->last();

		// Restore files
		File::copy($latestIt, base_path("frontend/lang/it.json"));
		File::copy($latestEn, base_path("frontend/lang/en.json"));

		Log::info("JSON files restored from backup");
	}

	/**
	 * Clean old backup files (keep only last 5)
	 */
	private static function cleanOldBackups()
	{
		$backupDir = base_path("storage/backups/translations");

		if (!File::exists($backupDir)) {
			return;
		}

		$itBackups = collect(File::glob("{$backupDir}/it_*.json"))->sort();
		$enBackups = collect(File::glob("{$backupDir}/en_*.json"))->sort();

		// Keep only last 5 backups
		$itBackups
			->take(-5)
			->reverse()
			->skip(5)
			->each(function ($file) {
				File::delete($file);
			});

		$enBackups
			->take(-5)
			->reverse()
			->skip(5)
			->each(function ($file) {
				File::delete($file);
			});
	}

	/**
	 * Force import for manual execution (like seeder)
	 */
	public static function forceImport()
	{
		// Define file paths
		$itPath = base_path("frontend/lang/it.json");
		$enPath = base_path("frontend/lang/en.json");

		// Check if both files exist
		if (!File::exists($itPath) || !File::exists($enPath)) {
			Log::warning("Translation files not found for forced import");
			return false;
		}

		try {
			// Create backup before import
			self::backupJsonFiles();

			// Read and decode JSON files
			$italianData = json_decode(File::get($itPath), true);
			$englishData = json_decode(File::get($enPath), true);

			if (!$italianData || !$englishData) {
				Log::error("Failed to decode translation JSON files");
				return false;
			}

			// Disable events to prevent export during import
			Translate::withoutEvents(function () use ($italianData, $englishData) {
				self::processTranslations($italianData, $englishData);
			});

			Log::info("Translations force imported successfully from JSON files");
			return true;
		} catch (\Exception $e) {
			Log::error("Error in forced import: " . $e->getMessage());
			return false;
		}
	}
}
