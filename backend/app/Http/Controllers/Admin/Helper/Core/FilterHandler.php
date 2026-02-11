<?php

namespace App\Http\Controllers\Admin\Helper\Core;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Library\Widget;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Http\Controllers\Admin\Helper\Core\FieldConfigHandler;

class FilterHandler
{
	/**
	 * Applies filters directly to a query builder for CSV export
	 *
	 * @param \Illuminate\Database\Eloquent\Builder $query Query builder
	 * @param Model $model Model instance
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	public static function applyFiltersToQuery($query, Model $model)
	{
		$request = request();

		// Get filters from request excluding system parameters
		$getFilters = $request->except(
			"page",
			"persistent-table",
			"_token",
			"draw",
			"columns",
			"order",
			"start",
			"length",
			"search",
			"downloadToken",
			"datatable_id"
		);

		// Apply GET filters (from our custom form)
		foreach ($getFilters as $key => $value) {
			// Skip empty values, arrays, and null values
			if ($value === "" || $value === null || is_array($value)) {
				continue;
			}

			// Also skip if it's just whitespace
			if (is_string($value) && trim($value) === "") {
				continue;
			}

			self::applyFilterToQuery($query, $model, $key, $value);
		}

		// Also handle special page_filter
		if ($pageFilter = $request->get("page_filter")) {
			$query->where("page", "=", $pageFilter);
		}

		return $query;
	}

	/**
	 * Applies filters to CRUD based on request parameters
	 *
	 * Processes filter values from the request and adds
	 * corresponding where clauses to the CRUD query
	 *
	 * @param Model $model Current model
	 */
	public static function applyFilters(Model $model)
	{
		$request = request();

		// Get filters from both GET parameters and DataTables search parameters
		// Exclude page parameter to reset pagination when applying filters
		$getFilters = $request->except(
			"page",
			"persistent-table",
			"_token",
			"draw",
			"columns",
			"order",
			"start",
			"length",
			"search",
			"datatable_id"
		);
		$datatableSearch = $request->get("search", []);

		// Apply GET filters (from our custom form)
		foreach ($getFilters as $key => $value) {
			// Skip empty values, arrays, and null values
			if ($value === "" || $value === null || is_array($value)) {
				continue;
			}

			// Also skip if it's just whitespace
			if (is_string($value) && trim($value) === "") {
				continue;
			}

			self::applyFilter($model, $key, $value);
		}

		// Also handle special page_filter
		if ($pageFilter = $request->get("page_filter")) {
			CRUD::addClause("where", "page", "=", $pageFilter);
		}

		// Handle HasMany relation filters
		self::applyHasManyFilters($model);

		self::configureButtons();
	}

