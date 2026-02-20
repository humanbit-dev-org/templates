<?php

namespace App\Http\Controllers\Admin\Helper;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use App\Http\Controllers\Admin\Helper\Core\FieldConfigHandler;

class FilterHelper
{
	/**
	 * Get filter configuration for the CRUD list view
	 */
	public static function getFilterConfiguration(CrudPanel $crud): array
	{
		// Check if any filters are applied (excluding pagination)
		$hasFilters = collect(request()->except(["page", "persistent-table", "_token"]))
			->filter(function ($value) {
				return $value !== null && $value !== "";
			})
			->isNotEmpty();

		// Get all columns excluding pagination and other non-filterable fields
		$columns = collect($crud->columns())->filter(function ($column) {
			return isset($column["name"]) &&
				!in_array($column["name"], ["order"]) &&
				(!isset($column["relation_type"]) || $column["relation_type"] !== "HasMany");
		});

		// Define fields to hide from filters
		$hiddenFields = [
			"token_expire",
			"remember_token",
			"import",
			"token",
			"password",
			"email_verified_at",
			"token_verified",
		];

		// Filter out hidden fields
		$columns = $columns->filter(function ($column) use ($hiddenFields) {
			return !in_array($column["name"], $hiddenFields);
		});

		// Define advanced fields that require checkbox to show
		$advancedFields = [
			"backpack_role_id",
			"role_id", // Role relation
			"created_at",
			"updated_at",
		];

		// Get table name for select field detection and FK detection
		$model = $crud->model;
		$tableName = is_string($model) ? (new $model())->getTable() : $model->getTable();

		// Group columns by type, separating normal and advanced
		$textColumns = $columns->filter(function ($column) use ($tableName, $advancedFields) {
			$type = $column["type"] ?? "";
			// Exclude foreign keys (both _id and custom FKs like filiale_codice)
			$isForeignKey = self::isForeignKeyColumn($column["name"], $tableName);
			return !$isForeignKey &&
				!str_ends_with($column["name"], "_path") &&
				$type != "switch" &&
				$type != "date" &&
				$type != "datetime" &&
				$column["name"] !== "page" &&
				!FieldConfigHandler::isSelectField($column["name"], $tableName) &&
				!in_array($column["name"], $advancedFields);
		});

		// Advanced text columns (require checkbox)
		$advancedTextColumns = $columns->filter(function ($column) use ($tableName, $advancedFields) {
			$type = $column["type"] ?? "";
			// Exclude foreign keys (both _id and custom FKs like filiale_codice)
			$isForeignKey = self::isForeignKeyColumn($column["name"], $tableName);
			return !$isForeignKey &&
				!str_ends_with($column["name"], "_path") &&
				$type != "switch" &&
				$type != "date" &&
				$type != "datetime" &&
				$column["name"] !== "page" &&
				!FieldConfigHandler::isSelectField($column["name"], $tableName) &&
				in_array($column["name"], $advancedFields);
		});

		// Date filters (excluding advanced dates)
		$dateColumns = $columns->filter(function ($column) use ($advancedFields) {
			$type = $column["type"] ?? "";
			return ($type == "date" || $type == "datetime") && !in_array($column["name"], $advancedFields);
		});

		// Advanced date filters (created_at, updated_at)
		$advancedDateColumns = $columns->filter(function ($column) use ($advancedFields) {
			$type = $column["type"] ?? "";
			return ($type == "date" || $type == "datetime") && in_array($column["name"], $advancedFields);
		});

		// Path fields
		$pathFields = $columns->filter(function ($column) {
			return str_ends_with($column["name"], "_path");
		});

		// Boolean filters (excluding path fields)
		$booleanColumns = $columns->filter(function ($column) {
			return ($column["type"] ?? "") == "switch" && !str_ends_with($column["name"], "_path");
		});

		// Select filters (status, custom enums, etc.)
		$selectColumns = $columns->filter(function ($column) use ($tableName) {
			return FieldConfigHandler::isSelectField($column["name"], $tableName);
		});

		$relationColumns = $columns->filter(function ($column) use ($advancedFields, $tableName) {
			return (self::isForeignKeyColumn($column["name"], $tableName) || $column["name"] === "page") &&
				!in_array($column["name"], $advancedFields);
		});

		// Advanced relation columns (require checkbox)
		$advancedRelationColumns = $columns->filter(function ($column) use ($advancedFields, $tableName) {
			return (self::isForeignKeyColumn($column["name"], $tableName) || $column["name"] === "page") &&
				in_array($column["name"], $advancedFields);
		});

		// Get HasMany relations from model (inverse relations)
		$hasManyRelations = self::getHasManyRelations($crud->model) ?? [];
		// Group by relation name (consolidate multiple search fields into one filter)
		$hasManyRelationsGrouped = self::groupHasManyRelationsByName($hasManyRelations);
		$hasManyRelationsCount = self::getHasManyRelationsFilterCount($hasManyRelationsGrouped);

		// Get count of applied filters per section
		$textFilterCount = self::getTextFilterCount($textColumns);
		$dateFilterCount = self::getDateFilterCount($dateColumns);
		$booleanFilterCount = self::getBooleanFilterCount($booleanColumns);
		$selectFilterCount = self::getSelectFilterCount($selectColumns);
		$pathFilterCount = self::getPathFilterCount($pathFields);
		$relationFilterCount = self::getRelationFilterCount($relationColumns);

		// Advanced filters count
		$advancedTextFilterCount = self::getTextFilterCount($advancedTextColumns);
		$advancedRelationFilterCount = self::getRelationFilterCount($advancedRelationColumns);
		$advancedDateFilterCount = self::getDateFilterCount($advancedDateColumns);
		$advancedFilterCount = $advancedTextFilterCount + $advancedRelationFilterCount + $advancedDateFilterCount;

		// Check if specific sections have active filters
		$hasTextFilters = $textFilterCount > 0;
		$hasDateFilters = $dateFilterCount > 0;
		$hasBooleanFilters = $booleanFilterCount > 0;
		$hasSelectFilters = $selectFilterCount > 0;
		$hasPathFilters = $pathFilterCount > 0;
		$hasRelationFilters = $relationFilterCount > 0;
		$hasAdvancedFilters = $advancedFilterCount > 0;

		// Determine if multiple filter sections are active
		$activeFilterSections = 0;
		if ($hasTextFilters) {
			$activeFilterSections++;
		}
		if ($hasDateFilters) {
			$activeFilterSections++;
		}
		if ($hasBooleanFilters) {
			$activeFilterSections++;
		}
		if ($hasSelectFilters) {
			$activeFilterSections++;
		}
		if ($hasPathFilters) {
			$activeFilterSections++;
		}
		if ($hasRelationFilters) {
			$activeFilterSections++;
		}

		// All accordions stay closed by default - no automatic opening
		$openTextFilters = false;
		$openDateFilters = false;
		$openBooleanFilters = false;
		$openSelectFilters = false;
		$openPathFilters = false;
		$openRelationFilters = false;
		$openAdvancedFilters = false;

		// Check if there are any non-relation filters active (for smart filtering of relation options)
		$hasNonRelationFilters =
			$hasTextFilters || $hasDateFilters || $hasBooleanFilters || $hasSelectFilters || $hasPathFilters;

		// Check if there are ANY filters active (for smart counter display)
		// This includes relation filters too!
		$hasAnyActiveFilters = $hasFilters;

		// Check if there are any "not empty" or "empty" filters active
		$hasNotEmptyFilters = collect(request()->except(["page", "persistent-table"]))
			->filter(function ($value, $key) {
				return (str_ends_with($key, "_not_empty") || str_ends_with($key, "_empty")) && $value === "1";
			})
			->isNotEmpty();

		return [
			"hasFilters" => $hasFilters,
			"columns" => $columns,
			"textColumns" => $textColumns,
			"dateColumns" => $dateColumns,
			"pathFields" => $pathFields,
			"booleanColumns" => $booleanColumns,
			"selectColumns" => $selectColumns,
			"relationColumns" => $relationColumns,
			"advancedTextColumns" => $advancedTextColumns,
			"advancedRelationColumns" => $advancedRelationColumns,
			"advancedDateColumns" => $advancedDateColumns,
			"advancedFields" => $advancedFields,
			"tableName" => $tableName,
			"textFilterCount" => $textFilterCount,
			"dateFilterCount" => $dateFilterCount,
			"booleanFilterCount" => $booleanFilterCount,
			"selectFilterCount" => $selectFilterCount,
			"pathFilterCount" => $pathFilterCount,
			"relationFilterCount" => $relationFilterCount,
			"advancedFilterCount" => $advancedFilterCount,
			"hasManyRelations" => $hasManyRelationsGrouped,
			"hasManyRelationsCount" => $hasManyRelationsCount,
			"hasTextFilters" => $hasTextFilters,
			"hasDateFilters" => $hasDateFilters,
			"hasBooleanFilters" => $hasBooleanFilters,
			"hasSelectFilters" => $hasSelectFilters,
			"hasPathFilters" => $hasPathFilters,
			"hasRelationFilters" => $hasRelationFilters,
			"hasAdvancedFilters" => $advancedFilterCount > 0,
			"hasNonRelationFilters" => $hasNonRelationFilters,
			"hasAnyActiveFilters" => $hasAnyActiveFilters,
			"hasNotEmptyFilters" => $hasNotEmptyFilters,
			"openTextFilters" => $openTextFilters,
			"openDateFilters" => $openDateFilters,
			"openBooleanFilters" => $openBooleanFilters,
			"openSelectFilters" => $openSelectFilters,
			"openPathFilters" => $openPathFilters,
			"openRelationFilters" => $openRelationFilters,
			"openAdvancedFilters" => $openAdvancedFilters,
		];
	}

