<?php

namespace App\Support\Backpack;

use App\Support\Backpack\Handler\FieldTypeHandler;
use App\Support\Backpack\Handler\FilterHandler;
use App\Support\Backpack\Handler\MediaHandler;
use App\Support\Backpack\Handler\RelationHandler;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

/**
 * Helper class that provides methods to configure Backpack CRUD fields
 * based on model properties and relationships.
 *
 * The class is structured with logic divided into specialized components
 * for better maintainability and readability.
 */
class BackpackHelper
{
    /**
     * Automatically generates CRUD fields for a model.
     *
     * Iterates through all columns and methods of the model,
     * and generates appropriate fields in the CRUD panel.
     *
     * Logic is delegated to specialized classes:
     * - FieldTypeHandler: determines field type
     * - MediaHandler: handles media fields
     * - RelationHandler: handles relational fields
     *
     * @param  Model  $model  Model to generate fields for
     */
    public static function setFields(Model $model)
    {
        // Global edit subheading: "Modifica: <display>"
        if (CRUD::getOperation() === 'update') {
            $entry = CRUD::getCurrentEntry();
            if ($entry instanceof Model) {
                $label = self::resolveEntryDisplayLabel($entry);
                CRUD::setSubheading('<span class="crud-subheading-prefix">Modifica:</span> <strong>'.e($label).'</strong>');
            }
        }

        $request = request();
        // Get model table name
        $table = $model->getTable();
        // Get all table columns
        $columns = SchemaCache::getColumnListing($table);
        // Get all public model methods
        $reflector = new ReflectionClass($model);
        // Filter methods to only those without parameters
        $methods = array_filter($reflector->getMethods(ReflectionMethod::IS_PUBLIC), function ($method) use ($model) {
            return $method->class == get_class($model) && ModelRelations::isRelationMethod($method);
        });

        $queryParams = $request->except('page');

        // Get the model's primary key name to exclude it from "Relazioni" tab
        $primaryKey = $model->getKeyName();

        // Get fillable fields to determine which fields are editable
        $fillable = $model->getFillable();

        // Determine which tabs will have content
        $hasAdvancedFields = false;
        $hasRelationFields = false;

        // Collect FK columns that are managed by BelongsTo relations (to skip them in column loop)
        $belongsToForeignKeys = [];

        // Pre-scan methods to find BelongsTo relations and their foreign keys
        foreach ($methods as $method) {
            $methodName = $method->name;
            try {
                $result = $model->$methodName();
                if ($result instanceof BelongsTo) {
                    $belongsToForeignKeys[] = $result->getForeignKeyName();
                    $hasRelationFields = true;
                } elseif ($result instanceof BelongsToMany || $result instanceof HasMany || $result instanceof HasOne) {
                    $hasRelationFields = true;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Pre-scan columns to determine which tabs are needed
        foreach ($columns as $column) {
            if (in_array($column, ['id', 'created_at', 'updated_at'])) {
                continue;
            }
            // Check for advanced fields
            if (str_contains($column, 'password') || $column == 'email_verified_at') {
                $hasAdvancedFields = true;
            }
            if ($table == 'users' && in_array($column, ['token', 'token_verified', 'token_expire', 'remember_token'])) {
                $hasAdvancedFields = true;
            }
            // Check for relation fields (FK columns that are NOT the primary key and NOT managed by BelongsTo)
            if (str_ends_with($column, '_id') && ! str_contains($column, 'transaction') && $column !== $primaryKey && ! in_array($column, $belongsToForeignKeys)) {
                $hasRelationFields = true;
            }
        }

        // Add tab order placeholders only for tabs that will have content
        if ($table != 'media') {
            // Dati tab is always present
            CRUD::addField([
                'name' => '_tab_order_dati',
                'type' => 'custom_html',
                'value' => '',
                'wrapper' => ['class' => 'd-none'],
                'tab' => 'Dati',
            ]);
            // Avanzati tab only if there are advanced fields
            if ($hasAdvancedFields) {
                CRUD::addField([
                    'name' => '_tab_order_avanzati',
                    'type' => 'custom_html',
                    'value' => '',
                    'wrapper' => ['class' => 'd-none'],
                    'tab' => 'Avanzati',
                ]);
            }
            // Relazioni tab only if there are relation fields
            if ($hasRelationFields) {
                CRUD::addField([
                    'name' => '_tab_order_relazioni',
                    'type' => 'custom_html',
                    'value' => '',
                    'wrapper' => ['class' => 'd-none'],
                    'tab' => 'Relazioni',
                ]);
            }
        }

        // Add informative descriptions to tabs for media
        MediaHandler::addTabDescriptions($table);

        // For users table, we need to handle password separately to place it after email
        $passwordColumn = null;
        if ($table == 'users') {
            $passwordColumn = array_filter($columns, function ($col) {
                return str_contains($col, 'password');
            });
            $passwordColumn = ! empty($passwordColumn) ? array_values($passwordColumn)[0] : null;
        }

        // Iterate through columns and create a field for each
        foreach ($columns as $column) {
            // Skip id, created_at and updated_at columns
            if (in_array($column, ['id', 'created_at', 'updated_at'])) {
                continue;
            }
            // Skip FK columns that are managed by BelongsTo relations (RelationHandler will handle them)
            if (in_array($column, $belongsToForeignKeys)) {
                continue;
            }
            if ($column == 'email_verified_at') {
                $field = CRUD::field($column);
                $field->type('datetime');
                $field->default(now());
                $field->attributes(['disabled' => 'disabled']);
                $field->hint(trans('backpack::crud.field_not_editable'));
                $field->tab('Avanzati');

                continue;
            }
            // Skip password for users table (will be added after email)
            elseif ($table == 'users' && str_contains($column, 'password')) {
                continue;
            }

            // Get column type
            $columnType = SchemaCache::getColumnType($table, $column);
            // Determine field type based on column type
            $fieldType = FieldTypeHandler::getFieldType($columnType, $table, $column);

            // Skip uncommon column types, but NOT the primary key
            if ($fieldType == 'break' && $column !== $primaryKey) {
                continue;
            }

            // For primary key with bigint type, use number field
            if ($column === $primaryKey && $fieldType == 'break') {
                $fieldType = 'number';
            }

            // Create new field
            $field = CRUD::field($column);

            // For primary key field, set a formatted label
            if ($column === $primaryKey) {
                $formattedLabel = ucwords(str_replace('_', ' ', $column));
                $field->label($formattedLabel);
            }

            // Disable field if not in model's $fillable (applies to all fields including PK)
            if (! empty($fillable) && ! in_array($column, $fillable)) {
                $field->attributes(['disabled' => 'disabled']);
                if ($column === $primaryKey) {
                    $field->hint(trans('backpack::crud.primary_key_not_editable'));
                } else {
                    $field->hint(trans('backpack::crud.field_not_editable'));
                }
            }

            // Handle specific field types
            $fieldType = FieldTypeHandler::handleSpecificType($field, $fieldType, $columnType, $column, $table);
            $field->type($fieldType);

            // Assign correct tab for media fields
            MediaHandler::assignMediaFieldTab($column, $table, $field);

            // Assign tab based on field type
            // Advanced fields (password, email_verified_at, token fields) go to "Avanzati" tab
            if (str_contains($column, 'password')) {
                $field->tab('Avanzati');
            }
            // Token fields for users table go to "Avanzati" tab
            elseif ($table == 'users' && in_array($column, ['token', 'token_verified', 'token_expire', 'remember_token'])) {
                $field->tab('Avanzati');
            }
            // Relation fields go to "Relazioni" tab, but NOT the primary key of the model itself
            elseif (str_ends_with($column, '_id') && ! str_contains($column, 'transaction') && $column !== $primaryKey) {
                $field->tab('Relazioni');
            }
            // Other fields (including primary key) go to "Dati" tab (main tab), except for media table
            elseif ($table != 'media') {
                $field->tab('Dati');
            }

            if (isset($queryParams[$column])) {
                $field->value($queryParams[$column]);
            }

            // For users table, add password field right after email field
            if ($table == 'users' && $column == 'email' && $passwordColumn) {
                $passwordColumnType = SchemaCache::getColumnType($table, $passwordColumn);
                $passwordFieldType = FieldTypeHandler::getFieldType($passwordColumnType, $table, $passwordColumn);
                $passwordField = CRUD::field($passwordColumn);
                $passwordFieldType = FieldTypeHandler::handleSpecificType(
                    $passwordField,
                    $passwordFieldType,
                    $passwordColumnType,
                    $passwordColumn,
                    $table
                );
                $passwordField->type($passwordFieldType);
                $passwordField->tab('Dati');
                $passwordField->wrapper(['class' => 'form-group col-md-6']);

                if (isset($queryParams[$passwordColumn])) {
                    $passwordField->value($queryParams[$passwordColumn]);
                }
            }
        }

        // Add media previews in edit mode
        MediaHandler::addMediaPreviews($model);

        // Iterate through methods and create a field for each
        foreach ($methods as $method) {
            // Get method name
            $methodName = $method->name;

            // Call method and get result
            $result = $model->$methodName();
            // If result is a BelongsToMany or BelongsTo relation, create a relational field
            if ($result instanceof BelongsToMany || $result instanceof BelongsTo) {
                RelationHandler::createSelectFieldsForRelation($methodName, $result, $table);
            } elseif ($result instanceof HasMany) {
                RelationHandler::createHasManyRelationList($methodName, $result, $table);
            } elseif ($result instanceof HasOne) {
                RelationHandler::createHasOneRelationField($methodName, $result, $table);
            }
        }
    }

    /**
     * Generates fields and columns for CRUD views.
     *
     * This method handles:
     * - Applying filters
     * - Creating a field for each column in the model's table
     * - Creating a column for each relation method in the model
     * - Properly configuring special views for media and relations
     *
     * @param  Model  $model  Model to set fields for
     */
    public static function setFieldsView(Model $model)
    {
        FilterHandler::applyFilters($model);

        // Get table name, columns and primary key
        $table = $model->getTable();
        $columns = SchemaCache::getColumnListing($table);
        $columns = self::orderColumnsByFillable($model, $columns);
        $primaryKey = $model->getKeyName();
        $isListOperation = CRUD::getOperation() === 'list';
        $firstListColumnLinked = false;
        $modelCrudUrlBase = config('app.url').'/admin/'.Str::kebab(class_basename($model));
        $projectBaseUrl = config('app.url').'/admin';

        // Display column is not added to list/show; getDisplayAttribute/getRelationDisplayAttribute
        // are used only in relation context (RelationHandler, relation columns, etc.).

        // Get public methods of the model
        $methods = collect((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC))->filter(function (
            $method
        ) use ($model) {
            return $method->class == get_class($model) && ModelRelations::isRelationMethod($method);
        });

        // Iterate through each table column
        foreach ($columns as $column) {
            // Get column type in database
            $columnType = SchemaCache::getColumnType($table, $column);

            // Determine field type to create
            $fieldType = FieldTypeHandler::getFieldType($columnType);

            if ($column == 'order') {
                FilterHandler::configureDragSortButton();
            }

            // Display small image or media player instead of link for media files
            if (in_array($column, ['image_path', 'logo_path', 'mp4_path', 'webm_path', 'ogv_path', 'ogg_path', 'mp3_path'])) {
                MediaHandler::configureMediaColumnView($column);
            }

            // Display color swatch + hex for columns whose name contains "color"
            elseif (str_contains($column, 'color')) {
                self::configureColorColumnView($column);
            }

            // font_family columns: single dash for empty; render value with that font for preview
            elseif ($column === 'font_family') {
                CRUD::column($column)
                    ->label('Font')
                    ->type('custom_html')
                    ->value(function ($entry) {
                        $v = $entry->font_family ?? '';
                        if ($v === '') {
                            return '-';
                        }
                        $cssSafe = str_contains($v, ' ') ? "'".str_replace("'", "\\'", $v)."'" : $v;

                        return '<span style="font-family: '.e($cssSafe).', sans-serif;">'.e($v).'</span>';
                    });
            }

            // Array-cast attributes (json payloads): rendered as JSON text, the
            // textarea/select views cannot print an array
            elseif (self::hasArrayCast($model, $column) && ! ($isListOperation && ! $firstListColumnLinked)) {
                self::configureJsonColumnView($column);
            }
            // Handle normal relations (automatically detected from database schema)
            elseif (self::isForeignKeyColumn($column, $table) && ! str_contains($column, 'transaction')) {
                $relationName = self::getRelationNameFromColumn($column, $table);
                RelationHandler::configureRelationColumnView($column, $relationName, $projectBaseUrl);
            } else {
                // Create normal field for column
                if ($isListOperation && ! $firstListColumnLinked) {
                    $field = CRUD::column($column)
                        ->type('custom_html')
                        ->value(function ($entry) use ($column, $columnType, $modelCrudUrlBase) {
                            $href = $modelCrudUrlBase.'/'.$entry->getKey().'/edit';
                            $displayValue = FieldTypeHandler::formatListColumnValue(
                                $entry->$column ?? null,
                                $columnType,
                                $column
                            );

                            if ($displayValue === '') {
                                $displayValue = self::normalizeColumnValueForDisplay($entry->$column ?? null);
                            }

                            $label = self::renderDisplayValue($displayValue);

                            return '<a class="crud-first-col-link" href="'.e($href).'">'.$label.'</a>';
                        });
                    $firstListColumnLinked = true;
                } else {
                    $field = CRUD::column($column)->type($fieldType);
                    if ($field) {
                        FieldTypeHandler::handleSpecificTypeView($field, $columnType, $column);
                    }
                }

                if ($field) {
                    // For primary key, show full formatted name (e.g., "User Id" not "User")
                    if ($column === $primaryKey) {
                        $formattedLabel = ucwords(str_replace('_', ' ', $column));
                        $field->label($formattedLabel);
                    }
                }
            }
        }

        // Process relations
        $methods->each(function ($method) use ($model, $projectBaseUrl) {
            $methodName = $method->name;
            if (method_exists($model, $methodName)) {
                $result = $model->$methodName();
                if (
                    $result instanceof BelongsToMany ||
                    $result instanceof HasMany ||
                    $result instanceof HasManyThrough ||
                    $result instanceof HasOne
                ) {
                    RelationHandler::createRelationalFieldsView($methodName, $result, $projectBaseUrl);
                }
            }
        });

        CRUD::removeButton('show', 'line');

        // Reorder line buttons: update, duplicate, delete
        CRUD::removeButton('update');
        CRUD::removeButton('delete');
        CRUD::addButtonFromView('line', 'update', 'update', 'end');
        CRUD::addButtonFromView('line', 'duplicate', 'duplicate', 'end');
        CRUD::addButtonFromView('line', 'delete', 'delete', 'end');

        CRUD::setOperationSetting('bulkActions', false);
        CRUD::addButtonFromView('top', 'bulk_operations', 'bulk_operations', 'beginning');
    }

    /**
     * Order table columns by model fillable, then keep remaining schema columns.
     *
     * @param  array<int, string>  $schemaColumns
     * @return array<int, string>
     */
    private static function orderColumnsByFillable(Model $model, array $schemaColumns): array
    {
        $fillable = $model->getFillable();

        if (empty($fillable)) {
            return $schemaColumns;
        }

        $remainingColumns = array_flip($schemaColumns);
        $orderedColumns = [];

        foreach ($fillable as $column) {
            if (! isset($remainingColumns[$column])) {
                continue;
            }

            $orderedColumns[] = $column;
            unset($remainingColumns[$column]);
        }

        foreach ($schemaColumns as $column) {
            if (! isset($remainingColumns[$column])) {
                continue;
            }

            $orderedColumns[] = $column;
        }

        return $orderedColumns;
    }

    /**
     * Generates columns for CRUD Show operation.
     *
     * This method handles:
     * - Creating a column for each column in the model's table
     * - Creating a column for each relation method in the model
     * - Properly configuring special views for media and relations
     *
     * Similar to setFieldsView but optimized for Show operation (no filters, no buttons)
     *
     * @param  Model  $model  Model to set columns for
     */
    public static function setFieldsShow(Model $model)
    {
        // Get table name, columns and primary key
        $table = $model->getTable();
        $columns = SchemaCache::getColumnListing($table);
        $primaryKey = $model->getKeyName();

        // Display column is not added to show; used only in relation context.

        // Get public methods of the model
        $methods = collect((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC))->filter(function (
            $method
        ) use ($model) {
            return $method->class == get_class($model) && ModelRelations::isRelationMethod($method);
        });

        $projectBaseUrl = config('app.url').'/admin';

        // Iterate through each table column
        foreach ($columns as $column) {
            // Get column type in database
            $columnType = SchemaCache::getColumnType($table, $column);

            // Determine field type to create
            $fieldType = FieldTypeHandler::getFieldType($columnType);

            // Display small image or media player instead of link for media files
            if (in_array($column, ['image_path', 'logo_path', 'mp4_path', 'webm_path', 'ogv_path', 'ogg_path', 'mp3_path'])) {
                MediaHandler::configureMediaColumnView($column);
            } elseif (str_contains($column, 'color')) {
                self::configureColorColumnView($column);
            } elseif ($column === 'font_family') {
                CRUD::column($column)
                    ->label('Font')
                    ->type('custom_html')
                    ->value(function ($entry) {
                        $v = $entry->font_family ?? '';
                        if ($v === '') {
                            return '—';
                        }
                        $cssSafe = str_contains($v, ' ') ? "'".str_replace("'", "\\'", $v)."'" : $v;

                        return '<span style="font-family: '.e($cssSafe).', sans-serif;">'.e($v).'</span>';
                    });
            }
            // Array-cast attributes (json payloads): rendered as JSON text
            elseif (self::hasArrayCast($model, $column)) {
                self::configureJsonColumnView($column);
            }
            // Handle normal relations (automatically detected from database schema)
            elseif (self::isForeignKeyColumn($column, $table) && ! str_contains($column, 'transaction')) {
                $relationName = self::getRelationNameFromColumn($column, $table);
                RelationHandler::configureRelationColumnView($column, $relationName, $projectBaseUrl);
            } else {
                // Create normal field for column
                $field = CRUD::column($column)->type($fieldType);
                if ($field) {
                    // For primary key, show full formatted name (e.g., "User Id" not "User")
                    if ($column === $primaryKey) {
                        $formattedLabel = ucwords(str_replace('_', ' ', $column));
                        $field->label($formattedLabel);
                    }
                    FieldTypeHandler::handleSpecificTypeView($field, $columnType, $column);
                }
            }
        }

        // Process relations
        $methods->each(function ($method) use ($model, $projectBaseUrl) {
            $methodName = $method->name;
            if (method_exists($model, $methodName)) {
                $result = $model->$methodName();
                if (
                    $result instanceof BelongsToMany ||
                    $result instanceof HasMany ||
                    $result instanceof HasManyThrough ||
                    $result instanceof HasOne
                ) {
                    RelationHandler::createRelationalFieldsView($methodName, $result, $projectBaseUrl);
                }
            }
        });
    }

    /**
     * Convert a raw Eloquent column value to a plain string safe for renderDisplayValue().
     */
    public static function normalizeColumnValueForDisplay(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            return implode(', ', array_map(
                static fn (mixed $item): string => is_scalar($item) || $item === null
                    ? (string) $item
                    : json_encode($item, JSON_UNESCAPED_UNICODE),
                $value
            ));
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_object($value) && ! method_exists($value, '__toString')) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
        }

        return (string) $value;
    }

    /**
     * Render a value for list/relation display: hex color → swatch; image path → thumbnail; else escaped text.
     * Used for getDisplayAttribute() in list and in relation columns.
     *
     * @param  string|null  $value  Display value (e.g. from $entry->display)
     * @param  bool  $forRelation  When true, image is rendered without wrapper link and with logo-sized thumbnail (caller provides link to relation record).
     * @return string HTML (swatch, img, or escaped text)
     */
    public static function renderDisplayValue(?string $value, bool $forRelation = false): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $value = (string) $value;
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
            $hexEsc = e($value);

            return '<span style="display:inline-flex;align-items:center;gap:0.4rem;">
                <span style="display:inline-block;width:1.25rem;height:1.25rem;border-radius:4px;border:1px solid #ccc;background:'.$hexEsc.';flex-shrink:0;" title="'.$hexEsc.'"></span>
                <span>'.$hexEsc.'</span>
            </span>';
        }
        if (preg_match('/\.(gif|jpe?g|png|webp|ico)$/i', $value)) {
            $url = e(MediaHandler::resolveFileUrl($value));
            $imgStyle = $forRelation
                ? 'max-width:150px;max-height:40px;height:auto;border-radius:4px;vertical-align:middle;'
                : 'max-width:40px;max-height:40px;height:auto;border-radius:4px;vertical-align:middle;';

            if ($forRelation) {
                return '<img src="'.$url.'" alt="" style="'.$imgStyle.'" loading="lazy">';
            }

            return '<a href="'.$url.'" target="_blank" rel="noopener noreferrer"><img src="'.$url.'" alt="" style="'.$imgStyle.'" loading="lazy"></a>';
        }

