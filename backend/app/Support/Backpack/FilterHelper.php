<?php

namespace App\Support\Backpack;

use App\Support\Backpack\Handler\FieldConfigHandler;
use App\Http\Controllers\Admin\Support\RelationSearchOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

		// Get table name and primary key for FK detection
		$model = $crud->model;
		$modelInstance = is_string($model) ? new $model() : $model;
		$tableName = $modelInstance->getTable();
		$primaryKey = $modelInstance->getKeyName();

		// Group columns by type, separating normal and advanced
		$textColumns = $columns->filter(function ($column) use ($tableName, $primaryKey, $advancedFields) {
			$type = $column["type"] ?? "";
			// Exclude foreign keys (real FK constraints only, not the primary key)
			$isForeignKey = self::isForeignKeyColumn($column["name"], $tableName, $primaryKey);

			return !$isForeignKey &&
				!str_ends_with($column["name"], "_path") &&
				$type != "switch" &&
				$type != "date" &&
				$type != "datetime" &&
				$column["name"] !== "page" &&
				!FieldConfigHandler::isSelectField($column["name"], $tableName) &&
				!self::isBooleanDatabaseColumn($tableName, $column["name"]) &&
				!in_array($column["name"], $advancedFields);
		});

		// Advanced text columns (require checkbox)
		$advancedTextColumns = $columns->filter(function ($column) use ($tableName, $primaryKey, $advancedFields) {
			$type = $column["type"] ?? "";
			// Exclude foreign keys (real FK constraints only, not the primary key)
			$isForeignKey = self::isForeignKeyColumn($column["name"], $tableName, $primaryKey);

			return !$isForeignKey &&
				!str_ends_with($column["name"], "_path") &&
				$type != "switch" &&
				$type != "date" &&
				$type != "datetime" &&
				$column["name"] !== "page" &&
				!FieldConfigHandler::isSelectField($column["name"], $tableName) &&
				!self::isBooleanDatabaseColumn($tableName, $column["name"]) &&
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
		$booleanColumns = $columns->filter(function ($column) use ($tableName) {
			if (str_ends_with($column["name"], "_path")) {
				return false;
			}

			return ($column["type"] ?? "") === "switch" || self::isBooleanDatabaseColumn($tableName, $column["name"]);
		});

		// Select filters (status, custom enums, etc.)
		$selectColumns = $columns->filter(function ($column) use ($tableName) {
			return FieldConfigHandler::isSelectField($column["name"], $tableName);
		});

		$relationColumns = $columns->filter(function ($column) use ($advancedFields, $tableName, $primaryKey) {
			return (self::isForeignKeyColumn($column["name"], $tableName, $primaryKey) || $column["name"] === "page") &&
				!in_array($column["name"], $advancedFields);
		});

		// Advanced relation columns (require checkbox)
		$advancedRelationColumns = $columns->filter(function ($column) use ($advancedFields, $tableName, $primaryKey) {
			return (self::isForeignKeyColumn($column["name"], $tableName, $primaryKey) || $column["name"] === "page") &&
				in_array($column["name"], $advancedFields);
		});

		// Get HasMany relations from model (inverse relations)
		$hasManyRelations = self::getHasManyRelations($crud->model) ?? [];
		// Group by relation name (consolidate multiple search fields into one filter)
		$hasManyRelationsGrouped = self::groupHasManyRelationsByName($hasManyRelations);
		$hasManyRelationsGrouped = self::attachHasManyRelationFilterableFields($hasManyRelationsGrouped);
		$hasManyRelationsCount = self::getHasManyRelationsFilterCount($hasManyRelationsGrouped);
		$singularRelationsByFk = self::singularRelationsByForeignKey($crud->model);
		$relationsWithFieldFilters = self::relationsWithFieldFilterMetadata($crud->model);

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
			"primaryKey" => $primaryKey,
			"textFilterCount" => $textFilterCount,
			"dateFilterCount" => $dateFilterCount,
			"booleanFilterCount" => $booleanFilterCount,
			"selectFilterCount" => $selectFilterCount,
			"pathFilterCount" => $pathFilterCount,
			"relationFilterCount" => $relationFilterCount,
			"advancedFilterCount" => $advancedFilterCount,
			"hasManyRelations" => $hasManyRelationsGrouped,
			"hasManyRelationsCount" => $hasManyRelationsCount,
			"singularRelationsByFk" => $singularRelationsByFk,
			"relationsWithFieldFilters" => $relationsWithFieldFilters,
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
		if (SchemaCache::hasColumn($tableName, $columnName)) {
			$columnType = strtolower(SchemaCache::getColumnType($tableName, $columnName));

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
	 * Whether the filter UI should show a % suffix and 1–100 bounds (tan, taeg, percentuale, etc.).
	 */
	public static function isPercentageColumn(string $columnName): bool
	{
		return str_contains($columnName, "percentuale") ||
			str_contains($columnName, "tan") ||
			str_contains($columnName, "taeg");
	}

	/**
	 * Read DECIMAL/NUMERIC scale from information_schema (e.g. decimal(10,3) → 3).
	 */
	public static function getColumnDecimalScale(string $tableName, string $columnName): ?int
	{
		if (!SchemaCache::hasColumn($tableName, $columnName)) {
			return null;
		}

		try {
			$columnType = strtolower(SchemaCache::getColumnType($tableName, $columnName, true));

			if (preg_match("/decimal\(\d+,(\d+)\)/", $columnType, $matches) === 1) {
				return (int) $matches[1];
			}

			if (preg_match("/\b(int|bigint|smallint|tinyint|mediumint)\b/", $columnType) === 1) {
				return 0;
			}

			if (
				str_contains($columnType, "float") ||
				str_contains($columnType, "double") ||
				str_contains($columnType, "real")
			) {
				return 2;
			}
		} catch (\Throwable $e) {
			return null;
		}

		return null;
	}

	/**
	 * Build an HTML number input step from decimal scale (0 → 1, 2 → 0.01, 3 → 0.001).
	 */
	public static function stepFromDecimalScale(int $scale): string
	{
		if ($scale <= 0) {
			return "1";
		}

		return rtrim(rtrim(number_format(10 ** -$scale, $scale, ".", ""), "0"), ".") ?: "1";
	}

	/**
	 * Detect DB boolean columns (boolean or tinyint(1)), including contratto_copia_conforme-style fields.
	 */
	public static function isBooleanDatabaseColumn(string $tableName, string $columnName): bool
	{
		if (!SchemaCache::hasColumn($tableName, $columnName)) {
			return false;
		}

		$dataType = strtolower(SchemaCache::getColumnType($tableName, $columnName));

		if ($dataType === "boolean") {
			return true;
		}

		if ($dataType !== "tinyint") {
			return false;
		}

		try {
			$columnType = strtolower(SchemaCache::getColumnType($tableName, $columnName, true));

			return str_starts_with($columnType, "tinyint(1)");
		} catch (\Throwable $e) {
			return false;
		}
	}

	/**
	 * Normalize comma-decimal filter values to dot notation for query params.
	 */
	public static function normalizeDecimalFilterValue(string $value): string
	{
		$value = trim($value);
		if ($value === "") {
			return "";
		}

		return str_replace(",", ".", $value);
	}

	/**
	 * Get step attribute for number inputs
	 */
	public static function getNumberStep($column, $tableName): string
	{
		$columnName = is_array($column) ? $column["name"] ?? "" : $column;

		if ($columnName === "") {
			return "1";
		}

		if (!SchemaCache::hasColumn($tableName, $columnName)) {
			return "1";
		}

		$scale = self::getColumnDecimalScale($tableName, $columnName);

		if ($scale !== null) {
			return self::stepFromDecimalScale($scale);
		}

		$columnType = strtolower(SchemaCache::getColumnType($tableName, $columnName));

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

		return "1";
	}

	/**
	 * Resolve display label for a foreign-key value inside a related table filter field.
	 */
	public static function getForeignKeyFieldDisplayLabel(string $table, string $column, mixed $value): string
	{
		if ($value === null || $value === "") {
			return "";
		}

		$foreignKeys = self::getForeignKeysForTable($table);
		if (!isset($foreignKeys[$column])) {
			return (string) $value;
		}

		$referencedTable = $foreignKeys[$column]["table"];
		$referencedColumn = $foreignKeys[$column]["column"];
		$modelClass = self::findModelForTable($referencedTable);

		if (!$modelClass) {
			return (string) $value;
		}

		$record = $modelClass::query()->where($referencedColumn, $value)->first();
		if (!$record) {
			return (string) $value;
		}

		if (method_exists($record, "getRelationDisplayAttribute")) {
			$label = trim((string) $record->getRelationDisplayAttribute());
			if ($label !== "") {
				return $label;
			}
		}

		if (method_exists($record, "getDisplayAttribute")) {
			$label = trim((string) $record->getDisplayAttribute());
			if ($label !== "") {
				return $label;
			}
		}

		return (string) $record->getKey();
	}

	/**
	 * Format a numeric filter value for HTML number inputs (dot decimal separator).
	 */
	public static function formatNumberFilterValue(mixed $value): string
	{
		if ($value === null || $value === "") {
			return "";
		}

		return self::normalizeDecimalFilterValue((string) $value);
	}

	/**
	 * Get active filters for display
	 */
	public static function getActiveFilters(Collection $columns): Collection
	{
		$filters = collect(request()->except(["page", "persistent-table"]))->filter(function ($value, $key) {
			return $value !== null &&
				$value !== "" &&
				$key !== "_token" &&
				$key !== "last_filter_section" &&
				!str_ends_with($key, "_filter_mode");
		});

		// Group date range filters to avoid duplicates
		$processedFilters = collect();
		$processedDateRanges = collect();

		// Track fields with conflicting not_empty/empty filters
		$conflictingFields = collect();

		foreach ($filters as $key => $value) {
			// Relation count filters: prefer *_count_op as the canonical key for the badge,
			// but only when there is a valid numeric *_count value.
			if (str_ends_with($key, "_count_op")) {
				$relationName = str_replace("_count_op", "", $key);
				$count = request()->get($relationName . "_count");
				if ($count !== null && $count !== "" && is_numeric($count)) {
					$processedFilters->put($key, $value);
				}

				continue;
			}

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
			} elseif (str_ends_with($key, "_count")) {
				// Relation count numeric value: only add when *_count_op is NOT present
				$relationName = str_replace("_count", "", $key);
				if (empty(request()->get($relationName . "_count_op"))) {
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
	 * Resolve related-field context for filters not present on the current model table.
	 *
	 * @return array{relation:string,label:string}|null
	 */
	public static function resolveRelatedFilterContext($crud, string $key): ?array
	{
		$model = $crud->model;
		$modelInstance = is_string($model) ? new $model() : $model;

		foreach ((new \ReflectionClass($modelInstance))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
			if (
				$method->class !== get_class($modelInstance) ||
				!ModelRelations::isRelationMethod($method) ||
				$method->getNumberOfParameters() > 0 ||
				str_starts_with($method->getName(), "__") ||
				str_starts_with($method->getName(), "get") ||
				str_starts_with($method->getName(), "set") ||
				str_starts_with($method->getName(), "scope")
			) {
				continue;
			}

			try {
				$relation = $method->invoke($modelInstance);
			} catch (\Throwable $e) {
				continue;
			}

			if (!$relation instanceof Relation) {
				continue;
			}

			if (!$relation instanceof BelongsTo && !$relation instanceof HasOne && !$relation instanceof HasMany) {
				continue;
			}

			$relatedTable = $relation->getRelated()->getTable();
			if (!SchemaCache::hasColumn($relatedTable, $key)) {
				continue;
			}

			$relationLabel = self::cleanFieldName($method->getName());
			$fieldLabel = self::cleanFieldName($key);

			return [
				"relation" => $method->getName(),
				"label" => "{$relationLabel} -> {$fieldLabel}",
			];
		}

		return null;
	}

	/**
	 * Cache for foreign keys per table
	 */
	private static $foreignKeysCache = [];

	/**
	 * Get all foreign keys for a table by inspecting database schema
	 *
	 * @param  string  $tableName  Table name
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
				'
				SELECT
					COLUMN_NAME,
					REFERENCED_TABLE_NAME,
					REFERENCED_COLUMN_NAME
				FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
				WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = ?
				AND REFERENCED_TABLE_NAME IS NOT NULL
			',
<<<<<<< HEAD
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
	 * This method checks ONLY real FK constraints in the database, not naming patterns.
	 *
	 * @param  string  $columnName  Column name
	 * @param  string|null  $tableName  Table name (required for accurate detection)
	 * @param  string|null  $primaryKey  Primary key name to exclude (optional)
	 */
	public static function isForeignKeyColumn(
		string $columnName,
		?string $tableName = null,
		?string $primaryKey = null
	): bool {
		// If this is the primary key of the table, it's NOT a foreign key
		if ($primaryKey !== null && $columnName === $primaryKey) {
			return false;
		}

		// If table name provided, check schema for real FK constraints
		if ($tableName) {
			$foreignKeys = self::getForeignKeysForTable($tableName);

			return isset($foreignKeys[$columnName]);
		}

		// Without table name, we cannot determine if it's a real FK
		return false;
	}

	/**
	 * Find Model class for a given table name by scanning App\Models
	 *
	 * @param  string  $tableName  Table name
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
	 * @param  string  $foreignKeyName  Column name
	 * @param  string|null  $tableName  Table name (optional, for automatic detection)
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

					return Str::camel($modelName);
				}

				return Str::singular($referencedTable);
			}
		}

		// Fallback: derive from column name
		if (str_ends_with($foreignKeyName, "_id")) {
			return str_replace("_id", "", $foreignKeyName);
		}

		return $foreignKeyName;
	}

	/**
	 * Clean field name for display (remove underscores, _id suffix for FK fields, etc.)
	 *
	 * @param  string  $fieldName  Field name to clean
	 * @param  bool  $isPrimaryKey  If true, keeps the full name (e.g., "User Id" instead of "User")
	 */
	public static function cleanFieldName(string $fieldName, bool $isPrimaryKey = false): string
	{
		// Remove custom foreign key suffixes
		$fieldName = str_replace(["_numero_pratica"], "", $fieldName);

		// Remove _id suffix only for FK fields, not for primary keys
		if (!$isPrimaryKey) {
			$fieldName = str_replace("_id", "", $fieldName);
		}

		// Replace underscores with spaces
		$fieldName = str_replace("_", " ", $fieldName);

		// Split camelCase relation names (e.g. backpackRole -> backpack Role)
		$fieldName = preg_replace("/(?<!^)[A-Z]/", ' $0', $fieldName) ?? $fieldName;

		// Capitalize first letter of each word
		$fieldName = ucwords($fieldName);

		return $fieldName;
	}

	/**
	 * Placeholder for BelongsTo AJAX search modal inputs.
	 */
	public static function buildBelongsToSearchPlaceholder(string $relatedModelClass, ?string $relationLabel = null): string
	{
		$searchTerms = [
			"User" => "user ID, ragione sociale o email",
			"Banca" => "ABI o nome banca",
			"Filiale" => "filiale ID o descrizione",
			"Role" => "nome ruolo",
			"BackpackRole" => "nome ruolo backend",
			"Tiraggio" => "numero pratica",
			"Delibera" => "delibera ID",
			"Prodotto" => "codice prodotto o nome",
			"Page" => "titolo o slug",
			"Translate" => "codice o testo",
		];

		$modelName = class_basename($relatedModelClass);
		if (isset($searchTerms[$modelName])) {
			return "Cerca per " . $searchTerms[$modelName] . "...";
		}

		if ($relationLabel !== null && trim($relationLabel) !== "") {
			return "Cerca per " . strtolower($relationLabel) . "...";
		}

		return "Cerca...";
	}

	/**
	 * Placeholder for relation filter autocomplete inputs.
	 */
	public static function buildRelationFilterPlaceholder(object $relatedModel, string $currentModelLabel): string
	{
		$modelName = class_basename($relatedModel);
		$searchTerms = [
			"User" => "user ID o ragione sociale",
			"Banca" => "ABI o nome banca",
			"Filiale" => "filiale ID o descrizione",
			"Role" => "nome ruolo",
			"BackpackRole" => "nome ruolo backend",
			"Tiraggio" => "numero pratica",
			"Delibera" => "delibera ID",
		];

		$hint = $searchTerms[$modelName] ?? strtolower(str_replace("_", " ", $relatedModel->getKeyName()));
		$relatedLabel = strtolower(self::cleanFieldName($modelName));

		return "Cerca {$hint} di {$relatedLabel} e trova " . strtolower($currentModelLabel);
	}

	/**
	 * Human-readable label for a related model row in filters and autocompletes.
	 */
	public static function resolveRelationItemLabel(
		object $item,
		string $relatedModelClass,
		mixed $fallbackKey = null
	): string {
		if (method_exists($item, "getRelationDisplayAttribute")) {
			$label = trim((string) $item->getRelationDisplayAttribute());
			if ($label !== "") {
				return $label;
			}
		}

		if (method_exists($item, "getDisplayAttribute")) {
			$label = trim((string) $item->getDisplayAttribute());
			if ($label !== "") {
				return $label;
			}
		}

		$modelName = class_basename($relatedModelClass);
		if (in_array($modelName, ["Role", "BackpackRole"], true) && method_exists($item, "getAttribute")) {
			$nameValue = trim((string) ($item->getAttribute("name") ?? ""));
			if ($nameValue !== "") {
				return $nameValue;
			}
		}

		if (method_exists($item, "getKey")) {
			$key = trim((string) $item->getKey());
			if ($key !== "") {
				return $key;
			}
		}

		if ($fallbackKey !== null && trim((string) $fallbackKey) !== "") {
			return (string) $fallbackKey;
		}

		return "—";
	}

	/**
	 * Get display value for active filter
	 */
	public static function getFilterDisplayValue($key, $value, $columns, $crud): string
	{
		// Handle relation count filter (relationName_count_op + relationName_count)
		if (str_ends_with($key, "_count_op")) {
			$relationName = str_replace("_count_op", "", $key);
			$count = request()->get($relationName . "_count");
			if ($count !== null && $count !== "" && is_numeric($count)) {
				$labels = ["eq" => "Esattamente", "gt" => "Più di", "lt" => "Meno di"];
				$opLabel = $labels[$value] ?? $value;

				// The badge label already shows the relation name; here return only the condition
				return $opLabel . " " . $count;
			}
		}
		if (str_ends_with($key, "_count")) {
			$relationName = str_replace("_count", "", $key);
			if (request()->get($relationName . "_count_op") !== null && request()->get($relationName . "_count_op") !== "") {
				return ""; // Already shown with _count_op
			}
		}

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

		// Get table name and primary key
		$model = $crud->model;
		$modelInstance = is_string($model) ? new $model() : $model;
		$tableName = $modelInstance->getTable();
		$primaryKey = $modelInstance->getKeyName();

		// Handle path fields
		if (str_ends_with($columnName, "_path")) {
			return trans("backpack::filters.with_file");
		}

		// Handle select fields (status, enums, etc.)
		elseif (FieldConfigHandler::isSelectField($columnName, $tableName)) {
			$config = FieldConfigHandler::getSelectFieldConfig($columnName, $tableName);

			return $config["options"][$value] ?? $value;
		}

		// Handle relation fields (real FK constraints only, not primary key)
		elseif (self::isForeignKeyColumn($columnName, $tableName, $primaryKey)) {
			if ($value === "__null__") {
				return "Non collegati";
			}

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
	public static function getSelectOptions($column, $tableName): ?array
	{
		$config = FieldConfigHandler::getSelectFieldConfig($column["name"], $tableName);

		return $config ? $config["options"] : [];
	}

	/**
	 * Get options for relation filter with efficient counting and dynamic filtering
	 */
	/**
	 * Options for a relation filter rendered as a plain select, or null when
	 * the related table holds more than $max rows and the filter falls back to
	 * the autocomplete. Building the options loads every related record (every
	 * user, for a user_id column) and counts the current table per key: done
	 * on every list page only to count them, it ran out of memory in
	 * production on storici, previsionali, delibere and tiraggi.
	 *
	 * @return array<string, string>|null
	 */
	public static function getRelationOptionsForSelect($column, $crud, int $max): ?array
	{
		$relatedModel = self::resolveRelatedModel($column, $crud);

		if (!class_exists($relatedModel) || $relatedModel::query()->count() > $max) {
			return null;
		}

		return self::getRelationOptions($column, $crud);
	}

	/**
	 * The model a foreign key column points to: from the relation method when
	 * the model defines it, otherwise guessed from the column name.
	 */
	private static function resolveRelatedModel($column, $crud): string
	{
		$relationName = self::getRelationNameFromForeignKey($column["name"], $crud->model->getTable());

		if (method_exists($crud->model, $relationName)) {
			try {
				return get_class($crud->model->$relationName()->getRelated());
			} catch (\Exception $e) {
				// Fall back to the name below.
			}
		}

		return "App\\Models\\" . Str::studly($relationName);
	}

	public static function getRelationOptions($column, $crud): array
	{
		// Get table name and primary key for automatic FK detection
		$currentTableName = $crud->model->getTable();
		$currentPrimaryKey = $crud->model->getKeyName();
		$relatedModel = self::resolveRelatedModel($column, $crud);

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
				if (!SchemaCache::hasColumn($currentTableName, $filterKey)) {
					continue;
				}

				// Apply the same filter logic as FilterHandler
				if (str_ends_with($filterKey, "_path")) {
					// Path field filter
					if ($filterValue === "1") {
						$countsQuery->whereNotNull($filterKey)->where($filterKey, "!=", "");
					}
				} elseif (self::isForeignKeyColumn($filterKey, $currentTableName, $currentPrimaryKey)) {
					// Relation filter (real FK constraints only)
					$countsQuery->where($filterKey, "=", $filterValue);
				} else {
					// Get column type
					$columnType = SchemaCache::getColumnType($currentTableName, $filterKey);

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
					// Use the referenced column value as key (e.g., numero_pratica)
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

					// Build display: prefer model display helpers, then PK / unique field
					$displayValue = self::resolveRelationItemLabel($item, $relatedModel, $itemKey);

					if ($displayValue === "—") {
						$relatedModelInstance = new $relatedModel();
						$relatedTable = $relatedModelInstance->getTable();
						$pkValue = method_exists($item, "getKey") ? (string) $item->getKey() : (string) $itemKey;

						$uniqueFields = DB::select(
							"
=======
                [$tableName]
            );

            // Build array: column_name => ['table' => referenced_table, 'column' => referenced_column]
            $result = [];
            foreach ($foreignKeys as $fk) {
                $result[$fk->COLUMN_NAME] = [
                    'table' => $fk->REFERENCED_TABLE_NAME,
                    'column' => $fk->REFERENCED_COLUMN_NAME,
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
     * This method checks ONLY real FK constraints in the database, not naming patterns.
     *
     * @param  string  $columnName  Column name
     * @param  string|null  $tableName  Table name (required for accurate detection)
     * @param  string|null  $primaryKey  Primary key name to exclude (optional)
     */
    public static function isForeignKeyColumn(string $columnName, ?string $tableName = null, ?string $primaryKey = null): bool
    {
        // If this is the primary key of the table, it's NOT a foreign key
        if ($primaryKey !== null && $columnName === $primaryKey) {
            return false;
        }

        // If table name provided, check schema for real FK constraints
        if ($tableName) {
            $foreignKeys = self::getForeignKeysForTable($tableName);

            return isset($foreignKeys[$columnName]);
        }

        // Without table name, we cannot determine if it's a real FK
        return false;
    }

    /**
     * Find Model class for a given table name by scanning App\Models
     *
     * @param  string  $tableName  Table name
     * @return string|null Model class name or null if not found
     */
    private static function findModelForTable(string $tableName): ?string
    {
        $namespace = 'App\\Models\\';
        $modelsPath = app_path('Models');
        if (! is_dir($modelsPath)) {
            return null;
        }

        $files = scandir($modelsPath);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }

            $modelClass = $namespace.pathinfo($file, PATHINFO_FILENAME);

            if (! class_exists($modelClass)) {
                continue;
            }

            try {
                $instance = new $modelClass;
                if (method_exists($instance, 'getTable') && $instance->getTable() === $tableName) {
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
     * @param  string  $foreignKeyName  Column name
     * @param  string|null  $tableName  Table name (optional, for automatic detection)
     * @return string Relation name (camelCase from Model class)
     */
    private static function getRelationNameFromForeignKey(string $foreignKeyName, ?string $tableName = null): string
    {
        // If table name provided, use automatic detection
        if ($tableName) {
            $foreignKeys = self::getForeignKeysForTable($tableName);

            if (isset($foreignKeys[$foreignKeyName])) {
                $referencedTable = $foreignKeys[$foreignKeyName]['table'];
                $modelClass = self::findModelForTable($referencedTable);

                if ($modelClass) {
                    $modelName = class_basename($modelClass);

                    return Str::camel($modelName);
                }

                return Str::singular($referencedTable);
            }
        }

        // Fallback: derive from column name
        if (str_ends_with($foreignKeyName, '_id')) {
            return str_replace('_id', '', $foreignKeyName);
        }

        return $foreignKeyName;
    }

    /**
     * Clean field name for display (remove underscores, _id suffix for FK fields, etc.)
     *
     * @param  string  $fieldName  Field name to clean
     * @param  bool  $isPrimaryKey  If true, keeps the full name (e.g., "User Id" instead of "User")
     */
    public static function cleanFieldName(string $fieldName, bool $isPrimaryKey = false): string
    {
        // Remove _id suffix only for FK fields, not for primary keys
        if (! $isPrimaryKey) {
            $fieldName = str_replace('_id', '', $fieldName);
        }

        // Replace underscores with spaces
        $fieldName = str_replace('_', ' ', $fieldName);

        // Split camelCase relation names (e.g. backpackRole -> backpack Role)
        $fieldName = preg_replace('/(?<!^)[A-Z]/', ' $0', $fieldName) ?? $fieldName;

        // Capitalize first letter of each word
        $fieldName = ucwords($fieldName);

        return $fieldName;
    }

    /**
     * Placeholder for BelongsTo AJAX search modal inputs.
     */
    public static function buildBelongsToSearchPlaceholder(string $relatedModelClass, ?string $relationLabel = null): string
    {
        $searchTerms = [
            'User' => 'username, nome o email',
            'Role' => 'nome ruolo',
            'BackpackRole' => 'nome ruolo backend',
            'Page' => 'titolo o slug',
            'Translate' => 'codice o testo',
        ];

        $modelName = class_basename($relatedModelClass);
        if (isset($searchTerms[$modelName])) {
            return 'Cerca per '.$searchTerms[$modelName].'...';
        }

        if ($relationLabel !== null && trim($relationLabel) !== '') {
            return 'Cerca per '.strtolower($relationLabel).'...';
        }

        return 'Cerca...';
    }

    /**
     * Placeholder for relation filter autocomplete inputs.
     */
    public static function buildRelationFilterPlaceholder(object $relatedModel, string $currentModelLabel): string
    {
        $modelName = class_basename($relatedModel);
        $searchTerms = [
            'User' => 'username o nome',
            'Role' => 'nome ruolo',
            'BackpackRole' => 'nome ruolo backend',
        ];

        $hint = $searchTerms[$modelName] ?? strtolower(str_replace('_', ' ', $relatedModel->getKeyName()));
        $relatedLabel = strtolower(self::cleanFieldName($modelName));

        return "Cerca {$hint} di {$relatedLabel} e trova ".strtolower($currentModelLabel);
    }

    /**
     * Human-readable label for a related model row in filters and autocompletes.
     */
    public static function resolveRelationItemLabel(object $item, string $relatedModelClass, mixed $fallbackKey = null): string
    {
        if (method_exists($item, 'getRelationDisplayAttribute')) {
            $label = trim((string) $item->getRelationDisplayAttribute());
            if ($label !== '') {
                return $label;
            }
        }

        if (method_exists($item, 'getDisplayAttribute')) {
            $label = trim((string) $item->getDisplayAttribute());
            if ($label !== '') {
                return $label;
            }
        }

        $modelName = class_basename($relatedModelClass);
        if (
            in_array($modelName, ['Role', 'BackpackRole'], true)
            && method_exists($item, 'getAttribute')
        ) {
            $nameValue = trim((string) ($item->getAttribute('name') ?? ''));
            if ($nameValue !== '') {
                return $nameValue;
            }
        }

        if (method_exists($item, 'getKey')) {
            $key = trim((string) $item->getKey());
            if ($key !== '') {
                return $key;
            }
        }

        if ($fallbackKey !== null && trim((string) $fallbackKey) !== '') {
            return (string) $fallbackKey;
        }

        return '—';
    }

    /**
     * Get display value for active filter
     */
    public static function getFilterDisplayValue($key, $value, $columns, $crud): string
    {
        // Handle relation count filter (relationName_count_op + relationName_count)
        if (str_ends_with($key, '_count_op')) {
            $relationName = str_replace('_count_op', '', $key);
            $count = request()->get($relationName.'_count');
            if ($count !== null && $count !== '' && is_numeric($count)) {
                $labels = ['eq' => 'Esattamente', 'gt' => 'Più di', 'lt' => 'Meno di'];
                $opLabel = $labels[$value] ?? $value;

                // The badge label already shows the relation name; here return only the condition
                return $opLabel.' '.$count;
            }
        }
        if (str_ends_with($key, '_count')) {
            $relationName = str_replace('_count', '', $key);
            if (request()->get($relationName.'_count_op') !== null && request()->get($relationName.'_count_op') !== '') {
                return ''; // Already shown with _count_op
            }
        }

        // Handle not_empty and empty filters
        if (str_ends_with($key, '_not_empty')) {
            $originalKey = str_replace('_not_empty', '', $key);
            $column = $columns->firstWhere('name', $originalKey);
            if ($value === '1') {
                // Return translated "non vuoto" using Backpack translations
                return trans('backpack::crud.not_empty');
            }
        }

        if (str_ends_with($key, '_empty')) {
            $originalKey = str_replace('_empty', '', $key);
            $column = $columns->firstWhere('name', $originalKey);
            if ($value === '1') {
                // Return translated "vuoto" using Backpack translations
                return trans('backpack::crud.empty');
            }
        }

        // Handle date range filters - check if this is a date range field
        $fromValue = request()->get($key.'_from');
        $toValue = request()->get($key.'_to');
        if ($fromValue || $toValue) {
            $column = $columns->firstWhere('name', $key);
            if ($column) {
                $label = isset($column['label']) ? $column['label'] : $key;

                if ($fromValue && $toValue) {
                    return $label.': '.$fromValue.' - '.$toValue;
                } elseif ($fromValue) {
                    return $label.': da '.$fromValue;
                } elseif ($toValue) {
                    return $label.': fino a '.$toValue;
                }
            }
        }

        $columnName = $key === 'page_filter' ? 'page' : $key;
        $column = $columns->firstWhere('name', $columnName);

        if (! $column) {
            return $value;
        }

        // Get table name and primary key
        $model = $crud->model;
        $modelInstance = is_string($model) ? new $model : $model;
        $tableName = $modelInstance->getTable();
        $primaryKey = $modelInstance->getKeyName();

        // Handle path fields
        if (str_ends_with($columnName, '_path')) {
            return trans('backpack::filters.with_file');
        }

        // Handle select fields (status, enums, etc.)
        elseif (FieldConfigHandler::isSelectField($columnName, $tableName)) {
            $config = FieldConfigHandler::getSelectFieldConfig($columnName, $tableName);

            return $config['options'][$value] ?? $value;
        }

        // Handle relation fields (real FK constraints only, not primary key)
        elseif (self::isForeignKeyColumn($columnName, $tableName, $primaryKey)) {
            if ($value === '__null__') {
                return 'Non collegati';
            }

            return self::getRelationDisplayValue($columnName, $value, $crud);
        }

        // Handle boolean fields
        elseif (($column['type'] ?? '') == 'switch') {
            return $value == '1' ? trans('backpack::filters.yes') : trans('backpack::filters.no');
        }

        return $value;
    }

    /**
     * Get options for select fields (status, custom enums, etc.)
     */
    public static function getSelectOptions($column, $tableName): ?array
    {
        $config = FieldConfigHandler::getSelectFieldConfig($column['name'], $tableName);

        return $config ? $config['options'] : [];
    }

    /**
     * Get options for relation filter with efficient counting and dynamic filtering
     */
    /**
     * Options for a relation filter rendered as a plain select, or null when
     * the related table holds more than $max rows and the filter falls back to
     * the autocomplete. Building the options loads every related record (every
     * user, for a username column) and counts the current table per key: done
     * on every list page only to count them, it ran out of memory in
     * production on large tables.
     *
     * @return array<string, string>|null
     */
    public static function getRelationOptionsForSelect($column, $crud, int $max): ?array
    {
        $relatedModel = self::resolveRelatedModel($column, $crud);

        if (! class_exists($relatedModel) || $relatedModel::query()->count() > $max) {
            return null;
        }

        return self::getRelationOptions($column, $crud);
    }

    /**
     * The model a foreign key column points to: from the relation method when
     * the model defines it, otherwise guessed from the column name.
     */
    private static function resolveRelatedModel($column, $crud): string
    {
        $relationName = self::getRelationNameFromForeignKey($column['name'], $crud->model->getTable());

        if (method_exists($crud->model, $relationName)) {
            try {
                return get_class($crud->model->$relationName()->getRelated());
            } catch (\Exception $e) {
                // Fall back to the name below.
            }
        }

        return 'App\\Models\\'.Str::studly($relationName);
    }

    public static function getRelationOptions($column, $crud): array
    {
        // Get table name and primary key for automatic FK detection
        $currentTableName = $crud->model->getTable();
        $currentPrimaryKey = $crud->model->getKeyName();
        $relatedModel = self::resolveRelatedModel($column, $crud);

        $options = [];

        if (class_exists($relatedModel)) {
            // Get current model info for efficient counting
            $currentTableName = $crud->model->getTable();
            // Use the actual column name from the request, not reconstructed
            $foreignKeyColumn = $column['name'];

            // Get the referenced column from the foreign key constraint
            $foreignKeys = self::getForeignKeysForTable($currentTableName);
            $referencedColumn = $foreignKeys[$foreignKeyColumn]['column'] ?? null;

            // If no referenced column found, try to get it from the related model's primary key
            if (! $referencedColumn) {
                $relatedModelInstance = new $relatedModel;
                $referencedColumn = $relatedModelInstance->getKeyName();
            }

            // Get all related records first
            $relatedItems = call_user_func([$relatedModel, 'all']);

            // Build query for counting with active filters applied
            $countsQuery = DB::table($currentTableName)
                ->select($foreignKeyColumn, DB::raw('COUNT(*) as relation_count'))
                ->whereNotNull($foreignKeyColumn);

            // Apply active filters to the count query (excluding the current relation filter and pagination)
            $activeFilters = collect(
                request()->except([
                    'page',
                    'persistent-table',
                    '_token',
                    $column['name'], // Exclude current filter
                    'page_filter',
                ])
            );

            foreach ($activeFilters as $filterKey => $filterValue) {
                if ($filterValue === '' || $filterValue === null) {
                    continue;
                }

                // Check if column exists in the table
                if (! SchemaCache::hasColumn($currentTableName, $filterKey)) {
                    continue;
                }

                // Apply the same filter logic as FilterHandler
                if (str_ends_with($filterKey, '_path')) {
                    // Path field filter
                    if ($filterValue === '1') {
                        $countsQuery->whereNotNull($filterKey)->where($filterKey, '!=', '');
                    }
                } elseif (self::isForeignKeyColumn($filterKey, $currentTableName, $currentPrimaryKey)) {
                    // Relation filter (real FK constraints only)
                    $countsQuery->where($filterKey, '=', $filterValue);
                } else {
                    // Get column type
                    $columnType = SchemaCache::getColumnType($currentTableName, $filterKey);

                    if ($columnType === 'boolean' || $columnType === 'tinyint') {
                        // Boolean filter
                        if ($filterValue === '1' || $filterValue === '0') {
                            $countsQuery->where($filterKey, '=', (int) $filterValue);
                        }
                    } else {
                        // Text filter with LIKE
                        $countsQuery->where($filterKey, 'LIKE', '%'.$filterValue.'%');
                    }
                }
            }

            // Get counts grouped by foreign key
            $counts = $countsQuery->groupBy($foreignKeyColumn)->pluck('relation_count', $foreignKeyColumn);

            // Check if there are active filters (to decide whether to hide 0-count options)
            $hasActiveFilters = $activeFilters
                ->filter(function ($value) {
                    return $value !== null && $value !== '';
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
                    // Use the referenced column value as key (e.g., username)
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

                    // Build display: prefer model display helpers, then PK / unique field
                    $displayValue = self::resolveRelationItemLabel($item, $relatedModel, $itemKey);

                    if ($displayValue === '—') {
                        $relatedModelInstance = new $relatedModel;
                        $relatedTable = $relatedModelInstance->getTable();
                        $pkValue = method_exists($item, 'getKey') ? (string) $item->getKey() : (string) $itemKey;

                        $uniqueFields = DB::select(
                            "
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
						SELECT COLUMN_NAME
						FROM INFORMATION_SCHEMA.STATISTICS
						WHERE TABLE_SCHEMA = DATABASE()
						AND TABLE_NAME = ?
						AND NON_UNIQUE = 0
						AND INDEX_NAME != 'PRIMARY'
						ORDER BY SEQ_IN_INDEX
						LIMIT 1
					",
<<<<<<< HEAD
							[$relatedTable]
						);

						$uniqueValue = null;
						if (!empty($uniqueFields)) {
							$uniqueField = $uniqueFields[0]->COLUMN_NAME;
							if (method_exists($item, "getAttribute")) {
								$uniqueValue = $item->getAttribute($uniqueField);
							} elseif (isset($item->$uniqueField)) {
								$uniqueValue = $item->$uniqueField;
							}
						}

						if ($uniqueValue !== null && $uniqueValue !== "" && (string) $uniqueValue !== $pkValue) {
							$displayValue = $pkValue . " - " . $uniqueValue;
						} else {
							$displayValue = $pkValue;
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
	 * Get active filter field(s) from a model class.
	 * Supports string or array values from $activeFilterField / getActiveFilterField().
	 *
	 * @param  string  $modelClass  Model class name
	 * @return array<int, string>
	 */
	private static function getActiveFilterFieldFromModel(string $modelClass): array
	{
		if (!class_exists($modelClass)) {
			return [];
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

				return self::normalizeActiveFilterFields($value);
			}

			// Check for getActiveFilterField() method
			if ($reflection->hasMethod("getActiveFilterField")) {
				$method = $reflection->getMethod("getActiveFilterField");
				if ($method->isStatic() || $method->isPublic()) {
					$instance = $reflection->newInstanceWithoutConstructor();
					$value = $method->invoke($instance);

					return self::normalizeActiveFilterFields($value);
				}
			}
		} catch (\Exception $e) {
			// If anything fails, return null
			return [];
		}

		return [];
	}

	/**
	 * Normalize active filter field config into a clean list of field names.
	 *
	 * @return array<int, string>
	 */
	private static function normalizeActiveFilterFields($value): array
	{
		if (is_string($value) && trim($value) !== "") {
			return [trim($value)];
		}

		if (!is_array($value)) {
			return [];
		}

		return array_values(
			array_filter(
				array_map(function ($field) {
					return is_string($field) ? trim($field) : "";
				}, $value),
				fn($field) => $field !== ""
			)
		);
	}

	/**
	 * Resolve first non-empty value from configured active filter fields.
	 *
	 * @return array{value:string,field:string}|null
	 */
	private static function resolveActiveFilterValue($item, string $table, array $fields): ?array
	{
		$resolvedValues = [];
		$firstResolvedField = null;

		foreach ($fields as $field) {
			if (!SchemaCache::hasColumn($table, $field)) {
				continue;
			}

			$fieldValue = method_exists($item, "getAttribute")
				? $item->getAttribute($field)
				: (isset($item->$field)
					? $item->$field
					: null);

			if ($fieldValue !== null && $fieldValue !== "") {
				if ($firstResolvedField === null) {
					$firstResolvedField = $field;
				}
				$resolvedValues[] = (string) $fieldValue;
			}
		}

		if (!empty($resolvedValues) && $firstResolvedField !== null) {
			return [
				"value" => implode(" - ", $resolvedValues),
				"field" => $firstResolvedField,
			];
		}

		return null;
	}

	/**
	 * Get relation display value for active filters
	 */
	private static function getRelationDisplayValue($columnName, $value, $crud): string
	{
		if ($value === "__null__") {
			return "Non collegati";
		}

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
				$label = self::resolveRelationItemLabel($item, $relatedModel, $value);
				if ($label !== "—") {
					return $label;
				}

				// Get table name for unique field lookup
				$relatedTable = is_string($relatedModel) ? (new $relatedModel())->getTable() : $relatedModel->getTable();
				$relatedModelInstance = is_string($relatedModel) ? new $relatedModel() : $relatedModel;
				$primaryKey = method_exists($item, "getKey") ? (string) $item->getKey() : (string) $value;

				// Check if model has activeFilterField property/method
				$activeFilterFields = self::getActiveFilterFieldFromModel($relatedModel);

				// If model specifies activeFilterField, use it
				$resolvedActiveFilter = self::resolveActiveFilterValue($item, $relatedTable, $activeFilterFields);
				if ($resolvedActiveFilter) {
					$displayValue = $resolvedActiveFilter["value"];

					// For Role and BackpackRole, still include name if available
					$modelName = class_basename($relatedModel);
					if (in_array($modelName, ["Role", "BackpackRole"]) && SchemaCache::hasColumn($relatedTable, "name")) {
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
					// If activeFilterField is not set or empty, use primary key directly
					$displayValue = $primaryKey;

					// For Role and BackpackRole, still include name if available
					$modelName = class_basename($relatedModel);
					if (in_array($modelName, ["Role", "BackpackRole"]) && SchemaCache::hasColumn($relatedTable, "name")) {
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
	 * @param  string  $relatedModelClass  Related model class name
	 * @param  mixed  $value  Filter value (usually primary key)
	 * @param  string|null  $searchField  The field name used for the search (e.g., "id", "numero_pratica")
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
			$activeFilterFields = self::getActiveFilterFieldFromModel($relatedModelClass);
			$resolvedActiveFilter = self::resolveActiveFilterValue($item, $relatedTable, $activeFilterFields);
			if ($resolvedActiveFilter) {
				$displayValue = $resolvedActiveFilter["value"];

				// For Role and BackpackRole, still include name if available
				$modelName = class_basename($relatedModelClass);
				if (in_array($modelName, ["Role", "BackpackRole"]) && SchemaCache::hasColumn($relatedTable, "name")) {
					$nameValue = method_exists($item, "getAttribute")
						? $item->getAttribute("name")
						: (isset($item->name)
							? $item->name
							: null);

					if ($nameValue !== null && $nameValue !== "") {
						$displayValue = $nameValue . " (" . $displayValue . ")";
					}
				}

				return ["value" => $displayValue, "field" => $resolvedActiveFilter["field"]];
				// If activeFilterField is empty, fallback to primary key
			}

			// If activeFilterField is not set or empty, use primary key directly
			$primaryKey = method_exists($item, "getKey") ? (string) $item->getKey() : (string) $value;
			$displayValue = $primaryKey;

			// For Role and BackpackRole, still include name if available
			$modelName = class_basename($relatedModelClass);
			if (in_array($modelName, ["Role", "BackpackRole"]) && SchemaCache::hasColumn($relatedTable, "name")) {
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
	 * @param  string  $relatedModelClass  Related model class name
	 * @param  mixed  $value  Filter value (usually primary key)
	 * @param  string|null  $searchField  The field name used for the search (e.g., "id", "numero_pratica")
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
	 * Get relation info for filter description text
	 * Returns array with primary key, unique field, related table singular name, current table singular name
	 */
	public static function getRelationFilterInfo($column, $crud, $isHasMany = false, $hasManyRelation = null): array
	{
		$currentModel = $crud->model;
		$currentTableName = $currentModel->getTable();
		$currentTableSingular = Str::singular($currentTableName);
		$currentTableSingularUpper = strtoupper(str_replace("_", " ", $currentTableSingular));

		if ($isHasMany && $hasManyRelation) {
			// HasMany relation
			$relatedModelClass = $hasManyRelation["model"] ?? null;
			if (!$relatedModelClass && isset($hasManyRelation["name"])) {
				$relatedModelName = Str::studly($hasManyRelation["name"]);
				$relatedModelClass = "App\Models\\" . $relatedModelName;
			}

			if ($relatedModelClass && class_exists($relatedModelClass)) {
				$relatedModel = new $relatedModelClass();
				$relatedTableName = $relatedModel->getTable();
				$relatedTableSingular = Str::singular($relatedTableName);
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
					$relatedModelName = Str::studly($relationName);
					$relatedModelClass = "App\Models\\" . $relatedModelName;
					if (class_exists($relatedModelClass)) {
						$relatedModel = new $relatedModelClass();
					}
				}
			} else {
				$relatedModelName = Str::studly($relationName);
				$relatedModelClass = "App\Models\\" . $relatedModelName;
				if (class_exists($relatedModelClass)) {
					$relatedModel = new $relatedModelClass();
				}
			}

			if ($relatedModel) {
				$relatedTableName = $relatedModel->getTable();
				$relatedTableSingular = Str::singular($relatedTableName);
				$relatedTableSingularUpper = strtoupper(str_replace("_", " ", $relatedTableSingular));

				$primaryKey = $relatedModel->getKeyName();
				$primaryKeyUpper = strtoupper(str_replace("_", " ", $primaryKey));

				return [
					"primary_key" => $primaryKeyUpper,
					"unique_field" => null,
					"related_table_singular" => $relatedTableSingularUpper,
					"current_table_singular" => $currentTableSingularUpper,
					"placeholder" => self::buildRelationFilterPlaceholder($relatedModel, $currentTableSingularUpper),
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
	 * BelongsTo / HasOne relations with advanced field-filter metadata, keyed by local FK column.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function singularRelationsByForeignKey($model): array
	{
		$indexed = [];

		foreach (self::attachRelationFilterableFields(self::getSingularRelations($model)) as $relation) {
			$foreignKey = $relation["foreign_key"] ?? null;
			if (is_string($foreignKey) && $foreignKey !== "") {
				$indexed[$foreignKey] = $relation;
			}
		}

		return $indexed;
	}

	/**
	 * All relations (HasMany + BelongsTo + HasOne) that support relation field filters.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function relationsWithFieldFilterMetadata($model): array
	{
		$hasMany = self::attachHasManyRelationFilterableFields(
			self::groupHasManyRelationsByName(self::getHasManyRelations($model) ?? [])
		);

		return array_merge($hasMany, self::attachRelationFilterableFields(self::getSingularRelations($model)));
	}

	/**
	 * Discover BelongsTo and HasOne relations on a model for advanced relation field filters.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function getSingularRelations($model): array
	{
		$relations = [];
		$modelInstance = is_string($model) ? new $model() : $model;
		$reflectionClass = new \ReflectionClass($modelInstance);

		foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
			if (
				$method->class !== get_class($modelInstance) ||
				!ModelRelations::isRelationMethod($method) ||
				$method->getNumberOfParameters() > 0 ||
				str_starts_with($method->getName(), "__") ||
				str_starts_with($method->getName(), "get") ||
				str_starts_with($method->getName(), "set") ||
				str_starts_with($method->getName(), "scope")
			) {
				continue;
			}

			try {
				$return = $method->invoke($modelInstance);
			} catch (\Throwable $e) {
				continue;
			}

			if (!($return instanceof BelongsTo || $return instanceof HasOne)) {
				continue;
			}

			$relatedModel = get_class($return->getRelated());

			$relations[] = [
				"name" => $method->getName(),
				"type" => $return instanceof BelongsTo ? "BelongsTo" : "HasOne",
				"model" => $relatedModel,
				"foreign_key" => $return->getForeignKeyName(),
				"local_key" => $return instanceof BelongsTo ? $return->getOwnerKeyName() : $return->getLocalKeyName(),
				"searchable_keys" => RelationSearchOperation::getSearchableKeys($relatedModel),
			];
		}

		return $relations;
	}

	/**
	 * Attach advanced filterable fields metadata to relation definitions.
	 *
	 * @param  list<array<string, mixed>>  $relations
	 * @return list<array<string, mixed>>
	 */
	public static function attachRelationFilterableFields(array $relations): array
	{
		return array_map(function (array $relation): array {
			$relation["filterable_fields"] = RelationSearchOperation::getRelationFilterableFields(
				$relation["model"],
				$relation["foreign_key"] ?? null
			);

			return $relation;
		}, $relations);
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
				!ModelRelations::isRelationMethod($method) ||
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
				if ($return instanceof HasMany) {
					$relatedModel = get_class($return->getRelated());
					$foreignKey = $return->getForeignKeyName();
					$localKey = $return->getLocalKeyName();

					// Get searchable keys for the related model (id, numero_pratica, etc.)
					$searchableKeys = RelationSearchOperation::getSearchableKeys($relatedModel);

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
	 * Attach advanced filterable fields metadata to grouped HasMany relations.
	 */
	private static function attachHasManyRelationFilterableFields(array $hasManyRelations): array
	{
		return self::attachRelationFilterableFields($hasManyRelations);
	}

	/**
	 * Parse a HasMany relation field filter request key (e.g. tiraggi_rel_status).
	 *
	 * @return array{relation: array, relation_name: string, field: string, field_info: array, param_base: string}|null
	 */
	public static function parseHasManyRelationFieldFilterKey(string $key, array $hasManyRelations): ?array
	{
		foreach ($hasManyRelations as $relation) {
			$prefix = $relation["name"] . "_rel_";
			if (!str_starts_with($key, $prefix)) {
				continue;
			}

			$suffix = substr($key, strlen($prefix));
			$fieldName = $suffix;
			foreach (["_not_empty", "_empty", "_from", "_to"] as $modifier) {
				if (str_ends_with($fieldName, $modifier)) {
					$fieldName = substr($fieldName, 0, -strlen($modifier));
					break;
				}
			}

			foreach ($relation["filterable_fields"] ?? [] as $fieldInfo) {
				if (($fieldInfo["field"] ?? "") !== $fieldName) {
					continue;
				}

				return [
					"relation" => $relation,
					"relation_name" => $relation["name"],
					"field" => $fieldName,
					"field_info" => $fieldInfo,
					"param_base" => $prefix . $fieldName,
				];
			}
		}

		return null;
	}

	/**
	 * Build active-filter badge label for a HasMany relation field filter.
	 */
	public static function getHasManyRelationFieldFilterLabel(array $context): string
	{
		$relationLabel = ucfirst(str_replace("_", " ", $context["relation_name"]));
		$fieldLabel = $context["field_info"]["label"] ?? self::cleanFieldName($context["field"]);

		return "{$relationLabel} · {$fieldLabel}";
	}

	/**
	 * Resolve display value for a HasMany relation field filter badge.
	 */
	public static function getHasManyRelationFieldFilterDisplayValue(string $key, $value, array $context): string
	{
		if (str_ends_with($key, "_not_empty")) {
			return trans("backpack::filters.not_empty");
		}

		if (str_ends_with($key, "_empty")) {
			return trans("backpack::filters.empty");
		}

		$fieldInfo = $context["field_info"];
		$fieldType = $fieldInfo["type"] ?? "text";

		if ($fieldType === "select" && !empty($fieldInfo["options"][$value])) {
			return (string) $fieldInfo["options"][$value];
		}

		if ($fieldType === "boolean") {
			return $value === "1" ? trans("backpack::filters.yes") : trans("backpack::filters.no");
		}

		if ($fieldType === "fk_autocomplete") {
			$relatedModelClass = $context["relation"]["model"] ?? null;
			if (is_string($relatedModelClass) && class_exists($relatedModelClass)) {
				$relatedTable = (new $relatedModelClass())->getTable();
				$label = self::getForeignKeyFieldDisplayLabel($relatedTable, $context["field"], $value);
				if ($label !== "") {
					return $label;
				}
			}
		}

		return (string) $value;
	}

	/**
	 * Build removal query params for a HasMany relation field filter badge.
	 */
	public static function getHasManyRelationFieldFilterRemoveParams(string $key, array $context): array
	{
		$paramBase = $context["param_base"];

		return request()->except([
			"page",
			$paramBase,
			$paramBase . "_not_empty",
			$paramBase . "_empty",
			$paramBase . "_from",
			$paramBase . "_to",
			$context["relation_name"] . "_filter_mode",
		]);
	}

	/**
	 * Build a comma-separated list of searchable key labels for relation search tab hint.
	 */
	public static function getHasManySearchableKeysLabel(array $relation): string
	{
		$labels = [];
		foreach ($relation["searchable_keys"] ?? [] as $keyInfo) {
			$label = $keyInfo["label"] ?? self::cleanFieldName($keyInfo["field"] ?? "");
			if ($label !== "") {
				$labels[] = $label;
			}
		}

		return implode(", ", array_unique($labels));
	}

	/**
	 * Count active filters on a HasMany relation card (for badge display).
	 */
	public static function countHasManyRelationActiveFilters(array $relation): int
	{
		$count = 0;
		$relationName = $relation["name"];

		$countOp = request()->get($relationName . "_count_op");
		$countVal = request()->get($relationName . "_count");
		if ($countOp !== null && $countOp !== "" && $countVal !== null && $countVal !== "" && is_numeric($countVal)) {
			$count++;
		}

		$firstKey = $relation["searchable_keys"][0] ?? null;
		if ($firstKey) {
			$filterKey = $relationName . "_" . $firstKey["field"];
			if (request()->filled($filterKey)) {
				$count++;
			}
			if (request()->get($filterKey . "_not_empty") === "1" || request()->get($filterKey . "_empty") === "1") {
				$count++;
			}
		}

		foreach ($relation["filterable_fields"] ?? [] as $fieldInfo) {
			if (self::hasActiveHasManyRelationFieldFilter($relation, $fieldInfo)) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Count active filters on a BelongsTo relation card.
	 */
	public static function countBelongsToRelationActiveFilters(string $columnName, ?array $relation = null): int
	{
		$count = 0;

		if (request()->filled($columnName)) {
			$count++;
		}
		if (request()->get($columnName . "_not_empty") === "1" || request()->get($columnName . "_empty") === "1") {
			$count++;
		}

		if ($relation !== null) {
			foreach ($relation["filterable_fields"] ?? [] as $fieldInfo) {
				if (self::hasActiveHasManyRelationFieldFilter($relation, $fieldInfo)) {
					$count++;
				}
			}
		}

		return $count;
	}

	/**
	 * Check whether a HasMany relation field filter is active in the current request.
	 */
	public static function hasActiveHasManyRelationFieldFilter(array $relation, array $fieldInfo): bool
	{
		$paramBase = RelationSearchOperation::relationFieldParamPrefix($relation["name"], $fieldInfo["field"]);

		return request()->filled($paramBase) ||
			request()->get($paramBase . "_not_empty") === "1" ||
			request()->get($paramBase . "_empty") === "1" ||
			request()->filled($paramBase . "_from") ||
			request()->filled($paramBase . "_to");
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

			// Check for relation-level count filter (esattamente n / più di n / meno di n)
			$countOpKey = $relationName . "_count_op";
			$countKey = $relationName . "_count";
			if (
				isset($allRequestParams[$countOpKey]) &&
				$allRequestParams[$countOpKey] !== null &&
				$allRequestParams[$countOpKey] !== "" &&
				isset($allRequestParams[$countKey]) &&
				$allRequestParams[$countKey] !== null &&
				$allRequestParams[$countKey] !== "" &&
				is_numeric($allRequestParams[$countKey])
			) {
				$count++;

				continue;
			}

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

			// Also check relation_field_not_empty / relation_field_empty (form sends e.g. storici_id_not_empty)
			if (!$hasRelationFilter && isset($relation["searchable_keys"])) {
				foreach ($relation["searchable_keys"] as $keyInfo) {
					$filterKey = $relationName . "_" . $keyInfo["field"];
					$fieldNotEmpty = $filterKey . "_not_empty";
					$fieldEmpty = $filterKey . "_empty";
					if (
						(isset($allRequestParams[$fieldNotEmpty]) && $allRequestParams[$fieldNotEmpty] === "1") ||
						(isset($allRequestParams[$fieldEmpty]) && $allRequestParams[$fieldEmpty] === "1")
					) {
						$hasRelationFilter = true;
						break;
					}
				}
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

			foreach ($relation["filterable_fields"] ?? [] as $fieldInfo) {
				$paramBase = RelationSearchOperation::relationFieldParamPrefix($relationName, $fieldInfo["field"]);

				if (
					(isset($allRequestParams[$paramBase]) &&
						$allRequestParams[$paramBase] !== null &&
						$allRequestParams[$paramBase] !== "") ||
					(isset($allRequestParams[$paramBase . "_not_empty"]) &&
						$allRequestParams[$paramBase . "_not_empty"] === "1") ||
					(isset($allRequestParams[$paramBase . "_empty"]) && $allRequestParams[$paramBase . "_empty"] === "1") ||
					(isset($allRequestParams[$paramBase . "_from"]) &&
						$allRequestParams[$paramBase . "_from"] !== null &&
						$allRequestParams[$paramBase . "_from"] !== "") ||
					(isset($allRequestParams[$paramBase . "_to"]) &&
						$allRequestParams[$paramBase . "_to"] !== null &&
						$allRequestParams[$paramBase . "_to"] !== "")
				) {
					$count++;
				}
			}
		}

		return $count;
	}
=======
                            [$relatedTable]
                        );

                        $uniqueValue = null;
                        if (! empty($uniqueFields)) {
                            $uniqueField = $uniqueFields[0]->COLUMN_NAME;
                            if (method_exists($item, 'getAttribute')) {
                                $uniqueValue = $item->getAttribute($uniqueField);
                            } elseif (isset($item->$uniqueField)) {
                                $uniqueValue = $item->$uniqueField;
                            }
                        }

                        if ($uniqueValue !== null && $uniqueValue !== '' && (string) $uniqueValue !== $pkValue) {
                            $displayValue = $pkValue.' - '.$uniqueValue;
                        } else {
                            $displayValue = $pkValue;
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
                $options['__null__'] = "Non collegati → ({$nullCount}) {$currentModelName}";
            }

            // Sort options alphabetically
            asort($options);
        }

        return $options;
    }

    /**
     * Get text filter count
     */
    private static function getTextFilterCount(Collection $textColumns): int
    {
        return collect(request()->all())
            ->filter(function ($value, $key) use ($textColumns) {
                // Check for regular text filters
                if ($textColumns->contains('name', $key) && $value !== null && $value !== '') {
                    return true;
                }
                // Check for not_empty filters
                if (str_ends_with($key, '_not_empty') && $value === '1') {
                    $originalKey = str_replace('_not_empty', '', $key);

                    return $textColumns->contains('name', $originalKey);
                }
                // Check for empty filters
                if (str_ends_with($key, '_empty') && $value === '1') {
                    $originalKey = str_replace('_empty', '', $key);

                    return $textColumns->contains('name', $originalKey);
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
                return $booleanColumns->contains('name', $key) && $value !== null && $value !== '';
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
                if (str_ends_with($key, '_type')) {
                    return false;
                }

                // Handle regular date filters
                if ($dateColumns->contains('name', $key) && $value !== null && $value !== '') {
                    return true;
                }

                // Handle date range filters
                if (str_ends_with($key, '_from') || str_ends_with($key, '_to')) {
                    $baseField = str_replace(['_from', '_to'], '', $key);

                    // Only count each date range once
                    if (! $processedDateRanges->contains($baseField) && $dateColumns->contains('name', $baseField)) {
                        $fromValue = request()->get($baseField.'_from');
                        $toValue = request()->get($baseField.'_to');

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
                return $selectColumns->contains('name', $key) && $value !== null && $value !== '';
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
                return $pathFields->contains('name', $key) && $value !== null && $value !== '';
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
                if ($key === 'page_filter' && $value !== null && $value !== '') {
                    return true;
                }
                // Check for regular relation filters
                if ($relationColumns->contains('name', $key) && $value !== null && $value !== '') {
                    return true;
                }
                // Check for not_empty filters on relations
                if (str_ends_with($key, '_not_empty') && ($value === '1' || $value === 1 || $value === 'true')) {
                    $originalKey = str_replace('_not_empty', '', $key);

                    return $relationColumns->contains('name', $originalKey);
                }
                // Check for empty filters on relations
                if (str_ends_with($key, '_empty') && ($value === '1' || $value === 1 || $value === 'true')) {
                    $originalKey = str_replace('_empty', '', $key);

                    return $relationColumns->contains('name', $originalKey);
                }

                return false;
            })
            ->count();
    }

    /**
     * Get active filter field(s) from a model class.
     * Supports string or array values from $activeFilterField / getActiveFilterField().
     *
     * @param  string  $modelClass  Model class name
     * @return array<int, string>
     */
    private static function getActiveFilterFieldFromModel(string $modelClass): array
    {
        if (! class_exists($modelClass)) {
            return [];
        }

        try {
            // Check for $activeFilterField property (static or instance) using reflection
            $reflection = new \ReflectionClass($modelClass);
            if ($reflection->hasProperty('activeFilterField')) {
                $property = $reflection->getProperty('activeFilterField');
                $property->setAccessible(true);

                // If property is static, get value directly; otherwise create instance
                if ($property->isStatic()) {
                    $value = $property->getValue();
                } else {
                    $instance = $reflection->newInstanceWithoutConstructor();
                    $value = $property->getValue($instance);
                }

                return self::normalizeActiveFilterFields($value);
            }

            // Check for getActiveFilterField() method
            if ($reflection->hasMethod('getActiveFilterField')) {
                $method = $reflection->getMethod('getActiveFilterField');
                if ($method->isStatic() || $method->isPublic()) {
                    $instance = $reflection->newInstanceWithoutConstructor();
                    $value = $method->invoke($instance);

                    return self::normalizeActiveFilterFields($value);
                }
            }
        } catch (\Exception $e) {
            // If anything fails, return null
            return [];
        }

        return [];
    }

    /**
     * Normalize active filter field config into a clean list of field names.
     *
     * @return array<int, string>
     */
    private static function normalizeActiveFilterFields($value): array
    {
        if (is_string($value) && trim($value) !== '') {
            return [trim($value)];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($field) {
            return is_string($field) ? trim($field) : '';
        }, $value), fn ($field) => $field !== ''));
    }

    /**
     * Resolve first non-empty value from configured active filter fields.
     *
     * @return array{value:string,field:string}|null
     */
    private static function resolveActiveFilterValue($item, string $table, array $fields): ?array
    {
        $resolvedValues = [];
        $firstResolvedField = null;

        foreach ($fields as $field) {
            if (! SchemaCache::hasColumn($table, $field)) {
                continue;
            }

            $fieldValue = method_exists($item, 'getAttribute')
                ? $item->getAttribute($field)
                : (isset($item->$field) ? $item->$field : null);

            if ($fieldValue !== null && $fieldValue !== '') {
                if ($firstResolvedField === null) {
                    $firstResolvedField = $field;
                }
                $resolvedValues[] = (string) $fieldValue;
            }
        }

        if (! empty($resolvedValues) && $firstResolvedField !== null) {
            return [
                'value' => implode(' - ', $resolvedValues),
                'field' => $firstResolvedField,
            ];
        }

        return null;
    }

    /**
     * Get relation display value for active filters
     */
    private static function getRelationDisplayValue($columnName, $value, $crud): string
    {
        if ($value === '__null__') {
            return 'Non collegati';
        }

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
                $relatedModel = 'App\\Models\\'.$relatedModelName;
            }
        } else {
            // Fallback
            $relatedModelName = Str::studly($relationName);
            $relatedModel = 'App\\Models\\'.$relatedModelName;
        }

        $displayValue = 'ID: '.$value; // Default fallback

        if (class_exists($relatedModel)) {
            // Get the referenced column from the foreign key constraint
            $foreignKeys = self::getForeignKeysForTable($currentTableName);
            $referencedColumn = $foreignKeys[$columnName]['column'] ?? null;

            // Try to find the item using the referenced column (for custom FKs)
            $item = null;
            if ($referencedColumn) {
                $item = call_user_func([$relatedModel, 'where'], $referencedColumn, $value)->first();
            }

            // Fallback to primary key search
            if (! $item) {
                $item = call_user_func([$relatedModel, 'find'], $value);
            }

            if ($item) {
                $label = self::resolveRelationItemLabel($item, $relatedModel, $value);
                if ($label !== '—') {
                    return $label;
                }

                // Get table name for unique field lookup
                $relatedTable = is_string($relatedModel) ? (new $relatedModel)->getTable() : $relatedModel->getTable();
                $relatedModelInstance = is_string($relatedModel) ? new $relatedModel : $relatedModel;
                $primaryKey = method_exists($item, 'getKey') ? (string) $item->getKey() : (string) $value;

                // Check if model has activeFilterField property/method
                $activeFilterFields = self::getActiveFilterFieldFromModel($relatedModel);

                // If model specifies activeFilterField, use it
                $resolvedActiveFilter = self::resolveActiveFilterValue($item, $relatedTable, $activeFilterFields);
                if ($resolvedActiveFilter) {
                    $displayValue = $resolvedActiveFilter['value'];

                    // For Role and BackpackRole, still include name if available
                    $modelName = class_basename($relatedModel);
                    if (
                        in_array($modelName, ['Role', 'BackpackRole']) &&
                        SchemaCache::hasColumn($relatedTable, 'name')
                    ) {
                        $nameValue = method_exists($item, 'getAttribute')
                            ? $item->getAttribute('name')
                            : (isset($item->name)
                                ? $item->name
                                : null);

                        if ($nameValue !== null && $nameValue !== '') {
                            $displayValue = $nameValue.' ('.$displayValue.')';
                        }
                    }
                } else {
                    // If activeFilterField is not set or empty, use primary key directly
                    $displayValue = $primaryKey;

                    // For Role and BackpackRole, still include name if available
                    $modelName = class_basename($relatedModel);
                    if (
                        in_array($modelName, ['Role', 'BackpackRole']) &&
                        SchemaCache::hasColumn($relatedTable, 'name')
                    ) {
                        $nameValue = method_exists($item, 'getAttribute')
                            ? $item->getAttribute('name')
                            : (isset($item->name)
                                ? $item->name
                                : null);

                        if ($nameValue !== null && $nameValue !== '') {
                            $displayValue = $nameValue.' ('.$displayValue.')';
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
     * @param  string  $relatedModelClass  Related model class name
     * @param  mixed  $value  Filter value (usually primary key)
     * @param  string|null  $searchField  The field name used for the search (e.g., "id", "username")
     * @return array ['value' => string, 'field' => string|null] Display value and field name used (null if primary key)
     */
    public static function getHasManyFilterDisplayValueAndField(
        string $relatedModelClass,
        $value,
        ?string $searchField = null
    ): array {
        if (! class_exists($relatedModelClass)) {
            return ['value' => (string) $value, 'field' => null];
        }

        try {
            $relatedModel = new $relatedModelClass;
            $relatedTable = $relatedModel->getTable();

            // Find the item
            $item = $relatedModelClass::find($value);

            if (! $item) {
                return ['value' => (string) $value, 'field' => null];
            }

            // Check if model has activeFilterField property/method (highest priority)
            $activeFilterFields = self::getActiveFilterFieldFromModel($relatedModelClass);
            $resolvedActiveFilter = self::resolveActiveFilterValue($item, $relatedTable, $activeFilterFields);
            if ($resolvedActiveFilter) {
                $displayValue = $resolvedActiveFilter['value'];

                // For Role and BackpackRole, still include name if available
                $modelName = class_basename($relatedModelClass);
                if (
                    in_array($modelName, ['Role', 'BackpackRole']) &&
                    SchemaCache::hasColumn($relatedTable, 'name')
                ) {
                    $nameValue = method_exists($item, 'getAttribute')
                        ? $item->getAttribute('name')
                        : (isset($item->name)
                            ? $item->name
                            : null);

                    if ($nameValue !== null && $nameValue !== '') {
                        $displayValue = $nameValue.' ('.$displayValue.')';
                    }
                }

                return ['value' => $displayValue, 'field' => $resolvedActiveFilter['field']];
                // If activeFilterField is empty, fallback to primary key
            }

            // If activeFilterField is not set or empty, use primary key directly
            $primaryKey = method_exists($item, 'getKey') ? (string) $item->getKey() : (string) $value;
            $displayValue = $primaryKey;

            // For Role and BackpackRole, still include name if available
            $modelName = class_basename($relatedModelClass);
            if (
                in_array($modelName, ['Role', 'BackpackRole']) &&
                SchemaCache::hasColumn($relatedTable, 'name')
            ) {
                $nameValue = method_exists($item, 'getAttribute')
                    ? $item->getAttribute('name')
                    : (isset($item->name)
                        ? $item->name
                        : null);

                if ($nameValue !== null && $nameValue !== '') {
                    $displayValue = $nameValue.' ('.$displayValue.')';
                }
            }

            return ['value' => $displayValue, 'field' => null];
        } catch (\Exception $e) {
            // If anything fails, return the value as string
            return ['value' => (string) $value, 'field' => null];
        }
    }

    /**
     * Get display value for hasMany relation filter: unique field if exists and not empty, otherwise primary key
     * If a searchField is provided and it's "id", shows the id value instead of unique field
     *
     * @param  string  $relatedModelClass  Related model class name
     * @param  mixed  $value  Filter value (usually primary key)
     * @param  string|null  $searchField  The field name used for the search (e.g., "id", "username")
     * @return string Display value
     */
    public static function getHasManyFilterDisplayValue(
        string $relatedModelClass,
        $value,
        ?string $searchField = null
    ): string {
        $result = self::getHasManyFilterDisplayValueAndField($relatedModelClass, $value, $searchField);

        return $result['value'];
    }

    /**
     * Get relation info for filter description text
     * Returns array with primary key, unique field, related table singular name, current table singular name
     */
    public static function getRelationFilterInfo($column, $crud, $isHasMany = false, $hasManyRelation = null): array
    {
        $currentModel = $crud->model;
        $currentTableName = $currentModel->getTable();
        $currentTableSingular = Str::singular($currentTableName);
        $currentTableSingularUpper = strtoupper(str_replace('_', ' ', $currentTableSingular));

        if ($isHasMany && $hasManyRelation) {
            // HasMany relation
            $relatedModelClass = $hasManyRelation['model'] ?? null;
            if (! $relatedModelClass && isset($hasManyRelation['name'])) {
                $relatedModelName = Str::studly($hasManyRelation['name']);
                $relatedModelClass = "App\Models\\".$relatedModelName;
            }

            if ($relatedModelClass && class_exists($relatedModelClass)) {
                $relatedModel = new $relatedModelClass;
                $relatedTableName = $relatedModel->getTable();
                $relatedTableSingular = Str::singular($relatedTableName);
                $relatedTableSingularUpper = strtoupper(str_replace('_', ' ', $relatedTableSingular));

                $primaryKey = $relatedModel->getKeyName();
                $primaryKeyUpper = strtoupper(str_replace('_', ' ', $primaryKey));

                // Get unique field (first searchable key that is not primary)
                $uniqueField = null;
                if (isset($hasManyRelation['searchable_keys'])) {
                    foreach ($hasManyRelation['searchable_keys'] as $keyInfo) {
                        if (isset($keyInfo['field']) && $keyInfo['field'] !== $primaryKey && ! $keyInfo['is_primary']) {
                            $uniqueField = $keyInfo['field'];
                            break;
                        }
                    }
                }

                $uniqueFieldUpper = $uniqueField ? strtoupper(str_replace('_', ' ', $uniqueField)) : null;

                return [
                    'primary_key' => $primaryKeyUpper,
                    'unique_field' => $uniqueFieldUpper,
                    'related_table_singular' => $relatedTableSingularUpper,
                    'current_table_singular' => $currentTableSingularUpper,
                ];
            }
        } else {
            // BelongsTo relation
            $relationName = self::getRelationNameFromForeignKey($column['name'], $currentTableName);
            $relatedModel = null;

            if (method_exists($currentModel, $relationName)) {
                try {
                    $relationQuery = $currentModel->$relationName();
                    $relatedModel = $relationQuery->getRelated();
                } catch (\Exception $e) {
                    $relatedModelName = Str::studly($relationName);
                    $relatedModelClass = "App\Models\\".$relatedModelName;
                    if (class_exists($relatedModelClass)) {
                        $relatedModel = new $relatedModelClass;
                    }
                }
            } else {
                $relatedModelName = Str::studly($relationName);
                $relatedModelClass = "App\Models\\".$relatedModelName;
                if (class_exists($relatedModelClass)) {
                    $relatedModel = new $relatedModelClass;
                }
            }

            if ($relatedModel) {
                $relatedTableName = $relatedModel->getTable();
                $relatedTableSingular = Str::singular($relatedTableName);
                $relatedTableSingularUpper = strtoupper(str_replace('_', ' ', $relatedTableSingular));

                $primaryKey = $relatedModel->getKeyName();
                $primaryKeyUpper = strtoupper(str_replace('_', ' ', $primaryKey));

                return [
                    'primary_key' => $primaryKeyUpper,
                    'unique_field' => null,
                    'related_table_singular' => $relatedTableSingularUpper,
                    'current_table_singular' => $currentTableSingularUpper,
                    'placeholder' => self::buildRelationFilterPlaceholder($relatedModel, $currentTableSingularUpper),
                ];
            }
        }

        // Fallback
        return [
            'primary_key' => 'ID',
            'unique_field' => null,
            'related_table_singular' => 'TABELLA',
            'current_table_singular' => $currentTableSingularUpper,
        ];
    }

    /**
     * BelongsTo / HasOne relations with advanced field-filter metadata, keyed by local FK column.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function singularRelationsByForeignKey($model): array
    {
        $indexed = [];

        foreach (self::attachRelationFilterableFields(self::getSingularRelations($model)) as $relation) {
            $foreignKey = $relation['foreign_key'] ?? null;
            if (is_string($foreignKey) && $foreignKey !== '') {
                $indexed[$foreignKey] = $relation;
            }
        }

        return $indexed;
    }

    /**
     * All relations (HasMany + BelongsTo + HasOne) that support relation field filters.
     *
     * @return list<array<string, mixed>>
     */
    public static function relationsWithFieldFilterMetadata($model): array
    {
        $hasMany = self::attachHasManyRelationFilterableFields(
            self::groupHasManyRelationsByName(self::getHasManyRelations($model) ?? [])
        );

        return array_merge($hasMany, self::attachRelationFilterableFields(self::getSingularRelations($model)));
    }

    /**
     * Discover BelongsTo and HasOne relations on a model for advanced relation field filters.
     *
     * @return list<array<string, mixed>>
     */
    public static function getSingularRelations($model): array
    {
        $relations = [];
        $modelInstance = is_string($model) ? new $model : $model;
        $reflectionClass = new \ReflectionClass($modelInstance);

        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (
                $method->class !== get_class($modelInstance) ||
                ! ModelRelations::isRelationMethod($method) ||
                $method->getNumberOfParameters() > 0 ||
                str_starts_with($method->getName(), '__') ||
                str_starts_with($method->getName(), 'get') ||
                str_starts_with($method->getName(), 'set') ||
                str_starts_with($method->getName(), 'scope')
            ) {
                continue;
            }

            try {
                $return = $method->invoke($modelInstance);
            } catch (\Throwable $e) {
                continue;
            }

            if (! ($return instanceof BelongsTo || $return instanceof HasOne)) {
                continue;
            }

            $relatedModel = get_class($return->getRelated());

            $relations[] = [
                'name' => $method->getName(),
                'type' => $return instanceof BelongsTo ? 'BelongsTo' : 'HasOne',
                'model' => $relatedModel,
                'foreign_key' => $return->getForeignKeyName(),
                'local_key' => $return instanceof BelongsTo
                    ? $return->getOwnerKeyName()
                    : $return->getLocalKeyName(),
                'searchable_keys' => RelationSearchOperation::getSearchableKeys($relatedModel),
            ];
        }

        return $relations;
    }

    /**
     * Attach advanced filterable fields metadata to relation definitions.
     *
     * @param  list<array<string, mixed>>  $relations
     * @return list<array<string, mixed>>
     */
    public static function attachRelationFilterableFields(array $relations): array
    {
        return array_map(function (array $relation): array {
            $relation['filterable_fields'] = RelationSearchOperation::getRelationFilterableFields(
                $relation['model'],
                $relation['foreign_key'] ?? null
            );

            return $relation;
        }, $relations);
    }

    /**
     * Get all HasMany relations from a model
     * Returns array with relation name, related model, and foreign key info
     */
    private static function getHasManyRelations($model): array
    {
        $relations = [];
        $modelInstance = is_string($model) ? new $model : $model;
        $reflectionClass = new \ReflectionClass($modelInstance);

        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip magic methods, getters, setters, etc.
            if (
                $method->class !== get_class($modelInstance) ||
                ! ModelRelations::isRelationMethod($method) ||
                $method->getNumberOfParameters() > 0 ||
                strpos($method->getName(), '__') === 0 ||
                strpos($method->getName(), 'get') === 0 ||
                strpos($method->getName(), 'set') === 0 ||
                strpos($method->getName(), 'scope') === 0
            ) {
                continue;
            }

            try {
                $return = $method->invoke($modelInstance);

                // Check if it's a HasMany relation
                if ($return instanceof HasMany) {
                    $relatedModel = get_class($return->getRelated());
                    $foreignKey = $return->getForeignKeyName();
                    $localKey = $return->getLocalKeyName();

                    // Get searchable keys for the related model (id, username, etc.)
                    $searchableKeys = RelationSearchOperation::getSearchableKeys(
                        $relatedModel
                    );

                    $relations[] = [
                        'name' => $method->getName(),
                        'type' => 'HasMany',
                        'model' => $relatedModel,
                        'foreign_key' => $foreignKey,
                        'local_key' => $localKey,
                        'searchable_keys' => $searchableKeys,
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
     * Attach advanced filterable fields metadata to grouped HasMany relations.
     */
    private static function attachHasManyRelationFilterableFields(array $hasManyRelations): array
    {
        return self::attachRelationFilterableFields($hasManyRelations);
    }

    /**
     * Parse a HasMany relation field filter request key (e.g. articles_rel_published).
     *
     * @return array{relation: array, relation_name: string, field: string, field_info: array, param_base: string}|null
     */
    public static function parseHasManyRelationFieldFilterKey(string $key, array $hasManyRelations): ?array
    {
        foreach ($hasManyRelations as $relation) {
            $prefix = $relation['name'].'_rel_';
            if (! str_starts_with($key, $prefix)) {
                continue;
            }

            $suffix = substr($key, strlen($prefix));
            $fieldName = $suffix;
            foreach (['_not_empty', '_empty', '_from', '_to'] as $modifier) {
                if (str_ends_with($fieldName, $modifier)) {
                    $fieldName = substr($fieldName, 0, -strlen($modifier));
                    break;
                }
            }

            foreach ($relation['filterable_fields'] ?? [] as $fieldInfo) {
                if (($fieldInfo['field'] ?? '') !== $fieldName) {
                    continue;
                }

                return [
                    'relation' => $relation,
                    'relation_name' => $relation['name'],
                    'field' => $fieldName,
                    'field_info' => $fieldInfo,
                    'param_base' => $prefix.$fieldName,
                ];
            }
        }

        return null;
    }

    /**
     * Build active-filter badge label for a HasMany relation field filter.
     */
    public static function getHasManyRelationFieldFilterLabel(array $context): string
    {
        $relationLabel = ucfirst(str_replace('_', ' ', $context['relation_name']));
        $fieldLabel = $context['field_info']['label'] ?? self::cleanFieldName($context['field']);

        return "{$relationLabel} · {$fieldLabel}";
    }

    /**
     * Resolve display value for a HasMany relation field filter badge.
     */
    public static function getHasManyRelationFieldFilterDisplayValue(string $key, $value, array $context): string
    {
        if (str_ends_with($key, '_not_empty')) {
            return trans('backpack::filters.not_empty');
        }

        if (str_ends_with($key, '_empty')) {
            return trans('backpack::filters.empty');
        }

        $fieldInfo = $context['field_info'];
        $fieldType = $fieldInfo['type'] ?? 'text';

        if ($fieldType === 'select' && ! empty($fieldInfo['options'][$value])) {
            return (string) $fieldInfo['options'][$value];
        }

        if ($fieldType === 'boolean') {
            return $value === '1' ? trans('backpack::filters.yes') : trans('backpack::filters.no');
        }

        if ($fieldType === 'fk_autocomplete') {
            $relatedModelClass = $context['relation']['model'] ?? null;
            if (is_string($relatedModelClass) && class_exists($relatedModelClass)) {
                $relatedTable = (new $relatedModelClass)->getTable();
                $label = self::getForeignKeyFieldDisplayLabel($relatedTable, $context['field'], $value);
                if ($label !== '') {
                    return $label;
                }
            }
        }

        return (string) $value;
    }

    /**
     * Build removal query params for a HasMany relation field filter badge.
     */
    public static function getHasManyRelationFieldFilterRemoveParams(string $key, array $context): array
    {
        $paramBase = $context['param_base'];

        return request()->except([
            'page',
            $paramBase,
            $paramBase.'_not_empty',
            $paramBase.'_empty',
            $paramBase.'_from',
            $paramBase.'_to',
            $context['relation_name'].'_filter_mode',
        ]);
    }

    /**
     * Build a comma-separated list of searchable key labels for relation search tab hint.
     */
    public static function getHasManySearchableKeysLabel(array $relation): string
    {
        $labels = [];
        foreach ($relation['searchable_keys'] ?? [] as $keyInfo) {
            $label = $keyInfo['label'] ?? self::cleanFieldName($keyInfo['field'] ?? '');
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return implode(', ', array_unique($labels));
    }

    /**
     * Count active filters on a HasMany relation card (for badge display).
     */
    public static function countHasManyRelationActiveFilters(array $relation): int
    {
        $count = 0;
        $relationName = $relation['name'];

        $countOp = request()->get($relationName.'_count_op');
        $countVal = request()->get($relationName.'_count');
        if ($countOp !== null && $countOp !== '' && $countVal !== null && $countVal !== '' && is_numeric($countVal)) {
            $count++;
        }

        $firstKey = $relation['searchable_keys'][0] ?? null;
        if ($firstKey) {
            $filterKey = $relationName.'_'.$firstKey['field'];
            if (request()->filled($filterKey)) {
                $count++;
            }
            if (request()->get($filterKey.'_not_empty') === '1' || request()->get($filterKey.'_empty') === '1') {
                $count++;
            }
        }

        foreach ($relation['filterable_fields'] ?? [] as $fieldInfo) {
            if (self::hasActiveHasManyRelationFieldFilter($relation, $fieldInfo)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Count active filters on a BelongsTo relation card.
     */
    public static function countBelongsToRelationActiveFilters(string $columnName, ?array $relation = null): int
    {
        $count = 0;

        if (request()->filled($columnName)) {
            $count++;
        }
        if (request()->get($columnName.'_not_empty') === '1' || request()->get($columnName.'_empty') === '1') {
            $count++;
        }

        if ($relation !== null) {
            foreach ($relation['filterable_fields'] ?? [] as $fieldInfo) {
                if (self::hasActiveHasManyRelationFieldFilter($relation, $fieldInfo)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Check whether a HasMany relation field filter is active in the current request.
     */
    public static function hasActiveHasManyRelationFieldFilter(array $relation, array $fieldInfo): bool
    {
        $paramBase = RelationSearchOperation::relationFieldParamPrefix(
            $relation['name'],
            $fieldInfo['field']
        );

        return request()->filled($paramBase)
            || request()->get($paramBase.'_not_empty') === '1'
            || request()->get($paramBase.'_empty') === '1'
            || request()->filled($paramBase.'_from')
            || request()->filled($paramBase.'_to');
    }

    /**
     * Group HasMany relations by name (instead of by searchable key)
     * This consolidates relations with multiple searchable keys into single entries
     * Example: users_username and users_email become one "users" entry with multiple keys
     */
    private static function groupHasManyRelationsByName(array $hasManyRelations): array
    {
        $grouped = [];

        foreach ($hasManyRelations as $relation) {
            $name = $relation['name'];

            // If this relation doesn't exist yet, add it
            if (! isset($grouped[$name])) {
                $grouped[$name] = $relation;
            }
            // If it exists and has more searchable keys, keep all keys
            // (This shouldn't happen with current implementation, but good to be safe)
        }

        return array_values($grouped);
    }

    /**
     * Get filter count for HasMany relations
     * Now counts filters for each searchable key (id, username, etc.)
     * Also counts _empty and _not_empty filters at relation level
     */
    private static function getHasManyRelationsFilterCount(array $hasManyRelations): int
    {
        $count = 0;
        $allRequestParams = request()->all();

        foreach ($hasManyRelations as $relation) {
            $relationName = $relation['name'];
            $hasRelationFilter = false;

            // Check for relation-level count filter (esattamente n / più di n / meno di n)
            $countOpKey = $relationName.'_count_op';
            $countKey = $relationName.'_count';
            if (
                isset($allRequestParams[$countOpKey]) &&
                $allRequestParams[$countOpKey] !== null &&
                $allRequestParams[$countOpKey] !== '' &&
                isset($allRequestParams[$countKey]) &&
                $allRequestParams[$countKey] !== null &&
                $allRequestParams[$countKey] !== '' &&
                is_numeric($allRequestParams[$countKey])
            ) {
                $count++;

                continue;
            }

            // Check for relation-level _empty and _not_empty filters
            $emptyKey = $relationName.'_empty';
            $notEmptyKey = $relationName.'_not_empty';

            // Check if either filter exists in request and has a truthy value
            if (
                isset($allRequestParams[$emptyKey]) &&
                $allRequestParams[$emptyKey] !== null &&
                $allRequestParams[$emptyKey] !== '' &&
                ($allRequestParams[$emptyKey] === '1' ||
                    $allRequestParams[$emptyKey] === 1 ||
                    $allRequestParams[$emptyKey] === 'true')
            ) {
                $hasRelationFilter = true;
            } elseif (
                isset($allRequestParams[$notEmptyKey]) &&
                $allRequestParams[$notEmptyKey] !== null &&
                $allRequestParams[$notEmptyKey] !== '' &&
                ($allRequestParams[$notEmptyKey] === '1' ||
                    $allRequestParams[$notEmptyKey] === 1 ||
                    $allRequestParams[$notEmptyKey] === 'true')
            ) {
                $hasRelationFilter = true;
            }

            // Also check relation_field_not_empty / relation_field_empty (form sends e.g. media_id_not_empty)
            if (! $hasRelationFilter && isset($relation['searchable_keys'])) {
                foreach ($relation['searchable_keys'] as $keyInfo) {
                    $filterKey = $relationName.'_'.$keyInfo['field'];
                    $fieldNotEmpty = $filterKey.'_not_empty';
                    $fieldEmpty = $filterKey.'_empty';
                    if (
                        (isset($allRequestParams[$fieldNotEmpty]) && $allRequestParams[$fieldNotEmpty] === '1') ||
                        (isset($allRequestParams[$fieldEmpty]) && $allRequestParams[$fieldEmpty] === '1')
                    ) {
                        $hasRelationFilter = true;
                        break;
                    }
                }
            }

            if ($hasRelationFilter) {
                $count++;
            }

            // Count filters for each searchable key
            if (isset($relation['searchable_keys'])) {
                foreach ($relation['searchable_keys'] as $keyInfo) {
                    $filterKey = $relationName.'_'.$keyInfo['field'];
                    if (
                        isset($allRequestParams[$filterKey]) &&
                        $allRequestParams[$filterKey] !== null &&
                        $allRequestParams[$filterKey] !== ''
                    ) {
                        $count++;
                    }
                }
            }

            foreach ($relation['filterable_fields'] ?? [] as $fieldInfo) {
                $paramBase = RelationSearchOperation::relationFieldParamPrefix(
                    $relationName,
                    $fieldInfo['field']
                );

                if (
                    (isset($allRequestParams[$paramBase]) && $allRequestParams[$paramBase] !== null && $allRequestParams[$paramBase] !== '') ||
                    (isset($allRequestParams[$paramBase.'_not_empty']) && $allRequestParams[$paramBase.'_not_empty'] === '1') ||
                    (isset($allRequestParams[$paramBase.'_empty']) && $allRequestParams[$paramBase.'_empty'] === '1') ||
                    (isset($allRequestParams[$paramBase.'_from']) && $allRequestParams[$paramBase.'_from'] !== null && $allRequestParams[$paramBase.'_from'] !== '') ||
                    (isset($allRequestParams[$paramBase.'_to']) && $allRequestParams[$paramBase.'_to'] !== null && $allRequestParams[$paramBase.'_to'] !== '')
                ) {
                    $count++;
                }
            }
        }

        return $count;
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
}
