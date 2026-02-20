<?php

namespace App\Http\Controllers\Admin\Helper;

use ReflectionClass;
use ReflectionMethod;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function Laravel\Prompts\error;
use App\Http\Controllers\Controller;
use Backpack\CRUD\app\Library\Widget;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Admin\Helper\Core\MediaHandler;
use App\Http\Controllers\Admin\Helper\Core\FilterHandler;
use App\Http\Controllers\Admin\Helper\Core\RelationHandler;
use App\Http\Controllers\Admin\Helper\Core\FieldTypeHandler;
use App\Http\Controllers\Admin\Helper\Core\SelectHandler;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Helper class that provides methods to configure Backpack CRUD fields
 * based on model properties and relationships.
 *
 * The class is structured with logic divided into specialized components
 * for better maintainability and readability.
 */
class HelperBackend extends Controller
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
	 * @param \Illuminate\Database\Eloquent\Model $model Model to generate fields for
	 */
	public static function setFields(Model $model)
	{
		$request = request();
		// Get model table name
		$table = $model->getTable();
		// Get all table columns
		$columns = Schema::getColumnListing($table);
		// Get all public model methods
		$reflector = new ReflectionClass($model);
		// Filter methods to only those without parameters
		$methods = array_filter($reflector->getMethods(ReflectionMethod::IS_PUBLIC), function ($method) use ($model) {
			return $method->class == get_class($model) && $method->getNumberOfParameters() == 0;
		});

		$queryParams = $request->except("page");

		// Add informative descriptions to tabs for media
		MediaHandler::addTabDescriptions($table);

		// For users table, we need to handle password separately to place it after email
		$passwordColumn = null;
		if ($table == "users") {
			$passwordColumn = array_filter($columns, function ($col) {
				return str_contains($col, "password");
			});
			$passwordColumn = !empty($passwordColumn) ? array_values($passwordColumn)[0] : null;
		}

		// Iterate through columns and create a field for each
		foreach ($columns as $column) {
			// Skip id, created_at and updated_at columns
			if (in_array($column, ["id", "created_at", "updated_at"])) {
				continue;
			} elseif ($column == "email_verified_at") {
				$field = CRUD::field($column);
				$field->type("datetime");
				$field->default(now());
				$field->attributes(["readonly" => "readonly"]);
				$field->tab("Avanzati");
				$field->tab("Avanzati");
				continue;
			}
			// Skip password for users table (will be added after email)
			elseif ($table == "users" && str_contains($column, "password")) {
				continue;
			}

			// Get column type
			$columnType = DB::getSchemaBuilder()->getColumnType($table, $column);
			// Determine field type based on column type
			$fieldType = FieldTypeHandler::getFieldType($columnType);

			// Skip uncommon column types
			if ($fieldType == "break") {
				continue;
			}

			// Create new field
			$field = CRUD::field($column);

			// Handle specific field types
			$fieldType = FieldTypeHandler::handleSpecificType($field, $fieldType, $columnType, $column, $table);
			$field->type($fieldType);

			// Assign correct tab for media fields
			MediaHandler::assignMediaFieldTab($column, $table, $field);

			// Assign tab based on field type
			// Advanced fields (password, email_verified_at, token fields) go to "Avanzati" tab
			if (str_contains($column, "password")) {
				$field->tab("Avanzati");
			}
			// Token fields for users table go to "Avanzati" tab
			elseif ($table == "users" && in_array($column, ["token", "token_verified", "token_expire", "remember_token"])) {
				$field->tab("Avanzati");
			}
			// Relation fields go to "Relazioni" tab
			elseif (str_ends_with($column, "_id") && !str_contains($column, "transaction")) {
				$field->tab("Relazioni");
			}
			// Other fields go to "Dati" tab (main tab), except for media table
			elseif ($table != "media") {
				$field->tab("Dati");
			}

			if (isset($queryParams[$column])) {
				$field->value($queryParams[$column]);
			}

			// For users table, add password field right after email field
			if ($table == "users" && $column == "email" && $passwordColumn) {
				$passwordColumnType = DB::getSchemaBuilder()->getColumnType($table, $passwordColumn);
				$passwordFieldType = FieldTypeHandler::getFieldType($passwordColumnType);
				$passwordField = CRUD::field($passwordColumn);
				$passwordFieldType = FieldTypeHandler::handleSpecificType(
					$passwordField,
					$passwordFieldType,
					$passwordColumnType,
					$passwordColumn,
					$table
				);
				$passwordField->type($passwordFieldType);
				$passwordField->tab("Dati");
				$passwordField->wrapper(["class" => "form-group col-md-6"]);

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

			if (
				in_array($methodName, [
					"sendEmailVerificationNotification",
					"getDisplayAttribute",
					"getRelationDisplayAttribute",
				])
			) {
				continue; // skip this iteration
			}

			// Call method and get result
			$result = $model->$methodName();
			// If result is a BelongsToMany or BelongsTo relation, create a relational field
			if ($result instanceof BelongsToMany || $result instanceof BelongsTo) {
				RelationHandler::createSelectFieldsForRelation($methodName, $result, $table);
			} elseif ($result instanceof HasMany) {
				RelationHandler::createHasManyRelationList($methodName, $result, $table);
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
	 * @param Model $model Model to set fields for
	 */
	public static function setFieldsView(Model $model)
	{
		FilterHandler::applyFilters($model);
		// Get table name and columns
		$table = $model->getTable();
		$columns = Schema::getColumnListing($table);

		// Get public methods of the model
		$methods = collect((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC))->filter(function (
			$method
		) use ($model) {
			return $method->class == get_class($model) && $method->getNumberOfParameters() == 0;
		});

		// Iterate through each table column
		foreach ($columns as $column) {
			$projectBaseUrl = config("app.url") . "/admin";
			// Get column type in database
			$columnType = DB::getSchemaBuilder()->getColumnType($table, $column);

			// Determine field type to create
			$fieldType = FieldTypeHandler::getFieldType($columnType);

			if ($column == "order") {
				FilterHandler::configureDragSortButton();
			}

			// Display small image or media player instead of link for media files
			if (in_array($column, ["image_path", "mp4_path", "webm_path", "ogv_path", "ogg_path", "mp3_path"])) {
				MediaHandler::configureMediaColumnView($column);
			}

			// Handle normal relations (automatically detected from database schema)
			elseif (self::isForeignKeyColumn($column, $table) && !str_contains($column, "transaction")) {
				$relationName = self::getRelationNameFromColumn($column, $table);
				RelationHandler::configureRelationColumnView($column, $relationName, $projectBaseUrl);
			} else {
				// Create normal field for column
				$field = CRUD::column($column)->type($fieldType);
				if ($field) {
					FieldTypeHandler::handleSpecificTypeView($field, $columnType, $column);
				}
			}
		}

		// Process relations
		$methods->each(function ($method) use ($model, $projectBaseUrl) {
			$methodName = $method->name;
			if (
				in_array($methodName, [
					"sendEmailVerificationNotification",
					"getDisplayAttribute",
					"getRelationDisplayAttribute",
				])
			) {
				return; // skip this iteration
			}
			if (method_exists($model, $methodName)) {
				$result = $model->$methodName();
				if ($result instanceof BelongsToMany || $result instanceof HasMany) {
					RelationHandler::createRelationalFieldsView($methodName, $result, $projectBaseUrl);
				}
			}
		});

		CRUD::removeButton("show", "line");
		CRUD::addButtonFromView("line", "duplicate", "duplicate", "view");
		CRUD::addButtonFromView("top", "bulk_operations", "bulk_operations", "beginning");
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
	 * @param Model $model Model to set columns for
	 */
	public static function setFieldsShow(Model $model)
	{
		// Get table name and columns
		$table = $model->getTable();
		$columns = Schema::getColumnListing($table);

		// Get public methods of the model
		$methods = collect((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC))->filter(function (
			$method
		) use ($model) {
			return $method->class == get_class($model) && $method->getNumberOfParameters() == 0;
		});

		// Iterate through each table column
		foreach ($columns as $column) {
			$projectBaseUrl = config("app.url") . "/admin";
			// Get column type in database
			$columnType = DB::getSchemaBuilder()->getColumnType($table, $column);

			// Determine field type to create
			$fieldType = FieldTypeHandler::getFieldType($columnType);

			// Display small image or media player instead of link for media files
			if (in_array($column, ["image_path", "mp4_path", "webm_path", "ogv_path", "ogg_path", "mp3_path"])) {
				MediaHandler::configureMediaColumnView($column);
			}

			// Handle normal relations (automatically detected from database schema)
			elseif (self::isForeignKeyColumn($column, $table) && !str_contains($column, "transaction")) {
				$relationName = self::getRelationNameFromColumn($column, $table);
				RelationHandler::configureRelationColumnView($column, $relationName, $projectBaseUrl);
			} else {
				// Create normal field for column
				$field = CRUD::column($column)->type($fieldType);
				if ($field) {
					FieldTypeHandler::handleSpecificTypeView($field, $columnType, $column);
				}
			}
		}

		// Process relations
		$methods->each(function ($method) use ($model, $projectBaseUrl) {
			$methodName = $method->name;
			if (
				in_array($methodName, [
					"sendEmailVerificationNotification",
					"getDisplayAttribute",
					"getRelationDisplayAttribute",
				])
			) {
				return; // skip this iteration
			}
			if (method_exists($model, $methodName)) {
				$result = $model->$methodName();
				if ($result instanceof BelongsToMany || $result instanceof HasMany) {
					RelationHandler::createRelationalFieldsView($methodName, $result, $projectBaseUrl);
				}
			}
		});
	}

	/**
	 * Handles drag-and-drop sorting of items
	 *
	 * @param Request $request HTTP request
	 * @param string $modelName Model name to sort
	 * @return \Illuminate\Http\RedirectResponse
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
					REFERENCED_TABLE_NAME
				FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
				WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = ?
				AND REFERENCED_TABLE_NAME IS NOT NULL
			",
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
	 * @param string $columnName Column name
	 * @param string $tableName Table name
	 * @return bool
	 */
	private static function isForeignKeyColumn(string $columnName, string $tableName): bool
	{
		$foreignKeys = self::getForeignKeysForTable($tableName);
		return isset($foreignKeys[$columnName]);
	}

	/**
	 * Find Model class for a given table name by scanning App\Models
	 *
	 * @param string $tableName Table name
	 * @return string|null Model class name or null if not found
	 */
	private static function findModelForTable(string $tableName): ?string
	{
		// Common Model namespace
		$namespace = "App\\Models\\";

		// Get all PHP files in App\Models directory
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
				// Skip models that can't be instantiated
				continue;
			}
		}

		return null;
	}

	/**
	 * Get relation name from foreign key column (automatically derives from Model class name)
	 *
	 * @param string $columnName Column name
	 * @param string $tableName Table name (to lookup FK)
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
				// Get class basename (e.g., "App\Models\Filiale" -> "Filiale")
				$modelName = class_basename($modelClass);

				// Convert to camelCase for relation name (e.g., "Filiale" -> "filiale", "BackpackRole" -> "backpackRole")
				return \Illuminate\Support\Str::camel($modelName);
			}

			// Fallback: use Laravel's singular if Model not found
			return \Illuminate\Support\Str::singular($referencedTable);
		}

		return $columnName;
	}
}
