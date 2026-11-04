<?php

namespace App\Http\Controllers\Admin\Support;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BelongsToSearchOperation extends Controller
{
	public function search(Request $request)
	{
		$modelClass = $request->input("model");
		$q = (string) $request->input("q", "");
		$searchField = $request->input("search_field");

		if (!$modelClass || !class_exists($modelClass) || $q === "") {
			return response()->json(["results" => []]);
		}

		$model = new $modelClass();
		$query = $modelClass::query();

		if ($searchField) {
			$query->where($searchField, "like", "%{$q}%");
		} else {
			// Always include primary key in search
			$primaryKey = $model->getKeyName();
			$candidates = [$primaryKey, "user_id", "ragione_sociale", "name", "email", "title", "codice", "code"];

			$query->where(function ($builder) use ($candidates, $q, $model, $primaryKey) {
				foreach ($candidates as $field) {
					// primary key is always searchable; others only if fillable
					if ($field !== $primaryKey && !in_array($field, $model->getFillable(), true)) {
						continue;
					}

					// Exact match when searching by primary key-like value (e.g. user_id with dash)
					if ($field === $primaryKey || $field === "user_id") {
						$builder->orWhere($field, "=", $q);
					} else {
						$builder->orWhere($field, "like", "%{$q}%");
					}
				}
			});
		}

		$results = $query
			->limit(50)
			->get()
			->map(function ($item) use ($model) {
				if (method_exists($item, "getRelationDisplayAttribute")) {
					$text = $item->getRelationDisplayAttribute();
				} elseif (method_exists($item, "getDisplayAttribute")) {
					$text = $item->getDisplayAttribute();
				} else {
					$text = $item->user_id ?? ($item->ragione_sociale ?? ($item->name ?? ($item->title ?? $item->getKey())));
				}

				return [
					"id" => $item->{$model->getKeyName()},
					"text" => (string) $text,
				];
			});

		return response()->json(["results" => $results]);
	}
}
