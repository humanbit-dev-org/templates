<?php

namespace App\Http\Controllers\Admin\Helper;

use Illuminate\Support\Str;
use App\Http\Controllers\Controller;

class DuplicateController extends Controller
{
	public function duplicate($crud, $id)
	{
		$modelClass = "App\\Models\\" . Str::studly($crud);

		if (!class_exists($modelClass)) {
			return response()->json(["success" => false, "message" => "Model not found"]);
		}

		$model = app($modelClass);
		$entry = $model::findOrFail($id);

		// Duplicate logic
		$data = $entry->toArray();
		unset($data["id"], $data["created_at"], $data["updated_at"]);

		// Increment order by 1 if it exists
		if (isset($data["order"])) {
			$data["order"] += 1;
		}

		$newEntry = $model::create($data);

		return response()->json([
			"success" => true,
			"new_entry_id" => $newEntry->id,
		]);
	}
}
