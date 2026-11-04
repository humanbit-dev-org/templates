<?php

namespace App\Http\Controllers\Admin\Support;

use App\Support\Backpack\SchemaCache;
use App\Support\Backpack\FilterHelper;
use App\Support\Backpack\Handler\FieldConfigHandler;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AJAX controller for searching related entities
 * Used by hasMany relation filters to find parent entities by related entity IDs
 */
class RelationSearchOperation extends Controller
{
	/**
	 * Normalize model activeFilterField config into a list of field names.
	 *
	 * @return array<int, string>
	 */
	private static function resolveActiveFilterFieldsFromModel($model): array
	{
		$raw = null;

		// Prefer explicit accessor if present.
		if (method_exists($model, "getActiveFilterField")) {
			try {
				$raw = $model->getActiveFilterField();
			} catch (\Throwable $e) {
				$raw = null;
			}
		}

		// Fallback to property.
		if ($raw === null && property_exists($model, "activeFilterField")) {
			$raw = $model->activeFilterField;
		}

		if (is_string($raw) && trim($raw) !== "") {
			return [trim($raw)];
		}

		if (!is_array($raw)) {
			return [];
		}

		return array_values(
			array_filter(
				array_map(function ($field) {
					return is_string($field) ? trim($field) : "";
				}, $raw),
				fn($field) => $field !== ""
			)
		);
	}

	/**
	 * Search in hasMany relations (INVERSE SEARCH)
	 * This searches IN THE RELATED MODEL and returns options
	 * Example: search for tiraggi by ID/numero_pratica to filter users
	 *
	 * @return JsonResponse
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

		// Build query on RELATED model and preserve custom relation scopes
		// (e.g. usersEleggibili => sospeso = 0), while removing parent-key binding.
		$query = $this->buildScopedRelatedQuery($relation, $relatedClass);

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
	 * Build related-model query preserving relation scopes but removing parent-key constraint.
	 */
	private function buildScopedRelatedQuery(Relation $relation, string $relatedClass)
	{
		$query = $relatedClass::query();

		if (!($relation instanceof HasMany)) {
			return $query;
		}

		$foreignKey = $relation->getForeignKeyName();
		$relationBaseQuery = $relation->getQuery()->getQuery();
		$wheres = $relationBaseQuery->wheres ?? [];

		foreach ($wheres as $where) {
			$type = $where["type"] ?? null;
			$column = $where["column"] ?? null;

			// Skip the HasMany binding to parent local key (on empty model instance it's usually null).
			if ($column === $foreignKey) {
				continue;
			}

			if ($type === "Basic") {
				$query->where($column, $where["operator"] ?? "=", $where["value"] ?? null);
			} elseif ($type === "Null") {
				$query->whereNull($column);
			} elseif ($type === "NotNull") {
				$query->whereNotNull($column);
			} elseif ($type === "In") {
				$query->whereIn($column, $where["values"] ?? []);
			} elseif ($type === "NotIn") {
				$query->whereNotIn($column, $where["values"] ?? []);
			}
		}

		return $query;
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

		// Include model-configured active filter fields (supports string or array).
		foreach (self::resolveActiveFilterFieldsFromModel($model) as $field) {
			$fields[] = $field;
		}

		return array_unique($fields);
	}

