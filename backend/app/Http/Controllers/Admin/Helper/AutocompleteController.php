<?php

namespace App\Http\Controllers\Admin\Helper;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class AutocompleteController extends Controller
{
	public function getValues(Request $request)
	{
		$column = $request->input("column"); // Column name
		$table = $request->input("table"); // Table name
		$term = $request->input("term"); // Search term

		// Validate that the table and column exist to prevent SQL injection
		if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
			return response()->json([]);
		}

		// Fetch unique values from the given table & column
		$values = DB::table($table)
			->select($column)
			->where($column, "LIKE", "%$term%")
			->distinct()
			->limit(10)
			->pluck($column);

		return response()->json($values);
	}

	/**
	 * Get display value for a record: unique field if exists and not empty, otherwise primary key
	 * For Role and BackpackRole, also includes the "name" field
	 *
	 * @param mixed $record Eloquent model or stdClass object
	 * @param string $tableName Table name
	 * @return string Display value
	 */
	private function getRelationDisplayValue($record, string $tableName): string
	{
		// Try to get unique fields from the table
		$uniqueFields = DB::select(
			"
			SELECT COLUMN_NAME 
			FROM INFORMATION_SCHEMA.STATISTICS 
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = ?
			AND NON_UNIQUE = 0
			AND INDEX_NAME != 'PRIMARY'
			ORDER BY SEQ_IN_INDEX
			LIMIT 1
		",
			[$tableName]
		);

		$displayValue = null;
		$uniqueValue = null;

		// If unique field exists, try to use it
		if (!empty($uniqueFields)) {
			$uniqueField = $uniqueFields[0]->COLUMN_NAME;

			// Get value from record (handle both Eloquent and stdClass)
			if (is_object($record)) {
				if (method_exists($record, "getAttribute")) {
					$uniqueValue = $record->getAttribute($uniqueField);
				} elseif (isset($record->$uniqueField)) {
					$uniqueValue = $record->$uniqueField;
				} else {
					$uniqueValue = null;
				}

				// Use unique field if not empty
				if ($uniqueValue !== null && $uniqueValue !== "") {
					$displayValue = (string) $uniqueValue;
				}
			}
		}

		// Fallback to primary key if unique field not available or empty
		if ($displayValue === null) {
			if (method_exists($record, "getKey")) {
				$displayValue = (string) $record->getKey();
			} elseif (is_object($record) && isset($record->id)) {
				$displayValue = (string) $record->id;
			} elseif (is_object($record) && method_exists($record, "getKeyName")) {
				$keyName = $record->getKeyName();
				if (isset($record->$keyName)) {
					$displayValue = (string) $record->$keyName;
				}
			}
		}

		// For Role and BackpackRole models, also include the "name" field if available
		$modelClass = is_object($record) ? get_class($record) : null;
		if ($modelClass) {
			$modelName = class_basename($modelClass);
			if (in_array($modelName, ["Role", "BackpackRole"]) && Schema::hasColumn($tableName, "name")) {
				$nameValue = method_exists($record, "getAttribute")
					? $record->getAttribute("name")
					: (isset($record->name)
						? $record->name
						: null);

				if ($nameValue !== null && $nameValue !== "") {
					// Combine name with unique/primary key value
					return $nameValue . " (" . $displayValue . ")";
				}
			}
		}

		return $displayValue ?? "";
	}

	/**
	 * Get autocomplete values for relation filters
	 * Uses contains search (LIKE) instead of exact match
	 */
	public function getRelationValues(Request $request)
	{
		$relationColumn = $request->input("relation_column"); // Foreign key column name (e.g., user_id)
		$table = $request->input("table"); // Current table name
		$term = $request->input("term"); // Search term
		$isHasMany = $request->input("is_hasmany", false); // Is this a hasMany relation?
		$relation = $request->input("relation"); // Relation name (for hasMany)
		$model = $request->input("model"); // Model class (for hasMany)
		$searchableKeys = $request->input("searchable_keys"); // Searchable keys (for hasMany)

		if (empty($term)) {
			return response()->json([]);
		}

		$results = [];

		try {
			if ($isHasMany && $relation && $model && $searchableKeys) {
				// HasMany relation - search in related model
				if (!class_exists($model)) {
					return response()->json([]);
				}

				$parentModel = new $model();
				if (!method_exists($parentModel, $relation)) {
					return response()->json([]);
				}

				$relationQuery = $parentModel->$relation();
				$relatedModel = $relationQuery->getRelated();
				$relatedClass = get_class($relatedModel);
				$relatedTable = $relatedModel->getTable();
				$foreignKey = $relationQuery->getForeignKeyName(); // This is the FK in the related table (e.g., user_id in tiraggi)

				// Decode searchable keys
				$keys = json_decode(base64_decode($searchableKeys), true) ?? [];

				// For hasMany, we need to find records in the related table that have relations with the current table
				// The foreign key is in the related table (e.g., user_ndg in tiraggi table), pointing to the current table (users)
				// So we query the related table directly and filter by foreign key values that exist in current table
				$query = $relatedClass::query();

				// Get parent key name (e.g., "ndg" for User model)
				$parentKey = $parentModel->getKeyName();
				$parentTable = $parentModel->getTable();

				// Get all parent IDs that have at least one related record
				// The foreign key in the related table (e.g., user_ndg) points to parent key (e.g., ndg)
				if (!Schema::hasColumn($relatedTable, $foreignKey)) {
					return response()->json([]);
				}

				$parentIds = DB::table($relatedTable)
					->select($foreignKey)
					->whereNotNull($foreignKey)
					->distinct()
					->pluck($foreignKey)
					->toArray();

				if (empty($parentIds)) {
					return response()->json([]);
				}

				// Filter related records to only those that have relations with current table
				$query->whereIn($foreignKey, $parentIds);

				// Search in primary key or unique fields (exact match only)
				$searchFields = [];
				foreach ($keys as $keyInfo) {
					$field = $keyInfo["field"];
					if (Schema::hasColumn($relatedTable, $field)) {
						$searchFields[] = $field;
					}
				}

				if (empty($searchFields)) {
					return response()->json([]);
				}

				// Contains search (LIKE with wildcards)
				$query->where(function ($q) use ($term, $searchFields) {
					foreach ($searchFields as $field) {
						// Use LIKE for partial match (contains)
						$q->orWhere($field, "LIKE", "%{$term}%");
					}
				});

				$relatedRecords = $query->limit(10)->get();

				foreach ($relatedRecords as $record) {
					$displayValue = $this->getRelationDisplayValue($record, $relatedTable);

					// For Role and BackpackRole, also include the "name" field if available
					$modelName = class_basename($relatedClass);
					if (in_array($modelName, ["Role", "BackpackRole"]) && Schema::hasColumn($relatedTable, "name")) {
						$nameValue = method_exists($record, "getAttribute")
							? $record->getAttribute("name")
							: (isset($record->name)
								? $record->name
								: null);

						if ($nameValue !== null && $nameValue !== "") {
							// If displayValue already contains name (from getRelationDisplayValue), use it as is
							// Otherwise, combine name with the display value
							if (strpos($displayValue, $nameValue) === false) {
								$displayValue = $nameValue . " (" . $displayValue . ")";
							}
						}
					}

					$results[] = [
						"value" => $record->getKey(),
						"label" => $displayValue,
					];
				}
			} else {
				// BelongsTo relation - search in related table
				if (!Schema::hasTable($table) || !Schema::hasColumn($table, $relationColumn)) {
					return response()->json([]);
				}

				// Get foreign key info
				$foreignKeys = DB::select(
					"
					SELECT REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
					FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
					WHERE TABLE_SCHEMA = DATABASE()
					AND TABLE_NAME = ?
					AND COLUMN_NAME = ?
					AND REFERENCED_TABLE_NAME IS NOT NULL
				",
					[$table, $relationColumn]
				);

				if (empty($foreignKeys)) {
					return response()->json([]);
				}

				$referencedTable = $foreignKeys[0]->REFERENCED_TABLE_NAME;
				$referencedColumn = $foreignKeys[0]->REFERENCED_COLUMN_NAME ?? "id";

				// Get IDs that have relations in current table
				$relatedIds = DB::table($table)
					->whereNotNull($relationColumn)
					->distinct()
					->pluck($relationColumn)
					->toArray();

				if (empty($relatedIds)) {
					return response()->json([]);
				}

				// Try to get model class for referenced table
				$relatedModelClass = null;
				$modelPath = app_path("Models");
				if (is_dir($modelPath)) {
					$files = scandir($modelPath);
					foreach ($files as $file) {
						if (pathinfo($file, PATHINFO_EXTENSION) === "php") {
							$className = "App\Models\\" . pathinfo($file, PATHINFO_FILENAME);
							if (class_exists($className)) {
								$modelInstance = new $className();
								if (
									method_exists($modelInstance, "getTable") &&
									$modelInstance->getTable() === $referencedTable
								) {
									$relatedModelClass = $className;
									break;
								}
							}
						}
					}
				}

				if ($relatedModelClass) {
					// Use Eloquent model
					$query = $relatedModelClass::query()->whereIn($referencedColumn, $relatedIds);

					// Search in primary key or unique fields (contains match)
					// Try to find unique fields first
					$uniqueFields = DB::select(
						"
						SELECT COLUMN_NAME 
						FROM INFORMATION_SCHEMA.STATISTICS 
						WHERE TABLE_SCHEMA = DATABASE()
						AND TABLE_NAME = ?
						AND NON_UNIQUE = 0
						AND INDEX_NAME != 'PRIMARY'
						LIMIT 1
					",
						[$referencedTable]
					);

					if (!empty($uniqueFields)) {
						$uniqueField = $uniqueFields[0]->COLUMN_NAME;
						// Use LIKE for partial match
						$query->where($uniqueField, "LIKE", "%{$term}%");
					} else {
						// Use LIKE for partial match on primary key
						$query->where($referencedColumn, "LIKE", "%{$term}%");
					}

					$records = $query->limit(10)->get();

					foreach ($records as $record) {
						$displayValue = $this->getRelationDisplayValue($record, $referencedTable);

						// For Role and BackpackRole, also include the "name" field if available
						$modelName = class_basename($relatedModelClass);
						if (in_array($modelName, ["Role", "BackpackRole"]) && Schema::hasColumn($referencedTable, "name")) {
							$nameValue = method_exists($record, "getAttribute")
								? $record->getAttribute("name")
								: (isset($record->name)
									? $record->name
									: null);

							if ($nameValue !== null && $nameValue !== "") {
								// If displayValue already contains name (from getRelationDisplayValue), use it as is
								// Otherwise, combine name with the display value
								if (strpos($displayValue, $nameValue) === false) {
									$displayValue = $nameValue . " (" . $displayValue . ")";
								}
							}
						}

						$results[] = [
							"value" => $record->getKey(),
							"label" => $displayValue,
						];
					}
				} else {
					// Fallback to query builder
					$query = DB::table($referencedTable)->whereIn($referencedColumn, $relatedIds);

					// Try to find unique fields first
					$uniqueFields = DB::select(
						"
						SELECT COLUMN_NAME 
						FROM INFORMATION_SCHEMA.STATISTICS 
						WHERE TABLE_SCHEMA = DATABASE()
						AND TABLE_NAME = ?
						AND NON_UNIQUE = 0
						AND INDEX_NAME != 'PRIMARY'
						LIMIT 1
					",
						[$referencedTable]
					);

					if (!empty($uniqueFields)) {
						$uniqueField = $uniqueFields[0]->COLUMN_NAME;
						// Use LIKE for partial match
						$query->where($uniqueField, "LIKE", "%{$term}%");
					} else {
						// Use LIKE for partial match on primary key
						$query->where($referencedColumn, "LIKE", "%{$term}%");
					}

					$records = $query->limit(10)->get();

					foreach ($records as $record) {
						$keyValue = $record->$referencedColumn;
						// Get display value using helper method
						$displayValue = $this->getRelationDisplayValue($record, $referencedTable);
						// If helper returned empty, fallback to key value
						if (empty($displayValue)) {
							$displayValue = (string) $keyValue;
						}

						// For Role and BackpackRole, also include the "name" field if available
						if ($relatedModelClass) {
							$modelName = class_basename($relatedModelClass);
							if (
								in_array($modelName, ["Role", "BackpackRole"]) &&
								Schema::hasColumn($referencedTable, "name")
							) {
								$nameValue = isset($record->name) ? $record->name : null;

								if ($nameValue !== null && $nameValue !== "") {
									// If displayValue already contains name (from getRelationDisplayValue), use it as is
									// Otherwise, combine name with the display value
									if (strpos($displayValue, $nameValue) === false) {
										$displayValue = $nameValue . " (" . $displayValue . ")";
									}
								}
							}
						}

						$results[] = [
							"value" => $keyValue,
							"label" => $displayValue,
						];
					}
				}
			}
		} catch (\Exception $e) {
			Log::error("Autocomplete relation error: " . $e->getMessage(), [
				"exception" => $e,
				"trace" => $e->getTraceAsString(),
			]);
			return response()->json(["error" => $e->getMessage()], 500);
		}

		return response()->json($results);
	}
}
