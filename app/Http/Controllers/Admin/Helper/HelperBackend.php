<?php

namespace App\Http\Controllers\Admin\Helper;

use ReflectionClass;
use ReflectionMethod;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function Laravel\Prompts\error;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelperBackend extends Controller
{
	/**
	 * This method sets up an AJAX select field for a CRUD panel. The options are fetched via AJAX from the provided model.
	 *
	 * It takes in the name of the field, the fully qualified class name of the model, the field to use as the display value,
	 * an optional collection of models to use as options, a flag to determine if the field should be empty, a relation flag, and a find parameter.
	 *
	 * If the field should be empty, it sets up the select field with an empty option and '-- Select --' as the first option.
	 * If $init is provided, it sets up the select field with options from the $init collection mapped with keys.
	 * If $init is not provided, it sets up the select field with options from all the models of the given class.
	 *
	 * If $relation is provided, it adds a data-relation attribute to the select field so that it knows which relation to use as the source of the options.
	 *
	 * If $find is provided, it adds a data-find attribute to the select field so that it knows which method to call on the model to fetch the options.
	 *
	 * The method returns the select field.
	 *
	 * @param string $name The name of the field
	 * @param string $model The fully qualified class name of the model
	 * @param string $field The field to use as the display value
	 * @param Collection $init An optional collection of models to use as options
	 * @param boolean $empty A flag to determine if the field should be empty
	 * @param string $relation A relation flag
	 * @param string $find A find parameter
	 * @return Field The select field
	 */
	public static function setAjaxSelect($name, $model, $field, $init = null, $empty = false, $relation = null, $find = null)
	{
		if ($empty) {
			// If the field should be empty, set up the select field with an empty option and '-- Select --' as the first option
			return CRUD::field($name)
				->type("select_from_array")
				->model($model)
				->attributes(
					$relation != null
						? [
							"class" => "form-control form-select ajax_field ajax_field_empty",
							"data-model" => $model,
							"data-field" => $field,
							"data-relation" => $relation,
						]
						: [
							"class" => "form-control form-select ajax_field ajax_field_empty",
							"data-model" => $model,
							"data-field" => $field,
						]
				)
				->options(["" => "-- Select --"]);
		}
		if ($init) {
			// If $init is provided, set up the select field with options from the $init collection mapped with keys
			return CRUD::field($name)
				->type("select_from_array")
				->model($model)
				->attributes(
					$relation != null
						? [
							"class" => "form-control form-select ajax_field",
							"data-relation" => $relation,
							"data-model" => $model,
						]
						: ["class" => "form-control form-select ajax_field", "data-model" => $model]
				)
				->options(
					["" => "-- Select --"] +
						$init
							->mapWithKeys(function ($elem) use ($field) {
								return [$elem->id => $elem->$field];
							})
							->toArray()
				);
		}
		// If $init is not provided, set up the select field with options from all the models of the given class
		return CRUD::field($name)
			->type("select_from_array")
			->model($model)
			->attributes(
				$relation != null
					? [
						"class" => "form-control form-select ajax_field",
						"data-relation" => $relation,
						"data-model" => $model,
						"data-find" => $find,
					]
					: ["class" => "form-control form-select ajax_field", "data-model" => $model, "data-find" => $find]
			)
			->options(
				["" => "-- Select --"] +
					$model
						::all()
						->mapWithKeys(function ($elem) use ($field) {
							return [$elem->id => $elem->$field];
						})
						->toArray()
			);
	}

	/**
	 * Given a name, model, field, init and empty, will return a select field with the given properties.
	 *
	 * If $empty is true, the select field will be empty (i.e. it will have no values) and the first option will be '-- Select --'.
	 * If $init is not null, the select field will be populated with the values of the given model, filtered by the given $field.
	 * If $init is null, the select field will be populated with all the values of the given model, filtered by the given $field.
	 *
	 * @param string $name The name of the field.
	 * @param string $model The fully qualified class name of the model.
	 * @param string $field The name of the field that should be used as the display value.
	 * @param mixed $init An optional collection of models to use as the options. If not provided, all models of the given class will be retrieved.
	 * @param boolean $empty If true, the field will be empty and the first option will be '-- Select --'. If false, the field will be populated with the values of the given model, filtered by the given $field.
	 * @return \Backpack\CRUD\app\Library\CrudPanel\Fields\SelectFromArray
	 */
	public static function setSelect($name, $model, $field, $init = null, $empty = false)
	{
		// If $empty is true, create an empty select field with a '-- Select --' option
		if ($empty) {
			return CRUD::field($name)
				->type("select_from_array")
				->model($model)
				->attributes([
					"class" => "form-control form-select",
					"data-model" => $model,
					"data-field" => $field,
					"disabled" => "disabled",
				])
				->options(["" => "-- Select --"]);
		}
		// If $init is not null, create a select field with the values of the given model, filtered by the given $field
		if ($init) {
			return CRUD::field($name)
				->type("select_from_array")
				->model($model)
				->attributes(["class" => "form-control form-select", "data-model" => $model, "data-field" => $field])
				->options(
					["" => "-- Select --"] +
						$init
							->mapWithKeys(function ($elem) use ($field) {
								return [$elem->id => $elem->$field];
							})
							->toArray()
				);
		}
		// If $init is null, create a select field with all the values of the given model, filtered by the given $field
		return CRUD::field($name)
			->type("select_from_array")
			->model($model)
			->attributes(["class" => "form-control form-select", "data-model" => $model, "data-field" => $field])
			->options(
				["" => "-- Select --"] +
					$model
						::all()
						->mapWithKeys(function ($elem) use ($field) {
							return [$elem->id => $elem->$field];
						})
						->toArray()
			);
	}

	/**
	 * Given a model, will iterate over all its columns and methods, and for
	 * each one, will generate a field in the CRUD panel.
	 *
	 * The type of the field is determined by the type of the column in the
	 * database. If the column is not one of the common types (like string, integer,
	 * boolean, etc.), the field type will be "text".
	 *
	 * For each method, if the method has no parameters and returns a
	 * BelongsToMany or BelongsTo relation, the method will be treated as a
	 * relational field.
	 *
	 * The relational fields will be created as follows:
	 * - For BelongsToMany relations, a custom_html field will be created that shows the related entries as links.
	 * - For BelongsTo relations, a custom_html field will be created that shows the related entry as a link.
	 * - For pivot tables of BelongsToMany relations, a custom_html field will be created that shows the pivot table entries as links.
	 *
	 * @param \Illuminate\Database\Eloquent\Model $model The model to generate the fields for.
	 */
	public static function setFields(Model $model)
	{
		// Get the table name of the model
		$table = $model->getTable();
		// Get all the columns of the table
		$columns = Schema::getColumnListing($table);
		// Get all the public methods of the model
		$reflector = new ReflectionClass($model);
		// Filter the methods so that only the ones with no parameters are kept
		$methods = array_filter($reflector->getMethods(ReflectionMethod::IS_PUBLIC), function ($method) use ($model) {
			return $method->class == get_class($model) && $method->getNumberOfParameters() == 0;
		});
		// Iterate over the columns and create a field for each one
		foreach ($columns as $column) {
			// Skip the id, created_at, and updated_at columns
			if (in_array($column, ["id", "created_at", "updated_at"])) {
				continue;
			} elseif ($column == "email_verified_at") {
				$field = CRUD::field($column);
				$field->type("datetime");
				$field->default(now());
				$field->attributes(["readonly" => "readonly"]);
				continue;
			}
			// Get the type of the column
			$columnType = DB::getSchemaBuilder()->getColumnType($table, $column);
			// Determine the type of the field based on the type of the column
			$fieldType = self::getFieldType($columnType);

			// If the column type is not one of the common types, skip it
			if ($fieldType == "break") {
				continue;
			}
			// Create a new field
			$field = CRUD::field($column);

			// Handle specific types of fields
			self::handleSpecificType($field, $fieldType, $column);
		}
		// Iterate over the methods and create a field for each one
		foreach ($methods as $method) {
			// Get the name of the method
			$methodName = $method->name;

			if (in_array($methodName, ["sendEmailVerificationNotification"])) {
				return; // skip this iteration
			}

			// Call the method and get the result
			$result = $model->$methodName();
			// If the result is a BelongsToMany or BelongsTo relation, create a relational field
			if ($result instanceof BelongsToMany || $result instanceof BelongsTo) {
				self::createRelationalFields($methodName, $result);
			}
		}
	}

	/**
	 * Generate the fields and columns for the CRUD views.
	 *
	 * This method takes care of:
	 * - Creating a field for each column in the model's table
	 * - Creating a column for each relation method in the model
	 * - For relations of type BelongsToMany, creating a custom_html field that shows the related entries as links
	 * - For relations of type HasMany, creating a custom_html field that shows the related entries as links
	 * - For pivot tables of BelongsToMany relations, creating a custom_html field that shows the pivot table entries as links
	 * - For columns of type id, creating a link to the related entry's show page
	 *
	 * @param Model $model - The model for which fields need to be set.
	 * @return void
	 */
	public static function setFieldsView(Model $model)
	{
		// Get the table name and its columns
		$table = $model->getTable();
		$columns = Schema::getColumnListing($table);

		// Get the public methods of the model
		$methods = collect((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC))->filter(function (
			$method
		) use ($model) {
			// We only care about methods that are on the model itself and have no parameters
			return $method->class == get_class($model) && $method->getNumberOfParameters() == 0;
		});

		// The url of the backend
		$backendUrl = config("app.url") . "/admin";

		// Loop through each column of the table
		foreach ($columns as $column) {
			// Get the type of the column in the database
			$columnType = DB::getSchemaBuilder()->getColumnType($table, $column);

			// Determine the type of field that should be created
			$fieldType = self::getFieldType($columnType);

			// If the column is an id, create a link to the related entry's show page
			if (str_ends_with($column, "_id") && !str_contains($column, "transaction")) {
				$relationName = str_replace("_id", "", $column);
				CRUD::column($column)
					->label(ucfirst($relationName))
					->type("custom_html")
					->value(function ($entry) use ($relationName, $backendUrl) {
						// If the entry has a related entry, create a link to its show page
						if ($entry->$relationName) {
							$name =
								$entry->$relationName->email ?? ($entry->$relationName->name ?? $entry->$relationName->id);
							$id = $entry->$relationName->id;
							return '<a target="_blank" href="' .
								$backendUrl .
								"/" .
								strtolower(class_basename($entry->$relationName)) .
								"/" .
								$id .
								'/show">' .
								$name .
								"</a>";
						}
					});
			} else {
				// Create a normal field for the column
				$field = CRUD::column($column)->type($fieldType);

				if ($field) {
					// Handle any specific type of field
					self::handleSpecificTypeView($field, $column);
				}
			}
		}

		// Loop through each method of the model
		$methods->each(function ($method) use ($model, $backendUrl) {
			$methodName = $method->name;
			if (in_array($methodName, ["sendEmailVerificationNotification"])) {
				return; // skip this iteration
			}
			if (method_exists($model, $methodName)) {
				// Get the result of calling the method
				$result = $model->$methodName();

				// If the result is a BelongsToMany or HasMany relation, create a custom_html field
				if ($result instanceof BelongsToMany || $result instanceof HasMany) {
					self::createRelationalFieldsView($methodName, $result, $backendUrl);
				}
			}
		});
	}

	/**
	 * Determine the appropriate form field type based on the column type.
	 *
	 * This function takes a database column type as an argument and returns
	 * the corresponding form field type.
	 *
	 * The mapping is as follows:
	 * - string, varchar: text
	 * - text: textarea
	 * - int, smallint, float, double, decimal: number
	 * - tinyint: switch
	 * - date: date
	 * - datetime, timestamp: datetime
	 * - bigint: break
	 * - All other types: text
	 *
	 * @param string $columnType The type of the database column.
	 * @return string The corresponding form field type.
	 */
	public static function getFieldType($columnType)
	{
		switch ($columnType) {
			// String, varchar columns become text fields
			case "string":
			case "varchar":
				return "text";
				break;
			// Text columns become textarea fields
			case "text":
			case "longtext":
				return "textarea";
				break;
			// Integer, float, double, decimal columns become number fields
			case "int":
			case "smallint":
			case "float":
			case "double":
			case "decimal":
				return "number";
				break;
			// Tinyint columns become switch fields
			case "tinyint":
				return "switch";
				break;
			// Date columns become date fields
			case "date":
				return "date";
				break;
			// Datetime, timestamp columns become datetime fields
			case "datetime":
			case "timestamp":
				return "datetime";
				break;
			// Bigint columns become break fields
			case "bigint":
				return "break";
				break;
			// All other types become text fields
			default:
				return "text";
				break;
		}
	}

	/**
	 * Handles specific field types based on column name.
	 *
	 * This function is called after the field type has been determined by the
	 * getFieldType function. It takes in the field to be modified, the type of
	 * the field, and the column name.
	 *
	 * It then checks the column name to see if it matches any of the following
	 * conditions:
	 *  - If the column name contains the word "password", set the field type to
	 *    "password".
	 *  - If the column name contains the word "email", set the field type to
	 *    "email".
	 *  - If the column name contains the word "formatted", set the field type to
	 *    "summernote".
	 *  - If the column name contains the word "url", set the field type to "url".
	 *  - If the column name contains the word "import", add a prefix of "€" to the
	 *    field.
	 *  - If the column name contains the word "percentage", add a suffix of "%"
	 *    to the field and set the min and max attributes to 0 and 100,
	 *    respectively.
	 *  - If the column name contains the word "backpack_role", set the field type
	 *    to "radio" and add options for the different roles.
	 *
	 * @param Field $field The field to be modified.
	 * @param string $fieldType The type of the field.
	 * @param string $column The column name.
	 * @return void
	 */
	public static function handleSpecificType($field, $fieldType, $column)
	{
		if (str_contains($column, "password")) {
			// If the column name contains the word "password", set the field type to "password"
			$fieldType = "password";
		} elseif (str_contains($column, "email")) {
			// If the column name contains the word "email", set the field type to "email"
			$fieldType = "email";
		} elseif (str_contains($column, "formatted")) {
			// If the column name contains the word "formatted", set the field type to "summernote"
			$fieldType = "summernote";
		} elseif (str_contains($column, "url")) {
			// If the column name contains the word "url", set the field type to "url"
			$fieldType = "url";
		} elseif (str_contains($column, "import")) {
			// If the column name contains the word "import", add a prefix of "€" to the field
			$field->prefix("€");
		} elseif (str_contains($column, "percentage")) {
			// If the column name contains the word "percentage", add a suffix of "%"
			// to the field and set the min and max attributes to 0 and 100,
			// respectively
			$field->suffix("%");
			$field->attributes(["min" => 0, "max" => 100]);
		} elseif (str_contains($column, "backpack_role")) {
			// If the column name contains the word "backpack_role", set the field type
			// to "radio" and add options for the different roles
			$fieldType = "radio";
			$field->options(["admin" => "Admin", "developer" => "Developer", "guest" => "Guest", "user" => "User"]);
		} elseif (str_contains($column, "status")) {
			$fieldType = "radio";
			$field->options([
				"pending" => "Pending",
				"completed" => "Completed",
				"rejected" => "Rejected",
				"expired" => "Expired",
			]);
		} elseif (str_contains($column, "image_path")) {
			$fieldType = "upload";
			$field->upload(true);
			$field->label("Upload Image");
			$field->disk("uploads");
			$field->attributes(["accept" => "image/*"]);
		} elseif (str_contains($column, "webm_path")) {
			$fieldType = "upload";
			$field->upload(true);
			$field->label("Upload Webm");
			$field->disk("uploads");
			$field->attributes(["accept" => "video/webm"]);
		} elseif (str_contains($column, "mp4_path")) {
			$fieldType = "upload";
			$field->upload(true);
			$field->label("Upload Mp4");
			$field->disk("uploads");
			$field->attributes(["accept" => "video/mp4"]);
		} elseif (str_contains($column, "ogv_path")) {
			$fieldType = "upload";
			$field->upload(true);
			$field->label("Upload Ogv");
			$field->disk("uploads");
			$field->attributes(["accept" => "video/ogg"]);
		} elseif (str_contains($column, "file_path")) {
			$fieldType = "upload";
			$field->upload(true);
			$field->label("Upload File");
			$field->disk("uploads");
			$field->attributes(["accept" => "document/*"]);
		}
		// Set the field type to the determined value
		$field->type($fieldType);
	}

	/**
	 * Handle specific types of fields in the CRUD List operation.
	 *
	 * This function takes a Field object and a column name as arguments.
	 * It checks if the column name contains certain words, and if so,
	 * it applies specific formatting to the field.
	 *
	 * @param Field $field The field to be modified.
	 * @param string $column The column name.
	 * @return void
	 */
	public static function handleSpecificTypeView($field, $column)
	{
		// If the column name contains the word "import", add a prefix of "€"
		// to the field. This is used for displaying monetary values.
		if (str_contains($column, "import")) {
			$field->prefix("€");
		}

		// If the column name contains the word "percentage", add a suffix of "%"
		// to the field. This is used for displaying percentages.
		if (str_contains($column, "percentage")) {
			$field->suffix("%");
		}
	}

	/**
	 * This method creates relational fields based on the provided method name and the result relationship.
	 * It dynamically generates CRUD fields based on the type of relationship and attributes of the related model.
	 * The supported relationship types are BelongsToMany and BelongsTo.
	 *
	 * @param string $methodName The name of the method representing the relationship.
	 * @param mixed $result The result of the relationship query.
	 * @return void
	 */
	public static function createRelationalFields($methodName, $result)
	{
		// Get the related model and its class
		$relatedModel = $result->getRelated();
		$relatedModelClass = get_class($relatedModel);

		// Get the table name of the related model
		$tableModel = $relatedModel->getTable();

		// Determine the attribute to use based on column availability
		$attribute = Schema::hasColumn($tableModel, "email")
			? "email"
			: (Schema::hasColumn($tableModel, "transaction_id")
				? "transaction_id"
				: (Schema::hasColumn($tableModel, "import")
					? "import"
					: (Schema::hasColumn($tableModel, "name")
						? "name"
						: (Schema::hasColumn($tableModel, "title_italian")
							? "title_italian"
							: "id"))));

		// Check if the result is a BelongsToMany relationship
		if ($result instanceof BelongsToMany) {
			// Create a select multiple field for BelongsToMany relationship
			CRUD::field($methodName)
				->type("select_multiple")
				->entity($methodName)
				->model($relatedModelClass)
				->attribute($attribute)
				->pivot(true);
		}
		// Check if the result is a BelongsTo relationship
		elseif ($result instanceof BelongsTo) {
			// Create a select field for BelongsTo relationship using an array of options
			CRUD::field($methodName . "_id")
				->type("select_from_array")
				->model($relatedModelClass)
				->options(
					$relatedModelClass
						::all()
						->mapWithKeys(function ($elem) use ($attribute) {
							return [$elem->id => $elem->$attribute];
						})
						->toArray()
				);
		}
	}

	/**
	 * This method creates columns for displaying BelongsToMany or BelongsTo relationships in the CRUD List operation.
	 * It generates columns that show related model names with links to the related model's CRUD show operation.
	 * For BelongsToMany relationships, an additional column displays the pivot table's percentage attribute.
	 *
	 * @param string $methodName The name of the method representing the relationship.
	 * @param mixed $result The result of the relationship query.
	 * @param string $backendUrl The URL of the CRUD backend.
	 * @return void
	 */
	public static function createRelationalFieldsView($methodName, $result, $backendUrl)
	{
		// Get the related model and its details
		$relatedModel = $result->getRelated();
		$relatedModelName = class_basename($relatedModel);
		$formattedRelatedModelName = strtolower(preg_replace("/([a-z])([A-Z])/", '$1-$2', $relatedModelName));
		$formattedRelatedModelName = str_replace("_", "-", $formattedRelatedModelName);
		$relatedModelClass = get_class($relatedModel);
		$tableModel = $relatedModel->getTable();

		// Determine the attribute to use based on column availability
		$attribute = Schema::hasColumn($tableModel, "email")
			? "email"
			: (Schema::hasColumn($tableModel, "transaction_id")
				? "import"
				: (Schema::hasColumn($tableModel, "import")
					? "import"
					: (Schema::hasColumn($tableModel, "name")
						? "name"
						: (Schema::hasColumn($tableModel, "title_italian")
							? "title_italian"
							: "id"))));

		// Create a column for the related model name with links
		CRUD::column($methodName)
			->type("custom_html")
			->entity($methodName)
			->model($relatedModelClass)
			->value(function ($entry) use ($formattedRelatedModelName, $methodName, $attribute, $backendUrl) {
				$links = [];
				foreach ($entry->$methodName as $elem) {
					$links[] =
						'<a target="_blank" href="' .
						$backendUrl .
						"/" .
						$formattedRelatedModelName .
						"/" .
						$elem->id .
						'/show">' .
						($attribute == "import" ? "€" : "") .
						$elem->$attribute .
						"</a>";
				}
				return implode(", ", $links);
			});

		// Create a column for the pivot table's percentage attribute for BelongsToMany relationships
		if ($result instanceof BelongsToMany) {
			CRUD::column($methodName . "_pivot")
				->label(ucfirst($methodName . " pivot"))
				->type("custom_html")
				->entity($methodName)
				->model($relatedModelClass)
				->value(function ($entry) use ($methodName, $attribute, $backendUrl) {
					$pivotTableName = $entry->$methodName()->getTable();
					$pivotTableName = str_replace("_", "-", $pivotTableName);
					$links = [];
					foreach ($entry->$methodName as $elem) {
						$percentage = $elem->pivot->percentage;
						$pivotId = $elem->pivot->id;
						$links[] =
							'<a target="_blank" href="' .
							$backendUrl .
							"/" .
							$pivotTableName .
							"/" .
							$pivotId .
							'/show">' .
							$elem->$attribute .
							" (" .
							$percentage .
							"%)</a>";
					}
					return implode(", ", $links);
				});
		}
	}

	/**
	 * Retrieves an element from the database and filters it using the given dot notation.
	 * If the element is not found, returns a 404 error.
	 *
	 * This function is used when the user wants to retrieve data from the database
	 * using an AJAX request. The request should contain the id of the element to
	 * retrieve, the model that contains the element, and the find parameter that
	 * specifies the filtering to apply to the element.
	 *
	 * The find parameter is a dot notation that specifies the attributes of the
	 * element to retrieve. For example, if the model is User and the find parameter
	 * is "name->first_name", the function will retrieve the first name of the user.
	 *
	 * The function first tries to find the element in the database using the given
	 * id. If the element is found, it applies the filtering specified in the find
	 * parameter. If the element is not found, it returns a 404 error.
	 *
	 * @param Request $request
	 * @param int $elemId
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function getDataByAjax(Request $request, $elemId)
	{
		// Get the model from the request
		$model = $request->input("model");

		// Get the find parameter from the request
		$find = $request->input("find");

		// Try to find the element in the database
		$elem = $model::find($elemId);

		// If the element is not found, return a 404 error
		if (!$elem) {
			return error("Not found");
		}

		// If the find parameter is not null, apply the filtering
		if ($find != null) {
			// Split the find parameter into an array of filters
			$filters = explode("->", $find);

			// Apply the filters
			foreach ($filters as $filter) {
				// If the element is not null, apply the filter
				if ($elem) {
					$elem = $elem->{$filter};
				} else {
					// If the element is null, break the loop
					break;
				}
			}
		}

		// Return the filtered element
		return response()->json($elem);
	}
}