	/**
	 * Get HTML input type for a column based on database type and name
	 */
	public static function getInputType($column, $tableName): string
	{
		$columnName = $column["name"];

		// Check for email fields first (specific fields)
		if ($columnName === "email" || str_ends_with($columnName, "_email")) {
			return "email";
		}

		// Check for URL fields
		if (str_contains($columnName, "url") || str_contains($columnName, "website")) {
			return "url";
		}

		// Get database column type
		if (\Illuminate\Support\Facades\Schema::hasColumn($tableName, $columnName)) {
			$columnType = strtolower(\Illuminate\Support\Facades\Schema::getColumnType($tableName, $columnName));

			// Integer types - handle all possible aliases
			if (
				$columnType === "integer" ||
				$columnType === "int" || // MySQL
				$columnType === "bigint" ||
				$columnType === "biginteger" ||
				$columnType === "smallint" ||
				$columnType === "smallinteger" ||
				$columnType === "tinyint" ||
				$columnType === "mediumint" ||
				str_contains($columnType, "int")
			) {
				return "number";
			}

			// Decimal/Float types
			if (
				$columnType === "decimal" ||
				$columnType === "numeric" ||
				$columnType === "float" ||
				$columnType === "double" ||
				$columnType === "real" ||
				str_contains($columnType, "decimal") ||
				str_contains($columnType, "float")
			) {
				return "number";
			}
		}

		return "text";
	}

	/**
	 * Get step attribute for number inputs
	 */
	public static function getNumberStep($column, $tableName): string
	{
		if (!\Illuminate\Support\Facades\Schema::hasColumn($tableName, $column["name"])) {
			return "1";
		}

		$columnType = strtolower(\Illuminate\Support\Facades\Schema::getColumnType($tableName, $column["name"]));

		// Check if it's a decimal/float type (needs decimal step)
		if (
			$columnType === "decimal" ||
			$columnType === "numeric" ||
			$columnType === "float" ||
			$columnType === "double" ||
			$columnType === "real" ||
			str_contains($columnType, "decimal") ||
			str_contains($columnType, "float")
		) {
			return "0.01";
		}

		// Integer types get step="1"
		return "1";
	}