	/**
	 * Apply a single filter directly to query builder (for CSV export)
	 */
	private static function applyFilterToQuery($query, Model $model, string $key, $value)
	{
		// Special handling for path fields
		if (strpos($key, "_path") !== false) {
			if ($value === "1") {
				$query->whereNotNull($key)->where($key, "!=", "");
			} elseif ($value === "0") {
				$query->where(function ($q) use ($key) {
					$q->whereNull($key)->orWhere($key, "");
				});
			}
			return;
		}

		// Special handling for not_empty filters
		if (str_ends_with($key, "_not_empty")) {
			$originalKey = str_replace("_not_empty", "", $key);
			// Check if this is a hasMany relation filter
			if (self::isHasManyRelationFilter($originalKey, $model)) {
				// Use whereHas for hasMany relations
				$hasManyRelations = self::getHasManyRelations($model);
				foreach ($hasManyRelations as $relation) {
					$relationName = $relation["name"];
					$searchableKeys = \App\Http\Controllers\Admin\Ajax\RelationSearchController::getSearchableKeys(
						$relation["model"]
					);
					foreach ($searchableKeys as $keyInfo) {
						$filterKey = $relationName . "_" . $keyInfo["field"];
						if ($originalKey === $filterKey || $originalKey === $relationName) {
							$query->whereHas($relationName);
							return;
						}
					}
				}
			} else {
				// Regular field - check if column exists
				if (Schema::hasColumn($model->getTable(), $originalKey)) {
					$query->whereNotNull($originalKey);
					$columnType = Schema::getColumnType($model->getTable(), $originalKey);
					if ($columnType === "string" || $columnType === "varchar" || $columnType === "text") {
						$query->where($originalKey, "!=", "");
					}
				}
			}
			return;
		}

		// Special handling for empty filters
		if (str_ends_with($key, "_empty")) {
			$originalKey = str_replace("_empty", "", $key);
			// Check if this is a hasMany relation filter
			if (self::isHasManyRelationFilter($originalKey, $model)) {
				// Use whereDoesntHave for hasMany relations
				$hasManyRelations = self::getHasManyRelations($model);
				foreach ($hasManyRelations as $relation) {
					$relationName = $relation["name"];
					$searchableKeys = \App\Http\Controllers\Admin\Ajax\RelationSearchController::getSearchableKeys(
						$relation["model"]
					);
					foreach ($searchableKeys as $keyInfo) {
						$filterKey = $relationName . "_" . $keyInfo["field"];
						if ($originalKey === $filterKey || $originalKey === $relationName) {
							$query->whereDoesntHave($relationName);
							return;
						}
					}
				}
			} else {
				// Regular field - check if column exists
				if (Schema::hasColumn($model->getTable(), $originalKey)) {
					$columnType = Schema::getColumnType($model->getTable(), $originalKey);
					if ($columnType === "string" || $columnType === "varchar" || $columnType === "text") {
						$query->where(function ($q) use ($originalKey) {
							$q->whereNull($originalKey)->orWhere($originalKey, "");
						});
					} else {
						$query->whereNull($originalKey);
					}
				}
			}
			return;
		}

		// Special handling for date range filters
		if (str_ends_with($key, "_from") || str_ends_with($key, "_to")) {
			$baseField = str_replace(["_from", "_to"], "", $key);
			if (Schema::hasColumn($model->getTable(), $baseField)) {
				$request = request();
				$fromValue = $request->get($baseField . "_from");
				$toValue = $request->get($baseField . "_to");

				if ($fromValue) {
					$query->whereDate($baseField, ">=", $fromValue);
				}
				if ($toValue) {
					$query->whereDate($baseField, "<=", $toValue);
				}
			}
			return;
		}

		// Check if this is a HasMany relation filter (e.g., tiraggi_id, tiraggi_numero_pratica)
		$hasManyRelations = self::getHasManyRelations($model);
		foreach ($hasManyRelations as $relation) {
			$relationName = $relation["name"];
			$relatedModelClass = $relation["model"];
			$searchableKeys = \App\Http\Controllers\Admin\Ajax\RelationSearchController::getSearchableKeys(
				$relatedModelClass
			);

			foreach ($searchableKeys as $keyInfo) {
				$filterKey = $relationName . "_" . $keyInfo["field"];
				if ($key === $filterKey) {
					// Determine the actual field to use in whereHas
					// If searching by 'id', use the model's actual primary key name
					$searchFieldName = $keyInfo["field"];
					if ($searchFieldName === "id") {
						$relatedModelInstance = new $relatedModelClass();
						$searchFieldName = $relatedModelInstance->getKeyName();
					}

					// Apply whereHas filter
					$query->whereHas($relationName, function ($q) use ($searchFieldName, $value) {
						$q->where($searchFieldName, "=", $value);
					});
					return;
				}
			}
		}

		if (!Schema::hasColumn($model->getTable(), $key)) {
			return;
		}

		// Get table and column type
		$table = $model->getTable();
		$columnType = Schema::getColumnType($table, $key);

		// Check if column is nullable
		$database = config('database.connections.mysql.database');
		$isNullable =
			DB::table("INFORMATION_SCHEMA.COLUMNS")
				->where("TABLE_SCHEMA", $database)
				->where("TABLE_NAME", $table)
				->where("COLUMN_NAME", $key)
				->value("IS_NULLABLE") === "YES";

		// Check if this is a select field (status, enum, etc.) - use exact match
		if (FieldConfigHandler::isSelectField($key, $table)) {
			$query->where($key, "=", $value);
		} elseif ($columnType === "boolean" || $columnType === "tinyint") {
			// Explicitly check for "0" and "1", allowing null if field is nullable
			if ($value === "1" || $value === "0") {
				$query->where($key, "=", $isNullable && $value === "0" ? null : (int) $value);
			}
		} elseif ($columnType === "date" || $columnType === "datetime" || $columnType === "timestamp") {
			// Handle date fields - exact match
			$query->whereDate($key, "=", $value);
		} elseif (self::isForeignKeyColumn($key, $model->getTable()) || $key === "page" || $key === "id") {
			// Handle special "__null__" value for "Non collegati" option
			if ($value === "__null__") {
				$query->whereNull($key);
			} else {
				// Apply exact match for relation fields (foreign keys), page field, and ID field
				$query->where($key, "=", $value);
			}
		} else {
			// Apply LIKE filter for text fields
			$query->where($key, "LIKE", "%" . $value . "%");
		}
	}

