<?php

namespace App\Http\Controllers\Admin\Ajax;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AJAX controller for searching related entities
 * Used by hasMany relation filters to find parent entities by related entity IDs
 */
class RelationSearchController extends Controller
{
	/**
	 * Search in hasMany relations (INVERSE SEARCH)
	 * This searches IN THE RELATED MODEL and returns options
	 * Example: search for tiraggi by ID/numero_pratica to filter users
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function searchRelation(Request $request)
	{
		$modelClass = $request->input("model"); // Parent model (e.g., User)
		$relationName = $request->input("relation"); // Relation name (e.g., tiraggi)
		$searchTerm = $request->input("q", "");
		$searchField = $request->input("search_field", null); // Field to search in related model

		if (!$modelClass || !$relationName) {
			return response()->json(["results" => []]);
		}

		// Verify parent model exists
		if (!class_exists($modelClass)) {
			return response()->json(["results" => []]);
		}

		$parentModel = new $modelClass();

		// Get the relation to find the related model
		if (!method_exists($parentModel, $relationName)) {
			return response()->json(["results" => []]);
		}

		$relation = $parentModel->$relationName();
		$relatedModel = $relation->getRelated();
		$relatedClass = get_class($relatedModel);

		// Build query on RELATED model (where we search)
		$query = $relatedClass::query();

		// NO SEARCH = NO RESULTS (performance optimization)
		if (empty($searchTerm)) {
			return response()->json(["results" => []]);
		}

		// Apply search
		if ($searchField) {
			// Search in specific field
			if (is_numeric($searchTerm)) {
				$query->where($searchField, "=", $searchTerm);
			} else {
				$query->where($searchField, "LIKE", "%{$searchTerm}%");
			}
		} else {
			// Search in multiple fields
			$searchableFields = $this->getSearchableFields($relatedModel, null);
			$query->where(function ($q) use ($searchableFields, $searchTerm) {
				foreach ($searchableFields as $field) {
					if (is_numeric($searchTerm)) {
						$q->orWhere($field, "=", $searchTerm);
					} else {
						$q->orWhere($field, "LIKE", "%{$searchTerm}%");
					}
				}
			});
		}

		// Get results (max 50)
		$results = $query->limit(50)->get();

		// Format results
		// IMPORTANT: If searching by custom field (e.g., numero_pratica), return that field as 'id'
		// so it gets used in the filter, not the primary key
		$formatted = $results->map(function ($item) use ($searchField, $relatedModel) {
			// Determine which value to use as 'id' in the result
			if ($searchField && $searchField !== $relatedModel->getKeyName()) {
				// Custom field search: use the searched field value as 'id'
				$valueToReturn = $item->$searchField;
			} else {
				// Primary key search: use actual primary key
				$valueToReturn = $item->getKey();
			}

			// Get display value
			if (method_exists($item, "getRelationDisplayAttribute")) {
				$text = $item->getRelationDisplayAttribute();
			} elseif (method_exists($item, "getDisplayAttribute")) {
				$text = $item->getDisplayAttribute();
			} else {
				// Fallback
				$text = $item->numero_pratica ?? ($item->name ?? ($item->ragione_sociale ?? $item->getKey()));
			}

			return [
				"id" => $valueToReturn,
				"text" => $text,
			];
		});

		return response()->json(["results" => $formatted]);
	}

	/**
	 * Get searchable fields for a model
	 * Includes primary key and common unique/indexed fields
	 */
	private function getSearchableFields($model, $specificField = null): array
	{
		$fields = [];

		// If specific field requested, use only that
		if ($specificField) {
			return [$specificField];
		}

		// Always include primary key
		$fields[] = $model->getKeyName();

		// Add common searchable fields if they exist
		$commonFields = ["numero_pratica", "name", "ragione_sociale", "email", "code", "codice"];

		foreach ($commonFields as $field) {
			if (
				in_array($field, $model->getFillable()) ||
				(property_exists($model, "fillable") && in_array($field, $model->fillable))
			) {
				$fields[] = $field;
			}
		}

		return array_unique($fields);
	}

	/**
	 * Get all searchable keys for a model (used for filter configuration)
	 * Returns both primary key and custom unique keys
	 *
	 * @param string $modelClass
	 * @return array
	 */
	public static function getSearchableKeys(string $modelClass): array
	{
		if (!class_exists($modelClass)) {
			return [];
		}

		$model = new $modelClass();
		$keys = [];

		// Primary key
		$primaryKey = $model->getKeyName();
		$keys[] = [
			"field" => $primaryKey,
			"label" => ucfirst(str_replace("_", " ", $primaryKey)),
			"is_primary" => true,
		];

		// Check for numero_pratica (common custom key)
		if (in_array("numero_pratica", $model->getFillable())) {
			$keys[] = [
				"field" => "numero_pratica",
				"label" => "Numero Pratica",
				"is_primary" => false,
			];
		}

		return $keys;
	}
}