	/**
	 * Get active filters for display
	 */
	public static function getActiveFilters(Collection $columns): Collection
	{
		$filters = collect(request()->except(["page", "persistent-table"]))->filter(function ($value, $key) {
			return $value !== null && $value !== "" && $key !== "_token" && $key !== "last_filter_section";
		});

		// Group date range filters to avoid duplicates
		$processedFilters = collect();
		$processedDateRanges = collect();

		// Track fields with conflicting not_empty/empty filters
		$conflictingFields = collect();

		foreach ($filters as $key => $value) {
			// Handle date range filters
			if (str_ends_with($key, "_from") || str_ends_with($key, "_to")) {
				$baseField = str_replace(["_from", "_to"], "", $key);

				// Only process each date range once
				if (!$processedDateRanges->contains($baseField)) {
					$fromValue = request()->get($baseField . "_from");
					$toValue = request()->get($baseField . "_to");

					// Only add if at least one value exists
					if ($fromValue || $toValue) {
						$processedFilters->put($baseField, $baseField); // Use base field name as key
						$processedDateRanges->push($baseField);
					}
				}
			} elseif (str_ends_with($key, "_not_empty") || str_ends_with($key, "_empty")) {
				// Handle not_empty and empty filters
				$baseField = str_replace(["_not_empty", "_empty"], "", $key);

				// Check if this field has conflicting filters
				$hasNotEmpty = request()->get($baseField . "_not_empty") === "1";
				$hasEmpty = request()->get($baseField . "_empty") === "1";

				if ($hasNotEmpty && $hasEmpty) {
					// Conflicting filters detected - mark this field
					$conflictingFields->push($baseField);
					// Only add the most recent one (or prefer _not_empty if both exist)
					// In practice, we'll add the one that matches the current key
					if (str_ends_with($key, "_not_empty")) {
						$processedFilters->put($key, $value);
						// Remove the conflicting _empty filter if it was already added
						$processedFilters->forget($baseField . "_empty");
					} else {
						// If _empty is being processed, check if _not_empty was already added
						if ($processedFilters->has($baseField . "_not_empty")) {
							// Skip this _empty filter, keep the _not_empty
							continue;
						} else {
							$processedFilters->put($key, $value);
						}
					}
				} else {
					// No conflict, add normally
					$processedFilters->put($key, $value);
				}
			} else {
				// Regular filters (including hasMany relation filters)
				$processedFilters->put($key, $value);
			}
		}

		return $processedFilters;
	}

	/**
	 * Cache for foreign keys per table
	 */
	private static $foreignKeysCache = [];

	/**
	 * Get all foreign keys for a table by inspecting database schema
	 *
	 * @param string $tableName Table name
	 * @return array Array of foreign key info: ['column' => 'referenced_table']
	 */
	private static function getForeignKeysForTable(string $tableName): array
	{
		// Return from cache if available
		if (isset(self::$foreignKeysCache[$tableName])) {
			return self::$foreignKeysCache[$tableName];
		}

		try {
			// Query database schema for foreign keys
			$foreignKeys = DB::select(
				"
				SELECT 
					COLUMN_NAME,
					REFERENCED_TABLE_NAME,
					REFERENCED_COLUMN_NAME
				FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
				WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = ?
				AND REFERENCED_TABLE_NAME IS NOT NULL
			",
				[$tableName]
			);

			// Build array: column_name => ['table' => referenced_table, 'column' => referenced_column]
			$result = [];
			foreach ($foreignKeys as $fk) {
				$result[$fk->COLUMN_NAME] = [
					"table" => $fk->REFERENCED_TABLE_NAME,
					"column" => $fk->REFERENCED_COLUMN_NAME,
				];
			}

			// Cache result
			self::$foreignKeysCache[$tableName] = $result;

			return $result;
		} catch (\Exception $e) {
			// Fallback: return empty array if query fails
			return [];
		}
	}

	/**
	 * Check if a column name is a foreign key (automatically detected from schema)
	 *
	 * @param string $columnName Column name
	 * @param string|null $tableName Table name (optional, will try to detect if not provided)
	 * @return bool
	 */
	public static function isForeignKeyColumn(string $columnName, ?string $tableName = null): bool
	{
		// Standard foreign keys ending with _id (quick check)
		if (str_ends_with($columnName, "_id")) {
			return true;
		}

		// If table name provided, check schema
		if ($tableName) {
			$foreignKeys = self::getForeignKeysForTable($tableName);
			return isset($foreignKeys[$columnName]);
		}

		// Fallback: check common custom foreign keys (for backward compatibility)
		$customForeignKeys = ["user_ndg", "delibera_numero_pratica", "tiraggio_numero_pratica", "filiale_codice"];

		return in_array($columnName, $customForeignKeys);
	}

	/**
	 * Find Model class for a given table name by scanning App\Models
	 *
	 * @param string $tableName Table name
	 * @return string|null Model class name or null if not found
	 */
	private static function findModelForTable(string $tableName): ?string
	{
		$namespace = "App\\Models\\";
		$modelsPath = app_path("Models");
		if (!is_dir($modelsPath)) {
			return null;
		}

		$files = scandir($modelsPath);
		foreach ($files as $file) {
			if (pathinfo($file, PATHINFO_EXTENSION) !== "php") {
				continue;
			}

			$modelClass = $namespace . pathinfo($file, PATHINFO_FILENAME);

			if (!class_exists($modelClass)) {
				continue;
			}

			try {
				$instance = new $modelClass();
				if (method_exists($instance, "getTable") && $instance->getTable() === $tableName) {
					return $modelClass;
				}
			} catch (\Exception $e) {
				continue;
			}
		}

		return null;
	}

	/**
	 * Get relation method name from foreign key column name (automatically derives from Model class name)
	 *
	 * @param string $foreignKeyName Column name
	 * @param string|null $tableName Table name (optional, for automatic detection)
	 * @return string Relation name (camelCase from Model class)
	 */
	private static function getRelationNameFromForeignKey(string $foreignKeyName, ?string $tableName = null): string
	{
		// If table name provided, use automatic detection
		if ($tableName) {
			$foreignKeys = self::getForeignKeysForTable($tableName);

			if (isset($foreignKeys[$foreignKeyName])) {
				$referencedTable = $foreignKeys[$foreignKeyName]["table"];
				$modelClass = self::findModelForTable($referencedTable);

				if ($modelClass) {
					$modelName = class_basename($modelClass);
					return \Illuminate\Support\Str::camel($modelName);
				}

				return \Illuminate\Support\Str::singular($referencedTable);
			}
		}

		// Fallback: derive from column name
		if (str_ends_with($foreignKeyName, "_id")) {
			return str_replace("_id", "", $foreignKeyName);
		}

		if (str_ends_with($foreignKeyName, "_codice")) {
			return str_replace("_codice", "", $foreignKeyName);
		}

		if (str_ends_with($foreignKeyName, "_ndg")) {
			return str_replace("_ndg", "", $foreignKeyName);
		}

		return $foreignKeyName;
	}

	/**
	 * Clean field name for display (remove underscores, _id suffix, etc.)
	 */
	public static function cleanFieldName(string $fieldName): string
	{
		// Remove custom foreign key suffixes
		$fieldName = str_replace(["_ndg", "_numero_pratica", "_codice"], "", $fieldName);

		// Remove _id suffix
		$fieldName = str_replace("_id", "", $fieldName);

		// Replace underscores with spaces
		$fieldName = str_replace("_", " ", $fieldName);

		// Capitalize first letter of each word
		$fieldName = ucwords($fieldName);

		return $fieldName;
	}