	/**
	 * Apply a single filter to the CRUD query
	 */
	private static function applyFilter(Model $model, string $key, $value)
	{
		// Special handling for path fields
		if (strpos($key, "_path") !== false) {
			self::handlePathFieldFilter($key, $value);
			return;
		}

		// Special handling for not_empty filters
		if (str_ends_with($key, "_not_empty")) {
			$originalKey = str_replace("_not_empty", "", $key);
			// Check if this is a hasMany relation filter
			if (self::isHasManyRelationFilter($originalKey, $model)) {
				self::handleHasManyNotEmptyFilter($originalKey, $value, $model);
			} else {
				self::handleNotEmptyFilter($originalKey, $value);
			}
			return;
		}

		// Special handling for empty filters
		if (str_ends_with($key, "_empty")) {
			$originalKey = str_replace("_empty", "", $key);
			// Check if this is a hasMany relation filter
			if (self::isHasManyRelationFilter($originalKey, $model)) {
				self::handleHasManyEmptyFilter($originalKey, $value, $model);
			} else {
				self::handleEmptyFilter($originalKey, $value);
			}
			return;
		}

		// Special handling for date range filters
		if (str_ends_with($key, "_from") || str_ends_with($key, "_to")) {
			self::handleDateRangeFilter($key, $value, $model);
			return;
		}

		// Check if this is a HasMany relation filter (e.g., tiraggi_id, tiraggi_numero_pratica)
		$hasManyRelations = self::getHasManyRelations($model);
		foreach ($hasManyRelations as $relation) {
			$relationName = $relation["name"];
			$relatedModelClass = $relation["model"];
			$searchableKeys = \App\Http\Controllers\Admin\Ajax\RelationSearchController::getSearchableKeys(
				$relatedModelClass
			);

			foreach ($searchableKeys as $keyInfo) {
				$filterKey = $relationName . "_" . $keyInfo["field"];
				if ($key === $filterKey) {
					// Determine the actual field to use in whereHas
					// If searching by 'id', use the model's actual primary key name
					$searchFieldName = $keyInfo["field"];
					if ($searchFieldName === "id") {
						$relatedModelInstance = new $relatedModelClass();
						$searchFieldName = $relatedModelInstance->getKeyName();
					}

					// Apply whereHas filter for CRUD
					CRUD::addClause("whereHas", $relationName, function ($query) use ($searchFieldName, $value) {
						$query->where($searchFieldName, "=", $value);
					});
					return;
				}
			}
		}

		if (!Schema::hasColumn($model->getTable(), $key)) {
			return;
		}

		// Get table and column type
		$table = $model->getTable();
		$columnType = Schema::getColumnType($table, $key);

		// Check if column is nullable
		$database = config('database.connections.mysql.database');
		$isNullable =
			DB::table("INFORMATION_SCHEMA.COLUMNS")
				->where("TABLE_SCHEMA", $database)
				->where("TABLE_NAME", $table)
				->where("COLUMN_NAME", $key)
				->value("IS_NULLABLE") === "YES";

		// Check if this is a select field (status, enum, etc.) - use exact match
		if (FieldConfigHandler::isSelectField($key, $table)) {
			CRUD::addClause("where", $key, "=", $value);
		} elseif ($columnType === "boolean" || $columnType === "tinyint") {
			// Explicitly check for "0" and "1", allowing null if field is nullable
			if ($value === "1" || $value === "0") {
				CRUD::addClause("where", $key, "=", $isNullable && $value === "0" ? null : (int) $value);
			}
		} elseif ($columnType === "date" || $columnType === "datetime" || $columnType === "timestamp") {
			// Handle date fields - exact match
			CRUD::addClause("whereDate", $key, "=", $value);
		} elseif (self::isForeignKeyColumn($key, $model->getTable()) || $key === "page" || $key === "id") {
			// Handle special "__null__" value for "Non collegati" option
			if ($value === "__null__") {
				CRUD::addClause("whereNull", $key);
			} else {
				// Apply exact match for relation fields (foreign keys), page field, and ID field
				CRUD::addClause("where", $key, "=", $value);
			}
		} else {
			// Apply LIKE filter for text fields
			CRUD::addClause("where", $key, "LIKE", "%" . $value . "%");
		}
	}