        return e($value);
    }

    /**
     * Resolve a human-friendly label for an entry.
     * Priority: getRelationDisplayAttribute() → getDisplayAttribute() → title/name → primary key.
     */
    public static function resolveEntryDisplayLabel(Model $entry): string
    {
        // Highest priority for this project: activeFilterField (string|array) when available.
        // This yields combined labels like "19275 - Intesa Sanpaolo".
        try {
            $fields = [];
            if (property_exists($entry, 'activeFilterField')) {
                $v = $entry->activeFilterField;
                if (is_string($v) && trim($v) !== '') {
                    $fields = [trim($v)];
                } elseif (is_array($v)) {
                    $fields = array_values(array_filter(array_map(function ($f) {
                        return is_string($f) ? trim($f) : '';
                    }, $v), fn ($f) => $f !== ''));
                }
            } elseif (method_exists($entry, 'getActiveFilterField')) {
                $v = $entry->getActiveFilterField();
                if (is_string($v) && trim($v) !== '') {
                    $fields = [trim($v)];
                } elseif (is_array($v)) {
                    $fields = array_values(array_filter(array_map(function ($f) {
                        return is_string($f) ? trim($f) : '';
                    }, $v), fn ($f) => $f !== ''));
                }
            }

            if (! empty($fields)) {
                $resolved = [];
                foreach ($fields as $field) {
                    $val = $entry->getAttribute($field);
                    if ($val !== null && $val !== '') {
                        $resolved[] = (string) $val;
                    }
                }
                if (! empty($resolved)) {
                    return implode(' - ', $resolved);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        if (method_exists($entry, 'getRelationDisplayAttribute')) {
            try {
                $v = $entry->getRelationDisplayAttribute();
                if (is_string($v) && trim($v) !== '') {
                    return $v;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (method_exists($entry, 'getDisplayAttribute')) {
            try {
                $v = $entry->getDisplayAttribute();
                if (is_string($v) && trim($v) !== '') {
                    return $v;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        foreach (['title', 'name'] as $attr) {
            try {
                $v = $entry->getAttribute($attr);
                if (is_string($v) && trim($v) !== '') {
                    return $v;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return (string) $entry->getKey();
    }

    /**
     * Configure list/show column for color fields: display swatch + hex code.
     *
     * @param  string  $column  Column name (must contain "color", e.g. background_color)
     */
    /**
     * Whether the model casts the attribute to an array-like value (array,
     * json, object, collection, encrypted variants). The column type alone is
     * not enough: a JSON payload may live in a TEXT column, and SQLite reports
     * JSON columns as TEXT.
     */
    private static function hasArrayCast(Model $model, string $column): bool
    {
        return $model->hasCast($column, [
            'array', 'json', 'object', 'collection',
            'encrypted:array', 'encrypted:json', 'encrypted:object', 'encrypted:collection',
        ]);
    }

    /**
     * List/show column printing an array-cast attribute as escaped, pretty JSON.
     */
    private static function configureJsonColumnView(string $column): void
    {
        CRUD::column($column)
            ->type('custom_html')
            ->value(function ($entry) use ($column) {
                $value = $entry->{$column};

                if ($value === null || $value === []) {
                    return '-';
                }

                $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                return '<pre class="mb-0" style="white-space: pre-wrap;">'.e((string) $json).'</pre>';
            });
    }

    private static function configureColorColumnView(string $column): void
    {
        $label = ucwords(str_replace('_', ' ', $column));

        CRUD::column($column)
            ->label($label)
            ->type('custom_html')
            ->value(function ($entry) use ($column) {
                $hex = $entry->$column ?? '';
                if ($hex === '') {
                    return '—';
                }
                $hexEsc = e($hex);

                return '<span style="display:inline-flex;align-items:center;gap:0.4rem;">
                    <span style="display:inline-block;width:1.25rem;height:1.25rem;border-radius:4px;border:1px solid #ccc;background:'.$hexEsc.';flex-shrink:0;" title="'.$hexEsc.'"></span>
                    <span>'.$hexEsc.'</span>
                </span>';
            });
    }

    /**
     * Handles drag-and-drop sorting of items
     *
     * @param  Request  $request  HTTP request
     * @param  string  $modelName  Model name to sort
     * @return RedirectResponse
     */
    public function sort(Request $request, $modelName)
    {
        return FilterHandler::sort($request, $modelName);
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
					REFERENCED_TABLE_NAME
				FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
				WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = ?
				AND REFERENCED_TABLE_NAME IS NOT NULL
			',
                [$tableName]
            );

            // Build array: column_name => referenced_table
            $result = [];
            foreach ($foreignKeys as $fk) {
                $result[$fk->COLUMN_NAME] = $fk->REFERENCED_TABLE_NAME;
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
     * Check if a column is a foreign key (automatically detects from schema)
     *
     * @param  string  $columnName  Column name
     * @param  string  $tableName  Table name
     */
    private static function isForeignKeyColumn(string $columnName, string $tableName): bool
    {
        $foreignKeys = self::getForeignKeysForTable($tableName);

        return isset($foreignKeys[$columnName]);
    }

    /**
     * Find Model class for a given table name by scanning App\Models
     *
     * @param  string  $tableName  Table name
     * @return string|null Model class name or null if not found
     */
    private static function findModelForTable(string $tableName): ?string
    {
        // Common Model namespace
        $namespace = 'App\\Models\\';

        // Get all PHP files in App\Models directory
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
                // Skip models that can't be instantiated
                continue;
            }
        }

        return null;
    }

    /**
     * Get relation name from foreign key column (automatically derives from Model class name)
     *
     * @param  string  $columnName  Column name
     * @param  string  $tableName  Table name (to lookup FK)
     * @return string Relation name (camelCase from Model class)
     */
    private static function getRelationNameFromColumn(string $columnName, string $tableName): string
    {
        $foreignKeys = self::getForeignKeysForTable($tableName);

        if (isset($foreignKeys[$columnName])) {
            // Get referenced table name
            $referencedTable = $foreignKeys[$columnName];

            // Find the Model class for this table
            $modelClass = self::findModelForTable($referencedTable);

            if ($modelClass) {
                // Get class basename (e.g., "App\Models\Page" -> "Page")
                $modelName = class_basename($modelClass);

                // Convert to camelCase for relation name (e.g., "Page" -> "page", "BackpackRole" -> "backpackRole")
                return Str::camel($modelName);
            }

            // Fallback: use Laravel's singular if Model not found
            return Str::singular($referencedTable);
        }

        return $columnName;
    }
}
