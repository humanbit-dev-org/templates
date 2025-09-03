<?php

namespace App\Http\Controllers\Admin\Helper;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class BulkOperationsController extends Controller
{
	/**
	 * Delete multiple entries at once.
	 *
	 * @param  Request  $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function bulkDelete(Request $request, $crud)
	{
		$modelClass = "App\\Models\\" . Str::studly(Str::singular($crud));

		if (!class_exists($modelClass)) {
			return response()->json([
				"success" => false,
				"message" => "Model not found",
			]);
		}

		$model = app($modelClass);
		$ids = $request->input("ids");

		if (empty($ids) || !is_array($ids)) {
			return response()->json([
				"success" => false,
				"message" => "No IDs provided",
			]);
		}

		try {
			// Find all models by IDs
			$entries = $model::whereIn("id", $ids)->get();

			// Delete each entry
			$deletedCount = 0;
			foreach ($entries as $entry) {
				$entry->delete();
				$deletedCount++;
			}

			return response()->json([
				"success" => true,
				"message" => "{$deletedCount} items deleted successfully",
			]);
		} catch (\Exception $e) {
			Log::error("Bulk deletion error: " . $e->getMessage());

			return response()->json([
				"success" => false,
				"message" => "Error while deleting: " . $e->getMessage(),
			]);
		}
	}

	/**
	 * Duplicate multiple entries at once.
	 *
	 * @param  Request  $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function bulkDuplicate(Request $request, $crud)
	{
		$modelClass = "App\\Models\\" . Str::studly(Str::singular($crud));

		if (!class_exists($modelClass)) {
			return response()->json([
				"success" => false,
				"message" => "Model not found",
			]);
		}

		$model = app($modelClass);
		$ids = $request->input("ids");

		if (empty($ids) || !is_array($ids)) {
			return response()->json([
				"success" => false,
				"message" => "No IDs provided",
			]);
		}

		try {
			// Find all models by IDs
			$entries = $model::whereIn("id", $ids)->get();

			// Duplicate each entry
			$duplicatedCount = 0;
			$newEntries = [];

			foreach ($entries as $entry) {
				// Duplicate logic
				$data = $entry->toArray();
				unset($data["id"], $data["created_at"], $data["updated_at"]);

				// Increment order by 1 if it exists
				if (isset($data["order"])) {
					$data["order"] += 1;
				}

				$newEntry = $model::create($data);
				$newEntries[] = $newEntry->id;
				$duplicatedCount++;
			}

			return response()->json([
				"success" => true,
				"message" => "{$duplicatedCount} items duplicated successfully",
				"new_entries" => $newEntries,
			]);
		} catch (\Exception $e) {
			Log::error("Bulk duplication error: " . $e->getMessage());

			return response()->json([
				"success" => false,
				"message" => "Error while duplicating: " . $e->getMessage(),
			]);
		}
	}
}
