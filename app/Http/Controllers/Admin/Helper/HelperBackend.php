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
			$fieldType = FieldTypeHandler::handleSpecificType($field, $fieldType, $column, $table);
			$field->type($fieldType);

			// Assign correct tab for media fields
			MediaHandler::assignMediaFieldTab($column, $table, $field);

			if (isset($queryParams[$column])) {
				$field->value($queryParams[$column]);
			}
		}

		// Add media previews in edit mode
		MediaHandler::addMediaPreviews($model);

		// Iterate through methods and create a field for each
		foreach ($methods as $method) {
			// Get method name
			$methodName = $method->name;

			if (in_array($methodName, ["sendEmailVerificationNotification"])) {
				return; // skip this iteration
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

			// Handle normal relations
			elseif (str_ends_with($column, "_id") && !str_contains($column, "transaction")) {
				$relationName = str_replace("_id", "", $column);
				RelationHandler::configureRelationColumnView($column, $relationName, $projectBaseUrl);
			} else {
				// Create normal field for column
				$field = CRUD::column($column)->type($fieldType);
				if ($field) {
					FieldTypeHandler::handleSpecificTypeView($field, $column);
				}
			}
		}

		// Process relations
		$methods->each(function ($method) use ($model, $projectBaseUrl) {
			$methodName = $method->name;
			if (in_array($methodName, ["sendEmailVerificationNotification"])) {
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
}
