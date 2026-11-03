<?php

namespace App\Support\Backpack\Handler;

use App\Support\Backpack\ModelRelations;
use App\Support\Backpack\SchemaCache;
use App\Http\Controllers\Admin\Support\RelationSearchOperation;
use App\Support\Backpack\FilterHelper;
use App\Support\Backpack\PresetFilterHandler;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FilterHandler
{
    /**
     * Applies filters directly to a query builder for CSV export
     *
     * @param  Builder  $query  Query builder
     * @param  Model  $model  Model instance
     * @return Builder
     */
    public static function applyFiltersToQuery($query, Model $model)
    {
        $request = request();

        // Get filters from request excluding system parameters
        $getFilters = $request->except(
            'page',
            'persistent-table',
            '_token',
            'draw',
            'columns',
            'order',
            'start',
            'length',
            'search',
            'downloadToken',
            'datatable_id'
        );

        // Apply GET filters (from our custom form)
        foreach ($getFilters as $key => $value) {
            // Skip empty values, arrays, and null values
            if ($value === '' || $value === null || is_array($value)) {
                continue;
            }

            // Also skip if it's just whitespace
            if (is_string($value) && trim($value) === '') {
                continue;
            }

            self::applyFilterToQuery($query, $model, $key, $value);
        }

        // Also handle special page_filter
        if ($pageFilter = $request->get('page_filter')) {
            $query->where('page', '=', $pageFilter);
        }

        self::applyUnifiedHasManyRelationFiltersToQuery($query, $model);
        self::applyUnifiedSingularRelationFieldFiltersToQuery($query, $model);

        return $query;
    }

    /**
     * Applies filters to CRUD based on request parameters
     *
     * Processes filter values from the request and adds
     * corresponding where clauses to the CRUD query
     *
     * @param  Model  $model  Current model
     */
    public static function applyFilters(Model $model)
    {
        // Preset mode: apply preset filters and skip advanced filters entirely.
        if (request()->has('preset') && request()->get('preset') !== '') {
            PresetFilterHandler::applyPreset($model);
            self::configureButtons();

            return;
        }

        $request = request();

        // Get filters from both GET parameters and DataTables search parameters
        // Exclude page parameter to reset pagination when applying filters
        $getFilters = $request->except(
            'page',
            'persistent-table',
            '_token',
            'draw',
            'columns',
            'order',
            'start',
            'length',
            'search',
            'datatable_id'
        );
        $datatableSearch = $request->get('search', []);

        // Apply GET filters (from our custom form)
        foreach ($getFilters as $key => $value) {
            // Skip empty values, arrays, and null values
            if ($value === '' || $value === null || is_array($value)) {
                continue;
            }

            // Also skip if it's just whitespace
            if (is_string($value) && trim($value) === '') {
                continue;
            }

            self::applyFilter($model, $key, $value);
        }

        // Also handle special page_filter
        if ($pageFilter = $request->get('page_filter')) {
            CRUD::addClause('where', 'page', '=', $pageFilter);
        }

        self::applyUnifiedHasManyRelationFilters($model);
        self::applyUnifiedSingularRelationFieldFilters($model);

        self::configureButtons();
    }

    /**
     * Apply a single filter directly to query builder (for CSV export)
     */
    private static function applyFilterToQuery($query, Model $model, string $key, $value)
    {
        if (self::isHasManyUnifiedParam($key, $model)) {
            return;
        }

        // Special handling for path fields
        if (strpos($key, '_path') !== false) {
            if ($value === '1') {
                $query->whereNotNull($key)->where($key, '!=', '');
            } elseif ($value === '0') {
                $query->where(function ($q) use ($key) {
                    $q->whereNull($key)->orWhere($key, '');
                });
            }

            return;
        }

        // Special handling for not_empty filters
        if (str_ends_with($key, '_not_empty')) {
            $originalKey = str_replace('_not_empty', '', $key);
            if (self::isHasManyRelationFieldParam($originalKey, $model)) {
                return;
            }
            // Check if this is a hasMany relation filter
            if (self::isHasManyRelationFilter($originalKey, $model)) {
                // Use whereHas for hasMany relations
                $hasManyRelations = self::getHasManyRelations($model);
                foreach ($hasManyRelations as $relation) {
                    $relationName = $relation['name'];
                    $searchableKeys = RelationSearchOperation::getSearchableKeys(
                        $relation['model']
                    );
                    foreach ($searchableKeys as $keyInfo) {
                        $filterKey = $relationName.'_'.$keyInfo['field'];
                        if ($originalKey === $filterKey || $originalKey === $relationName) {
                            $query->whereHas($relationName);

                            return;
                        }
                    }
                }
            } else {
                // Regular field - check if column exists
                if (SchemaCache::hasColumn($model->getTable(), $originalKey)) {
                    $query->whereNotNull($originalKey);
                    $columnType = SchemaCache::getColumnType($model->getTable(), $originalKey);
                    if ($columnType === 'string' || $columnType === 'varchar' || $columnType === 'text') {
                        $query->where($originalKey, '!=', '');
                    }
                }
            }

            return;
        }

        // Special handling for empty filters
        if (str_ends_with($key, '_empty')) {
            $originalKey = str_replace('_empty', '', $key);
            if (self::isHasManyRelationFieldParam($originalKey, $model)) {
                return;
            }
            // Check if this is a hasMany relation filter
            if (self::isHasManyRelationFilter($originalKey, $model)) {
                // Use whereDoesntHave for hasMany relations
                $hasManyRelations = self::getHasManyRelations($model);
                foreach ($hasManyRelations as $relation) {
                    $relationName = $relation['name'];
                    $searchableKeys = RelationSearchOperation::getSearchableKeys(
                        $relation['model']
                    );
                    foreach ($searchableKeys as $keyInfo) {
                        $filterKey = $relationName.'_'.$keyInfo['field'];
                        if ($originalKey === $filterKey || $originalKey === $relationName) {
                            $query->whereDoesntHave($relationName);

                            return;
                        }
                    }
                }
            } else {
                // Regular field - check if column exists
                if (SchemaCache::hasColumn($model->getTable(), $originalKey)) {
                    $columnType = SchemaCache::getColumnType($model->getTable(), $originalKey);
                    if ($columnType === 'string' || $columnType === 'varchar' || $columnType === 'text') {
                        $query->where(function ($q) use ($originalKey) {
                            $q->whereNull($originalKey)->orWhere($originalKey, '');
                        });
                    } else {
                        $query->whereNull($originalKey);
                    }
                }
            }

            return;
        }

        // Special handling for date range filters
        if (str_ends_with($key, '_from') || str_ends_with($key, '_to')) {
            $baseField = str_replace(['_from', '_to'], '', $key);
            if (self::isHasManyRelationFieldParam($baseField, $model)) {
                return;
            }
            if (SchemaCache::hasColumn($model->getTable(), $baseField)) {
                $request = request();
                $fromValue = $request->get($baseField.'_from');
                $toValue = $request->get($baseField.'_to');

                if ($fromValue) {
                    $query->whereDate($baseField, '>=', $fromValue);
                }
                if ($toValue) {
                    $query->whereDate($baseField, '<=', $toValue);
                }
            }

            return;
        }

        if (self::isHasManyRelationFieldParam($key, $model)) {
            return;
        }

        // Check if this is a HasMany relation filter (e.g., tiraggi_id, tiraggi_numero_pratica)
        $hasManyRelations = self::getHasManyRelations($model);
        foreach ($hasManyRelations as $relation) {
            $relationName = $relation['name'];
            $relatedModelClass = $relation['model'];
            $searchableKeys = RelationSearchOperation::getSearchableKeys(
                $relatedModelClass
            );

            foreach ($searchableKeys as $keyInfo) {
                $filterKey = $relationName.'_'.$keyInfo['field'];
                if ($key === $filterKey) {
                    // Determine the actual field to use in whereHas
                    // If searching by 'id', use the model's actual primary key name
                    $searchFieldName = $keyInfo['field'];
                    if ($searchFieldName === 'id') {
                        $relatedModelInstance = new $relatedModelClass;
                        $searchFieldName = $relatedModelInstance->getKeyName();
                    }

                    // Apply whereHas filter
                    $query->whereHas($relationName, function ($q) use ($searchFieldName, $value) {
                        $q->where($searchFieldName, '=', $value);
                    });

                    return;
                }
            }
        }

        if (! SchemaCache::hasColumn($model->getTable(), $key)) {
            if (self::applyRelationColumnFilterToQuery($query, $model, $key, $value)) {
                return;
            }

            return;
        }

        // Get table and column type
        $table = $model->getTable();
        $columnType = SchemaCache::getColumnType($table, $key);

        // Check if column is nullable
        $isNullable = SchemaCache::isNullable($table, $key);

        // Check if this is a select field (status, enum, etc.) - use exact match
        if (FieldConfigHandler::isSelectField($key, $table)) {
            $query->where($key, '=', $value);
        } elseif ($columnType === 'boolean' || $columnType === 'tinyint') {
            // Explicitly check for "0" and "1", allowing null if field is nullable
            if ($value === '1' || $value === '0') {
                $query->where($key, '=', $isNullable && $value === '0' ? null : (int) $value);
            }
        } elseif ($columnType === 'date' || $columnType === 'datetime' || $columnType === 'timestamp') {
            // Handle date fields - exact match
            $query->whereDate($key, '=', $value);
        } elseif (self::isForeignKeyColumn($key, $model->getTable(), $model->getKeyName()) || $key === 'page') {
            // Handle special "__null__" value for "Non collegati" option
            if ($value === '__null__') {
                $query->whereNull($key);
            } else {
                // Apply exact match for relation fields (real FK constraints), page field
                $query->where($key, '=', $value);
            }
        } elseif ($key === $model->getKeyName()) {
            // Primary key field - exact match
            $query->where($key, '=', $value);
        } elseif (
            in_array($columnType, ['integer', 'bigint', 'smallint', 'tinyint', 'decimal', 'float', 'double'], true) ||
            str_contains((string) $columnType, 'int')
        ) {
            $normalized = str_replace(',', '.', (string) $value);
            if (is_numeric($normalized)) {
                $query->where($key, '=', $normalized);
            }
        } else {
            // Apply LIKE filter for text fields
            $query->where($key, 'LIKE', '%'.$value.'%');
        }
    }

    /**
     * Apply a single filter to the CRUD query
     */
    private static function applyFilter(Model $model, string $key, $value)
    {
        if (self::isHasManyUnifiedParam($key, $model)) {
            return;
        }

        // Special handling for path fields
        if (strpos($key, '_path') !== false) {
            self::handlePathFieldFilter($key, $value);

            return;
        }

        // Special handling for not_empty filters
        if (str_ends_with($key, '_not_empty')) {
            $originalKey = str_replace('_not_empty', '', $key);
            if (self::isHasManyRelationFieldParam($originalKey, $model)) {
                return;
            }
            // Check if this is a hasMany relation filter
            if (self::isHasManyRelationFilter($originalKey, $model)) {
                self::handleHasManyNotEmptyFilter($originalKey, $value, $model);
            } else {
                self::handleNotEmptyFilter($originalKey, $value);
            }

            return;
        }

        // Special handling for empty filters
        if (str_ends_with($key, '_empty')) {
            $originalKey = str_replace('_empty', '', $key);
            if (self::isHasManyRelationFieldParam($originalKey, $model)) {
                return;
            }
            // Check if this is a hasMany relation filter
            if (self::isHasManyRelationFilter($originalKey, $model)) {
                self::handleHasManyEmptyFilter($originalKey, $value, $model);
            } else {
                self::handleEmptyFilter($originalKey, $value);
            }

            return;
        }

        // Special handling for date range filters
        if (str_ends_with($key, '_from') || str_ends_with($key, '_to')) {
            $baseField = str_replace(['_from', '_to'], '', $key);
            if (! self::isHasManyRelationFieldParam($baseField, $model)) {
                self::handleDateRangeFilter($key, $value, $model);
            }

            return;
        }

        if (self::isHasManyRelationFieldParam($key, $model)) {
            return;
        }

        // Check if this is a HasMany relation filter (e.g., tiraggi_id, tiraggi_numero_pratica)
        $hasManyRelations = self::getHasManyRelations($model);
        foreach ($hasManyRelations as $relation) {
            $relationName = $relation['name'];
            $relatedModelClass = $relation['model'];
            $searchableKeys = RelationSearchOperation::getSearchableKeys(
                $relatedModelClass
            );

            foreach ($searchableKeys as $keyInfo) {
                $filterKey = $relationName.'_'.$keyInfo['field'];
                if ($key === $filterKey) {
                    // Determine the actual field to use in whereHas
                    // If searching by 'id', use the model's actual primary key name
                    $searchFieldName = $keyInfo['field'];
                    if ($searchFieldName === 'id') {
                        $relatedModelInstance = new $relatedModelClass;
                        $searchFieldName = $relatedModelInstance->getKeyName();
                    }

                    // Apply whereHas filter for CRUD
                    CRUD::addClause('whereHas', $relationName, function ($query) use ($searchFieldName, $value) {
                        $query->where($searchFieldName, '=', $value);
                    });

                    return;
                }
            }
        }

        if (! SchemaCache::hasColumn($model->getTable(), $key)) {
            if (self::applyRelationColumnFilter($model, $key, $value)) {
                return;
            }

            return;
        }

        // Get table and column type
        $table = $model->getTable();
        $columnType = SchemaCache::getColumnType($table, $key);

        // Check if column is nullable
        $isNullable = SchemaCache::isNullable($table, $key);

        // Check if this is a select field (status, enum, etc.) - use exact match
        if (FieldConfigHandler::isSelectField($key, $table)) {
            CRUD::addClause('where', $key, '=', $value);
        } elseif ($columnType === 'boolean' || $columnType === 'tinyint') {
            // Explicitly check for "0" and "1", allowing null if field is nullable
            if ($value === '1' || $value === '0') {
                CRUD::addClause('where', $key, '=', $isNullable && $value === '0' ? null : (int) $value);
            }
        } elseif ($columnType === 'date' || $columnType === 'datetime' || $columnType === 'timestamp') {
            // Handle date fields - exact match
            CRUD::addClause('whereDate', $key, '=', $value);
        } elseif (self::isForeignKeyColumn($key, $model->getTable(), $model->getKeyName()) || $key === 'page') {
            // Handle special "__null__" value for "Non collegati" option
            if ($value === '__null__') {
                CRUD::addClause('whereNull', $key);
            } else {
                // Apply exact match for relation fields (real FK constraints), page field
                CRUD::addClause('where', $key, '=', $value);
            }
        } elseif ($key === $model->getKeyName()) {
            // Primary key field - exact match
            CRUD::addClause('where', $key, '=', $value);
        } else {
            // Apply LIKE filter for text fields
            CRUD::addClause('where', $key, 'LIKE', '%'.$value.'%');
        }
    }

    /**
     * Handles filtering for fields with '_path' in their name
     *
     * @param  string  $key  Field name
     * @param  string  $value  Filter value
     */
    private static function handlePathFieldFilter($key, $value)
    {
        // For path fields, check if they're empty or not based on checkbox value
        if ($value === '1') {
            // Has file (not empty)
            CRUD::addClause('whereNotNull', $key);
            CRUD::addClause('where', $key, '!=', '');
        } elseif ($value === '0') {
            // No file (empty)
            CRUD::addClause(function ($query) use ($key) {
                $query->whereNull($key)->orWhere($key, '');
            });
        }
    }

    /**
     * Handles filtering for "not empty" fields
     *
     * @param  string  $key  Original field name
     * @param  string  $value  Filter value
     */
    private static function handleNotEmptyFilter($key, $value)
    {
        if ($value === '1') {
            // Field is not empty (has value)
            $model = new (CRUD::getModel())();
            if (! SchemaCache::hasColumn($model->getTable(), $key)) {
                return;
            }
            CRUD::addClause('whereNotNull', $key);
            // Only add != "" check for string fields, not numeric fields
            $columnType = SchemaCache::getColumnType($model->getTable(), $key);
            if ($columnType === 'string' || $columnType === 'varchar' || $columnType === 'text') {
                CRUD::addClause('where', $key, '!=', '');
            }
        }
    }

    /**
     * Handles filtering for date range fields
     *
     * @param  string  $key  Field name (with _from or _to suffix)
     * @param  string  $value  Filter value
     * @param  Model  $model  Model instance
     */
    private static function handleDateRangeFilter($key, $value, $model)
    {
        $request = request();
        $table = $model->getTable();

        // Extract the base field name
        $baseField = str_replace(['_from', '_to'], '', $key);

        // Check if the base field exists
        if (! SchemaCache::hasColumn($table, $baseField)) {
            return;
        }

        // Get both from and to values
        $fromValue = $request->get($baseField.'_from');
        $toValue = $request->get($baseField.'_to');

        // Only apply range filter if we have at least one value
        if ($fromValue || $toValue) {
            // Apply from date filter (>=)
            if ($fromValue) {
                CRUD::addClause('whereDate', $baseField, '>=', $fromValue);
            }

            // Apply to date filter (<=)
            if ($toValue) {
                CRUD::addClause('whereDate', $baseField, '<=', $toValue);
            }
        }
    }

    /**
     * Configures buttons and widgets for list view
     */
    public static function configureButtons()
    {
        CRUD::removeButton('create');
        // CRUD::addButtonFromView("top", "import_csv", "import_csv", "end");
        // CRUD::addButtonFromView("top", "export_csv", "export_csv", "end");
        CRUD::addButtonFromView('top', 'csv_actions', 'csv_actions', 'end');
        CRUD::addButtonFromView('top', 'create', 'create_filters');
    }

    /**
     * Configures drag-and-drop sorting functionality
     */
    public static function configureDragSortButton()
    {
        Widget::add()->type('script')->content('static/js/draggable-sort.js');
        CRUD::addButtonFromView('top', 'draggable_button', 'draggable_button');
    }

    /**
     * Performs sorting of the specified model
     *
     * Updates the order field for each model instance based
     * on the new sort order provided in the request
     *
     * @param  Request  $request  HTTP request
     * @param  string  $modelName  Model name to sort
     * @return RedirectResponse
     */
    public static function sort(Request $request, $modelName)
    {
        $modelName = ucfirst($modelName);
        $newOrder = $request->newSortOrder;
        $newOrderArray = explode(',', $newOrder);
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
     * @param  string  $tableName  Table name
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
    private static function isForeignKeyColumn(string $columnName, ?string $tableName = null, ?string $primaryKey = null): bool
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
     * Handles filtering for empty fields
     *
     * @param  string  $fieldName  Field name
     * @param  string  $value  Filter value
     */
    private static function handleEmptyFilter($fieldName, $value)
    {
        if ($value !== '1') {
            return;
        }

        // Check if the field exists in the database
        $model = new (CRUD::getModel())();
        if (! SchemaCache::hasColumn($model->getTable(), $fieldName)) {
            return;
        }

        // Apply empty filter (null or empty string)
        // Only check for empty string if it's a string field
        $columnType = SchemaCache::getColumnType($model->getTable(), $fieldName);
        if ($columnType === 'string' || $columnType === 'varchar' || $columnType === 'text') {
            CRUD::addClause(function ($query) use ($fieldName) {
                $query->whereNull($fieldName)->orWhere($fieldName, '');
            });
        } else {
            // For numeric fields, only check for null
            CRUD::addClause('whereNull', $fieldName);
        }
    }

    /**
     * Apply all HasMany relation filters (search, count, presence, field) per relation.
     * Count and field/search constraints share the same child scope (true AND).
     */
    private static function applyUnifiedHasManyRelationFilters(Model $model): void
    {
        self::applyUnifiedHasManyRelationFilterClauses($model, function (
            string $relationName,
            string $mode,
            ?string $countOperator,
            ?int $countValue,
            ?\Closure $constraintCallback
        ): void {
            if ($mode === 'count') {
                CRUD::addClause(function ($query) use ($relationName, $countOperator, $countValue, $constraintCallback) {
                    if ($constraintCallback) {
                        $query->has($relationName, $countOperator, $countValue, 'and', $constraintCallback);
                    } else {
                        $query->has($relationName, $countOperator, $countValue);
                    }
                });

                return;
            }

            if ($mode === 'whereDoesntHave') {
                if ($constraintCallback) {
                    CRUD::addClause('whereDoesntHave', $relationName, $constraintCallback);
                } else {
                    CRUD::addClause('whereDoesntHave', $relationName);
                }

                return;
            }

            if ($constraintCallback) {
                CRUD::addClause('whereHas', $relationName, $constraintCallback);
            } else {
                CRUD::addClause('whereHas', $relationName);
            }
        });
    }

    /**
     * Apply unified HasMany relation filters to a query builder (CSV export).
     */
    private static function applyUnifiedHasManyRelationFiltersToQuery($query, Model $model): void
    {
        self::applyUnifiedHasManyRelationFilterClauses($model, function (
            string $relationName,
            string $mode,
            ?string $countOperator,
            ?int $countValue,
            ?\Closure $constraintCallback
        ) use ($query): void {
            if ($mode === 'count') {
                if ($constraintCallback) {
                    $query->has($relationName, $countOperator, $countValue, 'and', $constraintCallback);
                } else {
                    $query->has($relationName, $countOperator, $countValue);
                }

                return;
            }

            if ($mode === 'whereDoesntHave') {
                if ($constraintCallback) {
                    $query->whereDoesntHave($relationName, $constraintCallback);
                } else {
                    $query->whereDoesntHave($relationName);
                }

                return;
            }

            if ($constraintCallback) {
                $query->whereHas($relationName, $constraintCallback);
            } else {
                $query->whereHas($relationName);
            }
        });
    }

    /**
     * Build and apply unified HasMany clauses for each relation on the model.
     */
    private static function applyUnifiedHasManyRelationFilterClauses(Model $model, callable $applyClause): void
    {
        $request = request();

        foreach (self::getHasManyRelations($model) as $relation) {
            $relationName = $relation['name'];
            $presence = self::resolveHasManyPresenceFilter($relation, $request);
            $countFilter = self::resolveHasManyCountFilter($relationName, $request);
            $constraintCallback = self::buildHasManyRelationConstraintCallback($relation, $request);

            if ($presence === null && $countFilter === null && $constraintCallback === null) {
                continue;
            }

            if ($countFilter !== null) {
                $applyClause(
                    $relationName,
                    'count',
                    $countFilter['operator'],
                    $countFilter['n'],
                    $constraintCallback
                );

                continue;
            }

            if ($presence === 'empty') {
                $applyClause($relationName, 'whereDoesntHave', null, null, $constraintCallback);

                continue;
            }

            $applyClause($relationName, 'whereHas', null, null, $constraintCallback);
        }
    }

    /**
     * Resolve HasMany count filter operator and value from the request.
     *
     * @return array{operator: string, n: int}|null
     */
    private static function resolveHasManyCountFilter(string $relationName, Request $request): ?array
    {
        $countOp = $request->get($relationName.'_count_op');
        $countVal = $request->get($relationName.'_count');

        if ($countOp === null || $countOp === '' || $countVal === null || $countVal === '' || ! is_numeric($countVal)) {
            return null;
        }

        $operator = match ($countOp) {
            'eq' => '=',
            'gt' => '>',
            'lt' => '<',
            default => null,
        };

        if ($operator === null) {
            return null;
        }

        return [
            'operator' => $operator,
            'n' => (int) $countVal,
        ];
    }

    /**
     * Resolve HasMany presence filter (has related / has none) from the request.
     */
    private static function resolveHasManyPresenceFilter(array $relation, Request $request): ?string
    {
        $relationName = $relation['name'];
        $searchableKeys = RelationSearchOperation::getSearchableKeys($relation['model']);

        foreach ($searchableKeys as $keyInfo) {
            $filterKey = $relationName.'_'.$keyInfo['field'];

            if ($request->get($filterKey.'_not_empty') === '1') {
                return 'not_empty';
            }

            if ($request->get($filterKey.'_empty') === '1') {
                return 'empty';
            }
        }

        return null;
    }

    /**
     * Build a single whereHas callback combining search identity + relation field filters.
     */
    private static function buildHasManyRelationConstraintCallback(array $relation, Request $request): ?\Closure
    {
        $relationName = $relation['name'];
        $relatedModelClass = $relation['model'];
        $relatedModel = new $relatedModelClass;
        $relatedTable = $relatedModel->getTable();

        $searchableKeys = RelationSearchOperation::getSearchableKeys($relatedModelClass);
        $filterableFields = RelationSearchOperation::getRelationFilterableFields(
            $relatedModelClass,
            $relation['foreign_key'] ?? null
        );

        $hasSearch = false;
        foreach ($searchableKeys as $keyInfo) {
            $filterKey = $relationName.'_'.$keyInfo['field'];
            if ($request->filled($filterKey)) {
                $hasSearch = true;
                break;
            }
        }

        $hasFields = self::hasHasManyRelationFieldFiltersForRelation(
            $relationName,
            $filterableFields,
            $request
        );

        if (! $hasSearch && ! $hasFields) {
            return null;
        }

        return function ($query) use (
            $searchableKeys,
            $relationName,
            $relatedModelClass,
            $filterableFields,
            $relatedTable,
            $request
        ) {
            foreach ($searchableKeys as $keyInfo) {
                $filterKey = $relationName.'_'.$keyInfo['field'];
                $filterValue = $request->get($filterKey);

                if ($filterValue === null || $filterValue === '') {
                    continue;
                }

                $searchFieldName = $keyInfo['field'];
                if ($searchFieldName === 'id') {
                    $relatedModelInstance = new $relatedModelClass;
                    $searchFieldName = $relatedModelInstance->getKeyName();
                }

                $query->where($searchFieldName, '=', $filterValue);
            }

            foreach ($filterableFields as $fieldInfo) {
                $field = $fieldInfo['field'];
                $paramBase = RelationSearchOperation::relationFieldParamPrefix(
                    $relationName,
                    $field
                );
                $fieldType = $fieldInfo['type'] ?? 'text';

                $value = $request->get($paramBase);
                if ($value !== null && $value !== '') {
                    self::applyRelationFieldValueCondition($query, $field, $value, $relatedTable, $fieldType);
                }

                if ($request->get($paramBase.'_not_empty') === '1') {
                    $query->whereNotNull($field);
                    $columnType = SchemaCache::getColumnType($relatedTable, $field);
                    if (in_array($columnType, ['string', 'varchar', 'text'], true)) {
                        $query->where($field, '!=', '');
                    }
                }

                if ($request->get($paramBase.'_empty') === '1') {
                    $query->where(function ($q) use ($field, $relatedTable) {
                        $columnType = SchemaCache::getColumnType($relatedTable, $field);
                        if (in_array($columnType, ['string', 'varchar', 'text'], true)) {
                            $q->whereNull($field)->orWhere($field, '');
                        } else {
                            $q->whereNull($field);
                        }
                    });
                }

                $fromValue = $request->get($paramBase.'_from');
                $toValue = $request->get($paramBase.'_to');
                if ($fromValue) {
                    $query->whereDate($field, '>=', $fromValue);
                }
                if ($toValue) {
                    $query->whereDate($field, '<=', $toValue);
                }
            }
        };
    }

    /**
     * Check whether a request key is handled by unified relation filtering.
     */
    private static function isHasManyUnifiedParam(string $key, Model $model): bool
    {
        foreach (self::getHasManyRelations($model) as $relation) {
            $relationName = $relation['name'];

            if ($key === $relationName.'_count_op' || $key === $relationName.'_count') {
                return true;
            }

            if (str_starts_with($key, $relationName.'_rel_')) {
                return true;
            }

            $searchableKeys = RelationSearchOperation::getSearchableKeys($relation['model']);
            foreach ($searchableKeys as $keyInfo) {
                $filterKey = $relationName.'_'.$keyInfo['field'];
                if ($key === $filterKey || $key === $filterKey.'_not_empty' || $key === $filterKey.'_empty') {
                    return true;
                }
            }
        }

        foreach (FilterHelper::getSingularRelations($model) as $relation) {
            if (str_starts_with($key, $relation['name'].'_rel_')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply BelongsTo / HasOne relation field filters (Campi tab) to CRUD clauses.
     */
    private static function applyUnifiedSingularRelationFieldFilters(Model $model): void
    {
        self::applyUnifiedSingularRelationFieldFilterClauses($model, function (string $relationName, ?\Closure $constraintCallback): void {
            if ($constraintCallback) {
                CRUD::addClause('whereHas', $relationName, $constraintCallback);
            }
        });
    }

    /**
     * Apply BelongsTo / HasOne relation field filters to a query builder (CSV export).
     */
    private static function applyUnifiedSingularRelationFieldFiltersToQuery($query, Model $model): void
    {
        self::applyUnifiedSingularRelationFieldFilterClauses($model, function (string $relationName, ?\Closure $constraintCallback) use ($query): void {
            if ($constraintCallback) {
                $query->whereHas($relationName, $constraintCallback);
            }
        });
    }

    /**
     * Build and apply field-only constraints for BelongsTo / HasOne relations.
     */
    private static function applyUnifiedSingularRelationFieldFilterClauses(Model $model, callable $applyClause): void
    {
        $request = request();

        foreach (FilterHelper::attachRelationFilterableFields(FilterHelper::getSingularRelations($model)) as $relation) {
            $constraintCallback = self::buildRelationFieldConstraintCallback($relation, $request);

            if ($constraintCallback !== null) {
                $applyClause($relation['name'], $constraintCallback);
            }
        }
    }

    /**
     * Build a whereHas callback for relation field filters (without identity search keys).
     */
    private static function buildRelationFieldConstraintCallback(array $relation, Request $request): ?\Closure
    {
        $relatedModelClass = $relation['model'];
        $relatedModel = new $relatedModelClass;
        $relatedTable = $relatedModel->getTable();
        $filterableFields = RelationSearchOperation::getRelationFilterableFields(
            $relatedModelClass,
            $relation['foreign_key'] ?? null
        );

        if (! self::hasHasManyRelationFieldFiltersForRelation($relation['name'], $filterableFields, $request)) {
            return null;
        }

        return function ($query) use ($filterableFields, $relation, $relatedTable, $request) {
            foreach ($filterableFields as $fieldInfo) {
                $field = $fieldInfo['field'];
                $paramBase = RelationSearchOperation::relationFieldParamPrefix(
                    $relation['name'],
                    $field
                );
                $fieldType = $fieldInfo['type'] ?? 'text';

                $value = $request->get($paramBase);
                if ($value !== null && $value !== '') {
                    self::applyRelationFieldValueCondition($query, $field, $value, $relatedTable, $fieldType);
                }

                if ($request->get($paramBase.'_not_empty') === '1') {
                    $query->whereNotNull($field);
                    $columnType = SchemaCache::getColumnType($relatedTable, $field);
                    if (in_array($columnType, ['string', 'varchar', 'text'], true)) {
                        $query->where($field, '!=', '');
                    }
                }

                if ($request->get($paramBase.'_empty') === '1') {
                    $query->where(function ($q) use ($field, $relatedTable) {
                        $columnType = SchemaCache::getColumnType($relatedTable, $field);
                        if (in_array($columnType, ['string', 'varchar', 'text'], true)) {
                            $q->whereNull($field)->orWhere($field, '');
                        } else {
                            $q->whereNull($field);
                        }
                    });
                }

                $fromValue = $request->get($paramBase.'_from');
                $toValue = $request->get($paramBase.'_to');
                if ($fromValue) {
                    $query->whereDate($field, '>=', $fromValue);
                }
                if ($toValue) {
                    $query->whereDate($field, '<=', $toValue);
                }
            }
        };
    }

    /**
     * Check if a field name is a hasMany relation filter
     */
    private static function isHasManyRelationFilter($fieldName, $model): bool
    {
        $hasManyRelations = self::getHasManyRelations($model);
        foreach ($hasManyRelations as $relation) {
            $relationName = $relation['name'];
            $searchableKeys = RelationSearchOperation::getSearchableKeys(
                $relation['model']
            );
            foreach ($searchableKeys as $keyInfo) {
                $filterKey = $relationName.'_'.$keyInfo['field'];
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
     * Apply a scalar filter on related model columns (e.g. Tiraggio -> user.banca_abi).
     */
    private static function applyRelationColumnFilterToQuery($query, Model $model, string $key, $value): bool
    {
        foreach (self::getFilterableRelationsForModel($model) as $relationName => $relatedModel) {
            $relatedTable = $relatedModel->getTable();
            if (! SchemaCache::hasColumn($relatedTable, $key)) {
                continue;
            }

            $query->whereHas($relationName, function ($q) use ($key, $value) {
                $q->where($key, '=', $value);
            });

            return true;
        }

        return false;
    }

    /**
     * Apply a scalar filter on related model columns to CRUD query clauses.
     */
    private static function applyRelationColumnFilter(Model $model, string $key, $value): bool
    {
        foreach (self::getFilterableRelationsForModel($model) as $relationName => $relatedModel) {
            $relatedTable = $relatedModel->getTable();
            if (! SchemaCache::hasColumn($relatedTable, $key)) {
                continue;
            }

            CRUD::addClause('whereHas', $relationName, function ($q) use ($key, $value) {
                $q->where($key, '=', $value);
            });

            return true;
        }

        return false;
    }

    /**
     * Collect simple relations usable for implicit related-column filtering.
     *
     * @return array<string, Model>
     */
    private static function getFilterableRelationsForModel(Model $model): array
    {
        $relations = [];
        $reflectionClass = new \ReflectionClass($model);

        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (
                $method->class !== get_class($model) ||
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
                $relation = $method->invoke($model);
            } catch (\Throwable $e) {
                continue;
            }

            if (! $relation instanceof Relation) {
                continue;
            }

            // Keep only relations that can be filtered safely with whereHas.
            if (
                $relation instanceof BelongsTo ||
                $relation instanceof HasOne ||
                $relation instanceof HasMany
            ) {
                $relations[$method->getName()] = $relation->getRelated();
            }
        }

        return $relations;
    }

    /**
     * Handle not_empty filter for hasMany relations
     */
    private static function handleHasManyNotEmptyFilter($fieldName, $value, $model)
    {
        if ($value !== '1') {
            return;
        }

        // Extract relation name from fieldName
        // Could be "tiraggi_id" (relation_field) or "tiraggi" (just relation)
        $hasManyRelations = self::getHasManyRelations($model);
        foreach ($hasManyRelations as $relation) {
            $relationName = $relation['name'];

            // Check if fieldName matches relation name directly
            if ($fieldName === $relationName) {
                CRUD::addClause('whereHas', $relationName);

                return;
            }

            // Check if fieldName matches relation_field pattern
            $searchableKeys = RelationSearchOperation::getSearchableKeys(
                $relation['model']
            );
            foreach ($searchableKeys as $keyInfo) {
                $filterKey = $relationName.'_'.$keyInfo['field'];
                if ($fieldName === $filterKey) {
                    // Use whereHas to filter records that have the relation
                    CRUD::addClause('whereHas', $relationName);

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
        if ($value !== '1') {
            return;
        }

        // Extract relation name from fieldName
        // Could be "tiraggi_id" (relation_field) or "tiraggi" (just relation)
        $hasManyRelations = self::getHasManyRelations($model);
        foreach ($hasManyRelations as $relation) {
            $relationName = $relation['name'];

            // Check if fieldName matches relation name directly
            if ($fieldName === $relationName) {
                CRUD::addClause('whereDoesntHave', $relationName);

                return;
            }

            // Check if fieldName matches relation_field pattern
            $searchableKeys = RelationSearchOperation::getSearchableKeys(
                $relation['model']
            );
            foreach ($searchableKeys as $keyInfo) {
                $filterKey = $relationName.'_'.$keyInfo['field'];
                if ($fieldName === $filterKey) {
                    // Use whereDoesntHave to filter records that don't have the relation
                    CRUD::addClause('whereDoesntHave', $relationName);

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

                    $relations[] = [
                        'name' => $method->getName(),
                        'type' => 'HasMany',
                        'model' => $relatedModel,
                        'foreign_key' => $foreignKey,
                        'local_key' => $localKey,
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
     * Determine whether any advanced relation field filter is set for a relation.
     */
    private static function hasHasManyRelationFieldFiltersForRelation(
        string $relationName,
        array $filterableFields,
        Request $request
    ): bool {
        foreach ($filterableFields as $fieldInfo) {
            $paramBase = RelationSearchOperation::relationFieldParamPrefix(
                $relationName,
                $fieldInfo['field']
            );

            $value = $request->get($paramBase);
            if ($value !== null && $value !== '') {
                return true;
            }

            if (
                $request->get($paramBase.'_not_empty') === '1' ||
                $request->get($paramBase.'_empty') === '1' ||
                $request->filled($paramBase.'_from') ||
                $request->filled($paramBase.'_to')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply a single value condition on a related model field inside whereHas.
     *
     * @param  Builder  $query
     */
    private static function applyRelationFieldValueCondition(
        $query,
        string $field,
        mixed $value,
        string $relatedTable,
        string $fieldType
    ): void {
        if (FieldConfigHandler::isSelectField($field, $relatedTable) || $fieldType === 'select') {
            $query->where($field, '=', $value);

            return;
        }

        if ($fieldType === 'boolean') {
            $query->where($field, '=', (int) $value);

            return;
        }

        $columnType = SchemaCache::getColumnType($relatedTable, $field);

        if (in_array($columnType, ['date', 'datetime', 'timestamp'], true) || $fieldType === 'date') {
            $query->whereDate($field, '=', $value);

            return;
        }

        if (
            in_array($columnType, ['integer', 'bigint', 'smallint', 'tinyint', 'decimal', 'float', 'double'], true) ||
            $fieldType === 'number'
        ) {
            $query->where($field, '=', $value);

            return;
        }

        $query->where($field, 'LIKE', '%'.$value.'%');
    }

    /**
     * Parse a HasMany relation field filter parameter into relation metadata.
     *
     * @return array{relation_name: string, field: string, model: class-string}|null
     */
    private static function parseHasManyRelationFieldParam(string $paramKey, Model $model): ?array
    {
        foreach (self::getHasManyRelations($model) as $relation) {
            $prefix = $relation['name'].'_rel_';
            if (! str_starts_with($paramKey, $prefix)) {
                continue;
            }

            $fieldName = substr($paramKey, strlen($prefix));
            if ($fieldName === '') {
                continue;
            }

            $relatedTable = (new $relation['model'])->getTable();
            if (! SchemaCache::hasColumn($relatedTable, $fieldName)) {
                continue;
            }

            return [
                'relation_name' => $relation['name'],
                'field' => $fieldName,
                'model' => $relation['model'],
            ];
        }

        return null;
    }

    /**
     * Check whether a request key belongs to a HasMany relation field filter.
     */
    private static function isHasManyRelationFieldParam(string $paramKey, Model $model): bool
    {
        return self::parseHasManyRelationFieldParam($paramKey, $model) !== null;
    }
}