	/**
	 * Get all searchable keys for a model (used for filter configuration)
	 * Returns both primary key and custom unique keys
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

		// Include model-configured active filter fields (supports string or array).
		$activeFields = self::resolveActiveFilterFieldsFromModel($model);
		foreach ($activeFields as $field) {
			if ($field === $primaryKey) {
				continue;
			}

			$keys[] = [
				"field" => $field,
				"label" => ucfirst(str_replace("_", " ", $field)),
				"is_primary" => false,
			];
		}

		// Check for numero_pratica (common custom key)
		if (in_array("numero_pratica", $model->getFillable())) {
			$keys[] = [
				"field" => "numero_pratica",
				"label" => "Numero Pratica",
				"is_primary" => false,
			];
		}

		// Deduplicate by field while preserving insertion order.
		$unique = [];
		foreach ($keys as $keyInfo) {
			$field = $keyInfo["field"] ?? null;
			if (!is_string($field) || $field === "" || isset($unique[$field])) {
				continue;
			}
			$unique[$field] = $keyInfo;
		}

		return array_values($unique);
	}

	/**
	 * Build the request parameter prefix for a HasMany relation field filter.
	 */
	public static function relationFieldParamPrefix(string $relationName, string $fieldName): string
	{
		return $relationName . "_rel_" . $fieldName;
	}

	/**
	 * Get filterable fields for a related model (used by HasMany advanced relation filters).
	 *
	 * @return array<int, array{field: string, label: string, type: string, input_type: string, options?: array<string, string>}>
	 */
	public static function getRelationFilterableFields(string $modelClass, ?string $parentForeignKey = null): array
	{
		if (!class_exists($modelClass)) {
			return [];
		}

		/** @var Model $model */
		$model = new $modelClass();
		$table = $model->getTable();

		$hiddenFields = [
			"token_expire",
			"remember_token",
			"import",
			"token",
			"password",
			"email_verified_at",
			"token_verified",
		];

		$excludedFields = array_merge(
			$hiddenFields,
			array_map(
				fn(array $keyInfo) => $keyInfo["field"] === "id" ? $model->getKeyName() : $keyInfo["field"],
				self::getSearchableKeys($modelClass)
			)
		);

		if (is_string($parentForeignKey) && $parentForeignKey !== "") {
			$excludedFields[] = $parentForeignKey;
		}

		$fields = [];

		foreach ($model->getFillable() as $column) {
			if (!is_string($column) || $column === "") {
				continue;
			}

			if (in_array($column, $excludedFields, true) || !SchemaCache::hasColumn($table, $column)) {
				continue;
			}

			$selectConfig = FieldConfigHandler::getSelectFieldConfig($column, $table);
			if ($selectConfig !== null) {
				$fields[] = [
					"field" => $column,
					"label" => ucfirst(str_replace("_", " ", $column)),
					"type" => "select",
					"input_type" => "select",
					"options" => $selectConfig["options"] ?? [],
				];

				continue;
			}

			$columnType = strtolower((string) SchemaCache::getColumnType($table, $column));

			if (in_array($columnType, ["date", "datetime", "timestamp"], true)) {
				$fields[] = [
					"field" => $column,
					"label" => ucfirst(str_replace("_", " ", $column)),
					"type" => "date",
					"input_type" => "date",
				];

				continue;
			}

			if (FilterHelper::isBooleanDatabaseColumn($table, $column)) {
				$fields[] = [
					"field" => $column,
					"label" => ucfirst(str_replace("_", " ", $column)),
					"type" => "boolean",
					"input_type" => "boolean",
				];

				continue;
			}

			$relatedPk = $model->getKeyName();

			if (FilterHelper::isForeignKeyColumn($column, $table, $relatedPk)) {
				$fields[] = [
					"field" => $column,
					"label" => ucfirst(str_replace("_", " ", $column)),
					"type" => "fk_autocomplete",
					"input_type" => "text",
				];

				continue;
			}

			$inputType = "text";
			if (
				str_contains($columnType, "int") ||
				in_array($columnType, ["decimal", "numeric", "float", "double", "real"], true)
			) {
				$inputType = "number";
			}

			$fields[] = [
				"field" => $column,
				"label" => ucfirst(str_replace("_", " ", $column)),
				"type" => $inputType,
				"input_type" => $inputType,
				"step" => FilterHelper::getNumberStep(["name" => $column], $table),
				"percentage" => FilterHelper::isPercentageColumn($column),
				"autocomplete" => $inputType === "text",
			];
		}

		return $fields;
	}
}