	/**
	 * Handles filtering for fields with '_path' in their name
	 *
	 * @param string $key Field name
	 * @param string $value Filter value
	 */
	private static function handlePathFieldFilter($key, $value)
	{
		// For path fields, check if they're empty or not based on checkbox value
		if ($value === "1") {
			// Has file (not empty)
			CRUD::addClause("whereNotNull", $key);
			CRUD::addClause("where", $key, "!=", "");
		} elseif ($value === "0") {
			// No file (empty)
			CRUD::addClause(function ($query) use ($key) {
				$query->whereNull($key)->orWhere($key, "");
			});
		}
	}

	/**
	 * Handles filtering for "not empty" fields
	 *
	 * @param string $key Original field name
	 * @param string $value Filter value
	 */
	private static function handleNotEmptyFilter($key, $value)
	{
		if ($value === "1") {
			// Field is not empty (has value)
			$model = new (CRUD::getModel())();
			if (!Schema::hasColumn($model->getTable(), $key)) {
				return;
			}
			CRUD::addClause("whereNotNull", $key);
			// Only add != "" check for string fields, not numeric fields
			$columnType = Schema::getColumnType($model->getTable(), $key);
			if ($columnType === "string" || $columnType === "varchar" || $columnType === "text") {
				CRUD::addClause("where", $key, "!=", "");
			}
		}
	}

	/**
	 * Handles filtering for date range fields
	 *
	 * @param string $key Field name (with _from or _to suffix)
	 * @param string $value Filter value
	 * @param Model $model Model instance
	 */
	private static function handleDateRangeFilter($key, $value, $model)
	{
		$request = request();
		$table = $model->getTable();

		// Extract the base field name
		$baseField = str_replace(["_from", "_to"], "", $key);

		// Check if the base field exists
		if (!Schema::hasColumn($table, $baseField)) {
			return;
		}

		// Get both from and to values
		$fromValue = $request->get($baseField . "_from");
		$toValue = $request->get($baseField . "_to");

		// Only apply range filter if we have at least one value
		if ($fromValue || $toValue) {
			// Apply from date filter (>=)
			if ($fromValue) {
				CRUD::addClause("whereDate", $baseField, ">=", $fromValue);
			}

			// Apply to date filter (<=)
			if ($toValue) {
				CRUD::addClause("whereDate", $baseField, "<=", $toValue);
			}
		}
	}

	/**
	 * Configures buttons and widgets for list view
	 */
	public static function configureButtons()
	{
		CRUD::removeButton("create");
		// CRUD::addButtonFromView("top", "import_csv", "import_csv", "end");
		// CRUD::addButtonFromView("top", "export_csv", "export_csv", "end");
		CRUD::addButtonFromView("top", "csv_actions", "csv_actions", "end");
		CRUD::addButtonFromView("top", "create", "create_filters");
	}

	/**
	 * Configures drag-and-drop sorting functionality
	 */
	public static function configureDragSortButton()
	{
		Widget::add()->type("script")->content("static/js/draggable-sort.js");
		CRUD::addButtonFromView("top", "draggable_button", "draggable_button");
	}