	/**
	 * Get display value for active filter
	 */
	public static function getFilterDisplayValue($key, $value, $columns, $crud): string
	{
		// Handle not_empty and empty filters
		if (str_ends_with($key, "_not_empty")) {
			$originalKey = str_replace("_not_empty", "", $key);
			$column = $columns->firstWhere("name", $originalKey);
			if ($value === "1") {
				// Return translated "non vuoto" using Backpack translations
				return trans("backpack::crud.not_empty");
			}
		}

		if (str_ends_with($key, "_empty")) {
			$originalKey = str_replace("_empty", "", $key);
			$column = $columns->firstWhere("name", $originalKey);
			if ($value === "1") {
				// Return translated "vuoto" using Backpack translations
				return trans("backpack::crud.empty");
			}
		}

		// Handle date range filters - check if this is a date range field
		$fromValue = request()->get($key . "_from");
		$toValue = request()->get($key . "_to");
		if ($fromValue || $toValue) {
			$column = $columns->firstWhere("name", $key);
			if ($column) {
				$label = isset($column["label"]) ? $column["label"] : $key;

				if ($fromValue && $toValue) {
					return $label . ": " . $fromValue . " - " . $toValue;
				} elseif ($fromValue) {
					return $label . ": da " . $fromValue;
				} elseif ($toValue) {
					return $label . ": fino a " . $toValue;
				}
			}
		}

		$columnName = $key === "page_filter" ? "page" : $key;
		$column = $columns->firstWhere("name", $columnName);

		if (!$column) {
			return $value;
		}

		// Get table name
		$model = $crud->model;
		$tableName = is_string($model) ? (new $model())->getTable() : $model->getTable();

		// Handle path fields
		if (str_ends_with($columnName, "_path")) {
			return trans("backpack::filters.with_file");
		}

		// Handle select fields (status, enums, etc.)
		elseif (FieldConfigHandler::isSelectField($columnName, $tableName)) {
			$config = FieldConfigHandler::getSelectFieldConfig($columnName, $tableName);
			return $config["options"][$value] ?? $value;
		}

		// Handle relation fields
		elseif (self::isForeignKeyColumn($columnName, $tableName)) {
			return self::getRelationDisplayValue($columnName, $value, $crud);
		}

		// Handle boolean fields
		elseif (($column["type"] ?? "") == "switch") {
			return $value == "1" ? trans("backpack::filters.yes") : trans("backpack::filters.no");
		}

		return $value;
	}

	/**
	 * Get options for select fields (status, custom enums, etc.)
	 */
	public static function getSelectOptions($column, $tableName): array|null
	{
		$config = FieldConfigHandler::getSelectFieldConfig($column["name"], $tableName);
		return $config ? $config["options"] : [];
	}

	/**
	 * Get options for relation filter with efficient counting and dynamic filtering
	 */
	public static function getRelationOptions($column, $crud): array
	{
		// Get table name for automatic FK detection
		$currentTableName = $crud->model->getTable();

		// First try to get the related model from the actual relation method
		$relationName = self::getRelationNameFromForeignKey($column["name"], $currentTableName);
		$relatedModel = null;

		// Check if the relation method exists in the model
		if (method_exists($crud->model, $relationName)) {
			try {
				$relationQuery = $crud->model->$relationName();
				$relatedModel = get_class($relationQuery->getRelated());
			} catch (\Exception $e) {
				// Fallback to the old method if relation method fails
				$relatedModelName = Str::studly($relationName);
				$relatedModel = "App\Models\\" . $relatedModelName;
			}
		} else {
			// Fallback to the old method
			$relatedModelName = Str::studly($relationName);
			$relatedModel = "App\Models\\" . $relatedModelName;
		}

		$options = [];

		if (class_exists($relatedModel)) {
			// Get current model info for efficient counting
			$currentTableName = $crud->model->getTable();
			// Use the actual column name from the request, not reconstructed
			$foreignKeyColumn = $column["name"];

			// Get the referenced column from the foreign key constraint
			$foreignKeys = self::getForeignKeysForTable($currentTableName);
			$referencedColumn = $foreignKeys[$foreignKeyColumn]["column"] ?? null;

			// If no referenced column found, try to get it from the related model's primary key
			if (!$referencedColumn) {
				$relatedModelInstance = new $relatedModel();
				$referencedColumn = $relatedModelInstance->getKeyName();
			}

			// Get all related records first
			$relatedItems = call_user_func([$relatedModel, "all"]);

			// Build query for counting with active filters applied
			$countsQuery = DB::table($currentTableName)
				->select($foreignKeyColumn, DB::raw("COUNT(*) as relation_count"))
				->whereNotNull($foreignKeyColumn);

			// Apply active filters to the count query (excluding the current relation filter and pagination)
			$activeFilters = collect(
				request()->except([
					"page",
					"persistent-table",
					"_token",
					$column["name"], // Exclude current filter
					"page_filter",
				])
			);

			foreach ($activeFilters as $filterKey => $filterValue) {
				if ($filterValue === "" || $filterValue === null) {
					continue;
				}

				// Check if column exists in the table
				if (!\Illuminate\Support\Facades\Schema::hasColumn($currentTableName, $filterKey)) {
					continue;
				}

				// Apply the same filter logic as FilterHandler
				if (str_ends_with($filterKey, "_path")) {
					// Path field filter
					if ($filterValue === "1") {
						$countsQuery->whereNotNull($filterKey)->where($filterKey, "!=", "");
					}
				} elseif (self::isForeignKeyColumn($filterKey, $currentTableName)) {
					// Relation filter
					$countsQuery->where($filterKey, "=", $filterValue);
				} else {
					// Get column type
					$columnType = \Illuminate\Support\Facades\Schema::getColumnType($currentTableName, $filterKey);

					if ($columnType === "boolean" || $columnType === "tinyint") {
						// Boolean filter
						if ($filterValue === "1" || $filterValue === "0") {
							$countsQuery->where($filterKey, "=", (int) $filterValue);
						}
					} else {
						// Text filter with LIKE
						$countsQuery->where($filterKey, "LIKE", "%" . $filterValue . "%");
					}
				}
			}

			// Get counts grouped by foreign key
			$counts = $countsQuery->groupBy($foreignKeyColumn)->pluck("relation_count", $foreignKeyColumn);

			// Check if there are active filters (to decide whether to hide 0-count options)
			$hasActiveFilters = $activeFilters
				->filter(function ($value) {
					return $value !== null && $value !== "";
				})
				->isNotEmpty();

			// Get the current model name for clarity
			$currentModelName = class_basename($crud->model);

			$options = $relatedItems
				->mapWithKeys(function ($item) use (
					$counts,
					$hasActiveFilters,
					$currentModelName,
					$referencedColumn,
					$relatedModel
				) {
					// Use the referenced column value as key (e.g., numero_pratica, codice, ndg)
					// This matches the key used in $counts which is grouped by foreignKeyColumn
					if ($referencedColumn && isset($item->$referencedColumn)) {
						$itemKey = $item->$referencedColumn;
					} else {
						// Fallback to primary key if referenced column not available
						$itemKey = $item->getKey();
					}

					// Get count from the pre-calculated counts array
					$relationCount = $counts[$itemKey] ?? 0;

					// If there are active filters and this item has 0 results, skip it
					if ($hasActiveFilters && $relationCount === 0) {
						return [];
					}

					// Use unique field if available, otherwise primary key (same logic as autocomplete)
					$relatedModelInstance = new $relatedModel();
					$relatedTable = $relatedModelInstance->getTable();

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
						[$relatedTable]
					);

					$displayValue = null;
					$uniqueValue = null;
					// If unique field exists, try to use it
					if (!empty($uniqueFields)) {
						$uniqueField = $uniqueFields[0]->COLUMN_NAME;

						// Get value from item
						if (method_exists($item, "getAttribute")) {
							$uniqueValue = $item->getAttribute($uniqueField);
						} elseif (isset($item->$uniqueField)) {
							$uniqueValue = $item->$uniqueField;
						} else {
							$uniqueValue = null;
						}

						// Use unique field if not empty
						if ($uniqueValue !== null && $uniqueValue !== "") {
							$displayValue = (string) $uniqueValue;
						}
					}

					// Fallback to primary key if unique field not available or empty
					if ($displayValue === null) {
						$displayValue = method_exists($item, "getKey") ? (string) $item->getKey() : (string) $itemKey;
					}

					// For Role and BackpackRole models, also include the "name" field if available
					$modelName = class_basename($relatedModel);
					if (
						in_array($modelName, ["Role", "BackpackRole"]) &&
						\Illuminate\Support\Facades\Schema::hasColumn($relatedTable, "name")
					) {
						$nameValue = method_exists($item, "getAttribute")
							? $item->getAttribute("name")
							: (isset($item->name)
								? $item->name
								: null);

						if ($nameValue !== null && $nameValue !== "") {
							// Combine name with unique/primary key value
							$displayValue = $nameValue . " (" . $displayValue . ")";
						}
					}

					// Use safe ASCII characters that work everywhere
					$displayValue .= " → ({$relationCount}) {$currentModelName}";

					return [$itemKey => $displayValue];
				})
				->toArray();

			// Add "Non collegati" option for records without this relation
			$nullCount = DB::table($currentTableName)->whereNull($foreignKeyColumn)->count();

			if ($nullCount > 0) {
				$options["__null__"] = "Non collegati → ({$nullCount}) {$currentModelName}";
			}

			// Sort options alphabetically
			asort($options);
		}