	/**
	 * Performs sorting of the specified model
	 *
	 * Updates the order field for each model instance based
	 * on the new sort order provided in the request
	 *
	 * @param Request $request HTTP request
	 * @param string $modelName Model name to sort
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public static function sort(Request $request, $modelName)
	{
		$modelName = ucfirst($modelName);
		$newOrder = $request->newSortOrder;
		$newOrderArray = explode(",", $newOrder);
		$model = "App\\Models\\$modelName";

		for ($i = 0; $i < count($newOrderArray); $i++) {
			$item = $model::find($newOrderArray[$i]);
			if ($item) {
				$item->order = $i + 1;
				$item->save();
			}
		}

		return redirect()->back();
	}

	/**
	 * Cache for foreign keys per table
	 */
	private static $foreignKeysCache = [];

	/**
	 * Get all foreign keys for a table by inspecting database schema
	 *
	 * @param string $tableName Table name
	 * @return array Array of foreign key info: ['column' => ['table' => referenced_table, 'column' => referenced_column]]
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
	private static function isForeignKeyColumn(string $columnName, ?string $tableName = null): bool
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
	 * Adds a has/no file filter for path fields
	 *
	 * @param string $fieldName The path field name to filter
	 * @param string $label Custom label for the filter (optional)
	 */
	public static function addPathFieldFilter($fieldName, $label = null)
	{
		if (strpos($fieldName, "_path") === false) {
			$fieldName .= "_path";
		}

		$filterLabel = $label ?? ucfirst(str_replace(["_path", "_"], ["", " "], $fieldName));

		CRUD::addFilter(
			[
				"name" => $fieldName,
				"type" => "simple",
				"label" => $filterLabel,
			],
			[
				"1" => trans("backpack::filters.with_file"),
				"0" => trans("backpack::filters.without_file"),
			],
			function ($value) use ($fieldName) {
				if ($value == 1) {
					CRUD::addClause("whereNotNull", $fieldName);
					CRUD::addClause("where", $fieldName, "!=", "");
				} else {
					CRUD::addClause(function ($query) use ($fieldName) {
						$query->whereNull($fieldName)->orWhere($fieldName, "");
					});
				}
			}
		);
	}

	/**
	 * Handles filtering for empty fields
	 *
	 * @param string $fieldName Field name
	 * @param string $value Filter value
	 */
	private static function handleEmptyFilter($fieldName, $value)
	{
		if ($value !== "1") {
			return;
		}

		// Check if the field exists in the database
		$model = new (CRUD::getModel())();
		if (!Schema::hasColumn($model->getTable(), $fieldName)) {
			return;
		}

		// Apply empty filter (null or empty string)
		// Only check for empty string if it's a string field
		$columnType = Schema::getColumnType($model->getTable(), $fieldName);
		if ($columnType === "string" || $columnType === "varchar" || $columnType === "text") {
			CRUD::addClause(function ($query) use ($fieldName) {
				$query->whereNull($fieldName)->orWhere($fieldName, "");
			});
		} else {
			// For numeric fields, only check for null
			CRUD::addClause("whereNull", $fieldName);
		}
	}

	/**
	 * Apply HasMany relation filters with AJAX search
	 * Filters parent records based on specific IDs in related records
	 * Supports multiple searchable keys (id, numero_pratica, etc.)
	 */
	private static function applyHasManyFilters($model)
	{
		$request = request();

		// Get all HasMany relations from the model
		$hasManyRelations = self::getHasManyRelations($model);

		foreach ($hasManyRelations as $relation) {
			$relationName = $relation["name"];
			$relatedModelClass = $relation["model"];

			// Get searchable keys for this relation
			$searchableKeys = \App\Http\Controllers\Admin\Ajax\RelationSearchController::getSearchableKeys(
				$relatedModelClass
			);

			// Check each searchable key
			foreach ($searchableKeys as $keyInfo) {
				$filterKey = $relationName . "_" . $keyInfo["field"];
				$filterValue = $request->get($filterKey);

				if ($filterValue === null || $filterValue === "") {
					continue;
				}

				// Determine the actual field to use in whereHas
				// If searching by 'id', use the model's actual primary key name
				$searchFieldName = $keyInfo["field"];
				if ($searchFieldName === "id") {
					$relatedModelInstance = new $relatedModelClass();
					$searchFieldName = $relatedModelInstance->getKeyName();
				}

				// Apply whereHas with condition on the specific key
				CRUD::addClause("whereHas", $relationName, function ($query) use ($searchFieldName, $filterValue) {
					$query->where($searchFieldName, "=", $filterValue);
				});
			}
		}
	}

	/**
	 * Check if a field name is a hasMany relation filter
	 */
	private static function isHasManyRelationFilter($fieldName, $model): bool
	{
		$hasManyRelations = self::getHasManyRelations($model);
		foreach ($hasManyRelations as $relation) {
			$relationName = $relation["name"];
			$searchableKeys = \App\Http\Controllers\Admin\Ajax\RelationSearchController::getSearchableKeys(
				$relation["model"]
			);
			foreach ($searchableKeys as $keyInfo) {
				$filterKey = $relationName . "_" . $keyInfo["field"];
				if ($fieldName === $filterKey) {
					return true;
				}
			}
			// Also check if fieldName matches just the relation name (for empty/not_empty filters)
			if ($fieldName === $relationName) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Handle not_empty filter for hasMany relations
	 */
	private static function handleHasManyNotEmptyFilter($fieldName, $value, $model)
	{
		if ($value !== "1") {
			return;
		}

		// Extract relation name from fieldName
		// Could be "tiraggi_id" (relation_field) or "tiraggi" (just relation)
		$hasManyRelations = self::getHasManyRelations($model);
		foreach ($hasManyRelations as $relation) {
			$relationName = $relation["name"];

			// Check if fieldName matches relation name directly
			if ($fieldName === $relationName) {
				CRUD::addClause("whereHas", $relationName);
				return;
			}

			// Check if fieldName matches relation_field pattern
			$searchableKeys = \App\Http\Controllers\Admin\Ajax\RelationSearchController::getSearchableKeys(
				$relation["model"]
			);
			foreach ($searchableKeys as $keyInfo) {
				$filterKey = $relationName . "_" . $keyInfo["field"];
				if ($fieldName === $filterKey) {
					// Use whereHas to filter records that have the relation
					CRUD::addClause("whereHas", $relationName);
					return;
				}
			}
		}
	}

	/**
	 * Handle empty filter for hasMany relations
	 */
	private static function handleHasManyEmptyFilter($fieldName, $value, $model)
	{
		if ($value !== "1") {
			return;
		}

		// Extract relation name from fieldName
		// Could be "tiraggi_id" (relation_field) or "tiraggi" (just relation)
		$hasManyRelations = self::getHasManyRelations($model);
		foreach ($hasManyRelations as $relation) {
			$relationName = $relation["name"];

			// Check if fieldName matches relation name directly
			if ($fieldName === $relationName) {
				CRUD::addClause("whereDoesntHave", $relationName);
				return;
			}

			// Check if fieldName matches relation_field pattern
			$searchableKeys = \App\Http\Controllers\Admin\Ajax\RelationSearchController::getSearchableKeys(
				$relation["model"]
			);
			foreach ($searchableKeys as $keyInfo) {
				$filterKey = $relationName . "_" . $keyInfo["field"];
				if ($fieldName === $filterKey) {
					// Use whereDoesntHave to filter records that don't have the relation
					CRUD::addClause("whereDoesntHave", $relationName);
					return;
				}
			}
		}
	}

	/**
	 * Get all HasMany relations from a model
	 * Returns array with relation name, related model, foreign key info, and searchable keys
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

					$relations[] = [
						"name" => $method->getName(),
						"type" => "HasMany",
						"model" => $relatedModel,
						"foreign_key" => $foreignKey,
						"local_key" => $localKey,
					];
				}
			} catch (\Throwable $e) {
				// Skip methods that throw errors
				continue;
			}
		}

		return $relations;
	}
}