		return $options;
	}

	/**
	 * Check if relation filter should use modal (for large datasets)
	 */
	public static function shouldUseModal($column, $crud): bool
	{
		$options = self::getRelationOptions($column, $crud);
		return count($options) > 100; // Use modal only for very large datasets
	}

	/**
	 * Get text filter count
	 */
	private static function getTextFilterCount(Collection $textColumns): int
	{
		return collect(request()->all())
			->filter(function ($value, $key) use ($textColumns) {
				// Check for regular text filters
				if ($textColumns->contains("name", $key) && $value !== null && $value !== "") {
					return true;
				}
				// Check for not_empty filters
				if (str_ends_with($key, "_not_empty") && $value === "1") {
					$originalKey = str_replace("_not_empty", "", $key);
					return $textColumns->contains("name", $originalKey);
				}
				// Check for empty filters
				if (str_ends_with($key, "_empty") && $value === "1") {
					$originalKey = str_replace("_empty", "", $key);
					return $textColumns->contains("name", $originalKey);
				}
				return false;
			})
			->count();
	}

	/**
	 * Get boolean filter count
	 */
	private static function getBooleanFilterCount(Collection $booleanColumns): int
	{
		return collect(request()->all())
			->filter(function ($value, $key) use ($booleanColumns) {
				return $booleanColumns->contains("name", $key) && $value !== null && $value !== "";
			})
			->count();
	}

	/**
	 * Get date filter count
	 */
	private static function getDateFilterCount(Collection $dateColumns): int
	{
		$processedDateRanges = collect();

		return collect(request()->all())
			->filter(function ($value, $key) use ($dateColumns, &$processedDateRanges) {
				// Skip UI-only fields (radio button toggles)
				if (str_ends_with($key, "_type")) {
					return false;
				}

				// Handle regular date filters
				if ($dateColumns->contains("name", $key) && $value !== null && $value !== "") {
					return true;
				}

				// Handle date range filters
				if (str_ends_with($key, "_from") || str_ends_with($key, "_to")) {
					$baseField = str_replace(["_from", "_to"], "", $key);

					// Only count each date range once
					if (!$processedDateRanges->contains($baseField) && $dateColumns->contains("name", $baseField)) {
						$fromValue = request()->get($baseField . "_from");
						$toValue = request()->get($baseField . "_to");

						// Count if at least one value exists
						if ($fromValue || $toValue) {
							$processedDateRanges->push($baseField);
							return true;
						}
					}
				}

				return false;
			})
			->count();
	}

	/**
	 * Get select filter count
	 */
	private static function getSelectFilterCount(Collection $selectColumns): int
	{
		return collect(request()->all())
			->filter(function ($value, $key) use ($selectColumns) {
				return $selectColumns->contains("name", $key) && $value !== null && $value !== "";
			})
			->count();
	}

	/**
	 * Get path filter count
	 */
	private static function getPathFilterCount(Collection $pathFields): int
	{
		return collect(request()->all())
			->filter(function ($value, $key) use ($pathFields) {
				return $pathFields->contains("name", $key) && $value !== null && $value !== "";
			})
			->count();
	}

	/**
	 * Get relation filter count
	 */
	private static function getRelationFilterCount(Collection $relationColumns): int
	{
		return collect(request()->all())
			->filter(function ($value, $key) use ($relationColumns) {
				if ($key === "page_filter" && $value !== null && $value !== "") {
					return true;
				}
				// Check for regular relation filters
				if ($relationColumns->contains("name", $key) && $value !== null && $value !== "") {
					return true;
				}
				// Check for not_empty filters on relations
				if (str_ends_with($key, "_not_empty") && ($value === "1" || $value === 1 || $value === "true")) {
					$originalKey = str_replace("_not_empty", "", $key);
					return $relationColumns->contains("name", $originalKey);
				}
				// Check for empty filters on relations
				if (str_ends_with($key, "_empty") && ($value === "1" || $value === 1 || $value === "true")) {
					$originalKey = str_replace("_empty", "", $key);
					return $relationColumns->contains("name", $originalKey);
				}
				return false;
			})
			->count();
	}

	/**
	 * Get the active filter field from a model class
	 * Checks for $activeFilterField property or getActiveFilterField() method
	 *
	 * @param string $modelClass Model class name
	 * @return string|null Field name or null if not set
	 */
	private static function getActiveFilterFieldFromModel(string $modelClass): ?string
	{
		if (!class_exists($modelClass)) {
			return null;
		}

		try {
			// Check for $activeFilterField property (static or instance) using reflection
			$reflection = new \ReflectionClass($modelClass);
			if ($reflection->hasProperty("activeFilterField")) {
				$property = $reflection->getProperty("activeFilterField");
				$property->setAccessible(true);

				// If property is static, get value directly; otherwise create instance
				if ($property->isStatic()) {
					$value = $property->getValue();
				} else {
					$instance = $reflection->newInstanceWithoutConstructor();
					$value = $property->getValue($instance);
				}

				if (!empty($value) && is_string($value)) {
					return $value;
				}
			}

			// Check for getActiveFilterField() method
			if ($reflection->hasMethod("getActiveFilterField")) {
				$method = $reflection->getMethod("getActiveFilterField");
				if ($method->isStatic() || $method->isPublic()) {
					$instance = $reflection->newInstanceWithoutConstructor();
					$value = $method->invoke($instance);
					if (!empty($value) && is_string($value)) {
						return $value;
					}
				}
			}
		} catch (\Exception $e) {
			// If anything fails, return null
			return null;
		}

		return null;
	}

	/**
	 * Get relation display value for active filters
	 */
	private static function getRelationDisplayValue($columnName, $value, $crud): string
	{
		// Get table name for automatic FK detection
		$currentTableName = $crud->model->getTable();

		// Get related model using the same improved logic
		$relationName = self::getRelationNameFromForeignKey($columnName, $currentTableName);
		$relatedModel = null;

		// Check if the relation method exists in the model
		if (method_exists($crud->model, $relationName)) {
			try {
				$relationQuery = $crud->model->$relationName();
				$relatedModel = get_class($relationQuery->getRelated());
			} catch (\Exception $e) {
				// Fallback
				$relatedModelName = Str::studly($relationName);
				$relatedModel = "App\\Models\\" . $relatedModelName;
			}
		} else {
			// Fallback
			$relatedModelName = Str::studly($relationName);
			$relatedModel = "App\\Models\\" . $relatedModelName;
		}

		$displayValue = "ID: " . $value; // Default fallback

		if (class_exists($relatedModel)) {
			// Get the referenced column from the foreign key constraint
			$foreignKeys = self::getForeignKeysForTable($currentTableName);
			$referencedColumn = $foreignKeys[$columnName]["column"] ?? null;

			// Try to find the item using the referenced column (for custom FKs)
			$item = null;
			if ($referencedColumn) {
				$item = call_user_func([$relatedModel, "where"], $referencedColumn, $value)->first();
			}

			// Fallback to primary key search
			if (!$item) {
				$item = call_user_func([$relatedModel, "find"], $value);
			}

			if ($item) {
				// Get table name for unique field lookup
				$relatedTable = is_string($relatedModel) ? (new $relatedModel())->getTable() : $relatedModel->getTable();
				$relatedModelInstance = is_string($relatedModel) ? new $relatedModel() : $relatedModel;
				$primaryKey = method_exists($item, "getKey") ? (string) $item->getKey() : (string) $value;

				// Check if model has activeFilterField property/method
				$activeFilterField = self::getActiveFilterFieldFromModel($relatedModel);

				// If model specifies activeFilterField, use it
				if ($activeFilterField && \Illuminate\Support\Facades\Schema::hasColumn($relatedTable, $activeFilterField)) {
					$fieldValue = method_exists($item, "getAttribute")
						? $item->getAttribute($activeFilterField)
						: (isset($item->$activeFilterField)
							? $item->$activeFilterField
							: null);

					if ($fieldValue !== null && $fieldValue !== "") {
						$displayValue = (string) $fieldValue;

						// For Role and BackpackRole, still include name if available
						$modelName = class_basename($relatedModel);
						if (
							in_array($modelName, ["Role", "BackpackRole"]) &&
							\Illuminate\Support\Facades\Schema::hasColumn($relatedTable, "name")
						) {
							$nameValue = method_exists($item, "getAttribute")
								? $item->getAttribute("name")
								: (isset($item->name)
									? $item->name
									: null);

							if ($nameValue !== null && $nameValue !== "") {
								$displayValue = $nameValue . " (" . $displayValue . ")";
							}
						}
					} else {
						// Field is empty, fallback to primary key
						$displayValue = $primaryKey;
					}
				} else {
					// If activeFilterField is not set or empty, use primary key directly
					$displayValue = $primaryKey;

					// For Role and BackpackRole, still include name if available
					$modelName = class_basename($relatedModel);
					if (
						in_array($modelName, ["Role", "BackpackRole"]) &&
						\Illuminate\Support\Facades\Schema::hasColumn($relatedTable, "name")
					) {
						$nameValue = method_exists($item, "getAttribute")
							? $item->getAttribute("name")
							: (isset($item->name)
								? $item->name
								: null);

						if ($nameValue !== null && $nameValue !== "") {
							$displayValue = $nameValue . " (" . $displayValue . ")";
						}
					}
				}
			}
		}

		return $displayValue;
	}

	/**
	 * Get display value and field name for hasMany relation filter: unique field if exists and not empty, otherwise primary key
	 * If a searchField is provided and it's "id", shows the id value instead of unique field
	 *
	 * @param string $relatedModelClass Related model class name
	 * @param mixed $value Filter value (usually primary key)
	 * @param string|null $searchField The field name used for the search (e.g., "id", "numero_pratica")
	 * @return array ['value' => string, 'field' => string|null] Display value and field name used (null if primary key)
	 */
	public static function getHasManyFilterDisplayValueAndField(
		string $relatedModelClass,
		$value,
		?string $searchField = null
	): array {
		if (!class_exists($relatedModelClass)) {
			return ["value" => (string) $value, "field" => null];
		}

		try {
			$relatedModel = new $relatedModelClass();
			$relatedTable = $relatedModel->getTable();

			// Find the item
			$item = $relatedModelClass::find($value);

			if (!$item) {
				return ["value" => (string) $value, "field" => null];
			}

			// Check if model has activeFilterField property/method (highest priority)
			$activeFilterField = self::getActiveFilterFieldFromModel($relatedModelClass);
			if ($activeFilterField && \Illuminate\Support\Facades\Schema::hasColumn($relatedTable, $activeFilterField)) {
				$fieldValue = method_exists($item, "getAttribute")
					? $item->getAttribute($activeFilterField)
					: (isset($item->$activeFilterField)
						? $item->$activeFilterField
						: null);

				if ($fieldValue !== null && $fieldValue !== "") {
					$displayValue = (string) $fieldValue;

					// For Role and BackpackRole, still include name if available
					$modelName = class_basename($relatedModelClass);
					if (
						in_array($modelName, ["Role", "BackpackRole"]) &&
						\Illuminate\Support\Facades\Schema::hasColumn($relatedTable, "name")
					) {
						$nameValue = method_exists($item, "getAttribute")
							? $item->getAttribute("name")
							: (isset($item->name)
								? $item->name
								: null);

						if ($nameValue !== null && $nameValue !== "") {
							$displayValue = $nameValue . " (" . $displayValue . ")";
						}
					}

					return ["value" => $displayValue, "field" => $activeFilterField];
				}
				// If activeFilterField is empty, fallback to primary key
			}

			// If activeFilterField is not set or empty, use primary key directly
			$primaryKey = method_exists($item, "getKey") ? (string) $item->getKey() : (string) $value;
			$displayValue = $primaryKey;

			// For Role and BackpackRole, still include name if available
			$modelName = class_basename($relatedModelClass);
			if (
				in_array($modelName, ["Role", "BackpackRole"]) &&
				\Illuminate\Support\Facades\Schema::hasColumn($relatedTable, "name")
			) {
				$nameValue = method_exists($item, "getAttribute")
					? $item->getAttribute("name")
					: (isset($item->name)
						? $item->name
						: null);

				if ($nameValue !== null && $nameValue !== "") {
					$displayValue = $nameValue . " (" . $displayValue . ")";
				}
			}

			return ["value" => $displayValue, "field" => null];
		} catch (\Exception $e) {
			// If anything fails, return the value as string
			return ["value" => (string) $value, "field" => null];
		}
	}

	/**
	 * Get display value for hasMany relation filter: unique field if exists and not empty, otherwise primary key
	 * If a searchField is provided and it's "id", shows the id value instead of unique field
	 *
	 * @param string $relatedModelClass Related model class name
	 * @param mixed $value Filter value (usually primary key)
	 * @param string|null $searchField The field name used for the search (e.g., "id", "numero_pratica")
	 * @return string Display value
	 */
	public static function getHasManyFilterDisplayValue(
		string $relatedModelClass,
		$value,
		?string $searchField = null
	): string {
		$result = self::getHasManyFilterDisplayValueAndField($relatedModelClass, $value, $searchField);
		return $result["value"];
	}

	/**
	 * Convert numbers to Unicode bold characters for better visibility
	 */
	private static function convertToBoldNumbers($number): string
	{
		$boldDigits = [
			"0" => "𝟎",
			"1" => "𝟏",
			"2" => "𝟐",
			"3" => "𝟑",
			"4" => "𝟒",
			"5" => "𝟓",
			"6" => "𝟔",
			"7" => "𝟕",
			"8" => "𝟖",
			"9" => "𝟗",
		];

		$numberStr = (string) $number;
		$result = "";

		for ($i = 0; $i < strlen($numberStr); $i++) {
			$digit = $numberStr[$i];
			$result .= $boldDigits[$digit] ?? $digit;
		}

		return $result;
	}

	/**
	 * Get relation info for filter description text
	 * Returns array with primary key, unique field, related table singular name, current table singular name
	 */
	public static function getRelationFilterInfo($column, $crud, $isHasMany = false, $hasManyRelation = null): array
	{
		$currentModel = $crud->model;
		$currentTableName = $currentModel->getTable();
		$currentTableSingular = \Illuminate\Support\Str::singular($currentTableName);
		$currentTableSingularUpper = strtoupper(str_replace("_", " ", $currentTableSingular));

		if ($isHasMany && $hasManyRelation) {
			// HasMany relation
			$relatedModelClass = $hasManyRelation["model"] ?? null;
			if (!$relatedModelClass && isset($hasManyRelation["name"])) {
				$relatedModelName = \Illuminate\Support\Str::studly($hasManyRelation["name"]);
				$relatedModelClass = "App\Models\\" . $relatedModelName;
			}

			if ($relatedModelClass && class_exists($relatedModelClass)) {
				$relatedModel = new $relatedModelClass();
				$relatedTableName = $relatedModel->getTable();
				$relatedTableSingular = \Illuminate\Support\Str::singular($relatedTableName);
				$relatedTableSingularUpper = strtoupper(str_replace("_", " ", $relatedTableSingular));

				$primaryKey = $relatedModel->getKeyName();
				$primaryKeyUpper = strtoupper(str_replace("_", " ", $primaryKey));

				// Get unique field (first searchable key that is not primary)
				$uniqueField = null;
				if (isset($hasManyRelation["searchable_keys"])) {
					foreach ($hasManyRelation["searchable_keys"] as $keyInfo) {
						if (isset($keyInfo["field"]) && $keyInfo["field"] !== $primaryKey && !$keyInfo["is_primary"]) {
							$uniqueField = $keyInfo["field"];
							break;
						}
					}
				}

				$uniqueFieldUpper = $uniqueField ? strtoupper(str_replace("_", " ", $uniqueField)) : null;

				return [
					"primary_key" => $primaryKeyUpper,
					"unique_field" => $uniqueFieldUpper,
					"related_table_singular" => $relatedTableSingularUpper,
					"current_table_singular" => $currentTableSingularUpper,
				];
			}
		} else {
			// BelongsTo relation
			$relationName = self::getRelationNameFromForeignKey($column["name"], $currentTableName);
			$relatedModel = null;

			if (method_exists($currentModel, $relationName)) {
				try {
					$relationQuery = $currentModel->$relationName();
					$relatedModel = $relationQuery->getRelated();
				} catch (\Exception $e) {
					$relatedModelName = \Illuminate\Support\Str::studly($relationName);
					$relatedModelClass = "App\Models\\" . $relatedModelName;
					if (class_exists($relatedModelClass)) {
						$relatedModel = new $relatedModelClass();
					}
				}
			} else {
				$relatedModelName = \Illuminate\Support\Str::studly($relationName);
				$relatedModelClass = "App\Models\\" . $relatedModelName;
				if (class_exists($relatedModelClass)) {
					$relatedModel = new $relatedModelClass();
				}
			}

			if ($relatedModel) {
				$relatedTableName = $relatedModel->getTable();
				$relatedTableSingular = \Illuminate\Support\Str::singular($relatedTableName);
				$relatedTableSingularUpper = strtoupper(str_replace("_", " ", $relatedTableSingular));

				$primaryKey = $relatedModel->getKeyName();
				$primaryKeyUpper = strtoupper(str_replace("_", " ", $primaryKey));

				return [
					"primary_key" => $primaryKeyUpper,
					"unique_field" => null,
					"related_table_singular" => $relatedTableSingularUpper,
					"current_table_singular" => $currentTableSingularUpper,
				];
			}
		}

		// Fallback
		return [
			"primary_key" => "ID",
			"unique_field" => null,
			"related_table_singular" => "TABELLA",
			"current_table_singular" => $currentTableSingularUpper,
		];
	}

	/**
	 * Get all HasMany relations from a model
	 * Returns array with relation name, related model, and foreign key info
	 */
	private static function getHasManyRelations($model): array
	{
		$relations = [];
		$modelInstance = is_string($model) ? new $model() : $model;
		$reflectionClass = new \ReflectionClass($modelInstance);

		foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
			// Skip magic methods, getters, setters, etc.
			if (
				$method->class !== get_class($modelInstance) ||
				$method->getNumberOfParameters() > 0 ||
				strpos($method->getName(), "__") === 0 ||
				strpos($method->getName(), "get") === 0 ||
				strpos($method->getName(), "set") === 0 ||
				strpos($method->getName(), "scope") === 0
			) {
				continue;
			}

			try {
				$return = $method->invoke($modelInstance);

				// Check if it's a HasMany relation
				if ($return instanceof \Illuminate\Database\Eloquent\Relations\HasMany) {
					$relatedModel = get_class($return->getRelated());
					$foreignKey = $return->getForeignKeyName();
					$localKey = $return->getLocalKeyName();

					// Get searchable keys for the related model (id, numero_pratica, etc.)
					$searchableKeys = \App\Http\Controllers\Admin\Ajax\RelationSearchController::getSearchableKeys(
						$relatedModel
					);

					$relations[] = [
						"name" => $method->getName(),
						"type" => "HasMany",
						"model" => $relatedModel,
						"foreign_key" => $foreignKey,
						"local_key" => $localKey,
						"searchable_keys" => $searchableKeys,
					];
				}
			} catch (\Throwable $e) {
				// Skip methods that throw errors
				continue;
			}
		}

		return $relations;
	}

	/**
	 * Group HasMany relations by name (instead of by searchable key)
	 * This consolidates relations with multiple searchable keys into single entries
	 * Example: delibere_id and delibere_numero_pratica become one "delibere" entry with multiple keys
	 */
	private static function groupHasManyRelationsByName(array $hasManyRelations): array
	{
		$grouped = [];

		foreach ($hasManyRelations as $relation) {
			$name = $relation["name"];

			// If this relation doesn't exist yet, add it
			if (!isset($grouped[$name])) {
				$grouped[$name] = $relation;
			}
			// If it exists and has more searchable keys, keep all keys
			// (This shouldn't happen with current implementation, but good to be safe)
		}

		return array_values($grouped);
	}

	/**
	 * Get filter count for HasMany relations
	 * Now counts filters for each searchable key (id, numero_pratica, etc.)
	 * Also counts _empty and _not_empty filters at relation level
	 */
	private static function getHasManyRelationsFilterCount(array $hasManyRelations): int
	{
		$count = 0;
		$allRequestParams = request()->all();

		foreach ($hasManyRelations as $relation) {
			$relationName = $relation["name"];
			$hasRelationFilter = false;

			// Check for relation-level _empty and _not_empty filters
			$emptyKey = $relationName . "_empty";
			$notEmptyKey = $relationName . "_not_empty";

			// Check if either filter exists in request and has a truthy value
			if (
				isset($allRequestParams[$emptyKey]) &&
				$allRequestParams[$emptyKey] !== null &&
				$allRequestParams[$emptyKey] !== "" &&
				($allRequestParams[$emptyKey] === "1" ||
					$allRequestParams[$emptyKey] === 1 ||
					$allRequestParams[$emptyKey] === "true")
			) {
				$hasRelationFilter = true;
			} elseif (
				isset($allRequestParams[$notEmptyKey]) &&
				$allRequestParams[$notEmptyKey] !== null &&
				$allRequestParams[$notEmptyKey] !== "" &&
				($allRequestParams[$notEmptyKey] === "1" ||
					$allRequestParams[$notEmptyKey] === 1 ||
					$allRequestParams[$notEmptyKey] === "true")
			) {
				$hasRelationFilter = true;
			}

			if ($hasRelationFilter) {
				$count++;
			}

			// Count filters for each searchable key
			if (isset($relation["searchable_keys"])) {
				foreach ($relation["searchable_keys"] as $keyInfo) {
					$filterKey = $relationName . "_" . $keyInfo["field"];
					if (
						isset($allRequestParams[$filterKey]) &&
						$allRequestParams[$filterKey] !== null &&
						$allRequestParams[$filterKey] !== ""
					) {
						$count++;
					}
				}
			}
		}

		return $count;
	}

	/**
	 * Get options for HasMany relation filter
	 * Returns simple has/doesn't have options
	 */
	public static function getHasManyRelationOptions(array $relationInfo, $crud): array
	{
		$relationName = $relationInfo["name"];
		$displayName = ucfirst(str_replace("_", " ", $relationName));

		return [
			"has" => "Ha {$displayName}",
			"doesnt_have" => "Non ha {$displayName}",
		];
	}
}
