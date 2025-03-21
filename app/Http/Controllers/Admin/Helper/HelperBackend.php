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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

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
		$request = Request::capture();
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

		$queryParams = $request->except("page");

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
			self::handleSpecificType($field, $fieldType, $column, $table);

			if (isset($queryParams[$column])) {
				$field->value($queryParams[$column]);
			}
		}

		// === ADD IMAGE PREVIEW IN "UPLOADS" TAB (ONLY IN EDIT MODE) ===
		$entryId = CRUD::getCurrentEntryId(); // Get the current entry ID (only exists in edit mode)
		if ($entryId) {
			$entry = $model->find($entryId);
			$previewHtml = "";

			// Helper function to get clean file name (removing "media/")
			function getCleanFileName($filePath)
			{
				return str_replace("media/", "", basename($filePath));
			}

			// Check if an image exists and generate preview
			if (!empty($entry->image_path)) {
				$imageUrl = config("app.url") . "/storage/uploads/" . $entry->image_path;
				$fileName = getCleanFileName($entry->image_path);
				$previewHtml .=
					'<div class="media-item">
									<strong>' .
					htmlspecialchars($fileName) .
					'</strong>
									<a href="' .
					$imageUrl .
					'" target="_blank">
										<img src="' .
					$imageUrl .
					'" alt="Image Preview"/>
									</a>
								</div>';
			}

			// Check if a video exists (MP4, WebM, OGV, OGG) and generate preview
			foreach (
				["mp4_path" => "mp4", "webm_path" => "webm", "ogv_path" => "ogg", "ogg_path" => "ogg"]
				as $column => $mimeType
			) {
				if (!empty($entry->$column)) {
					$videoUrl = config("app.url") . "/storage/uploads/" . $entry->$column;
					$fileName = getCleanFileName($entry->$column);

					$previewHtml .=
						'<div class="media-item">
										<strong>' .
						htmlspecialchars($fileName) .
						'</strong>
										<video controls>
											<source src="' .
						$videoUrl .
						'" type="video/' .
						$mimeType .
						'">
											Your browser does not support the video tag.
										</video>
									</div>';
				}
			}

			// Check if an MP3 file exists and generate preview
			if (!empty($entry->mp3_path)) {
				$audioUrl = config("app.url") . "/storage/uploads/" . $entry->mp3_path;
				$fileName = getCleanFileName($entry->mp3_path);

				$previewHtml .=
					'<div class="media-item audio-item">
									<strong>' .
					htmlspecialchars($fileName) .
					'</strong>
									<div class="audio-container">
										<audio controls>
											<source src="' .
					$audioUrl .
					'" type="audio/mpeg">
											Your browser does not support the audio tag.
										</audio>
									</div>
								</div>';
			}

			// Add the preview field in Backpack if any media is found
			if (!empty($previewHtml)) {
				CRUD::addField([
					"name" => "media_preview",
					"type" => "custom_html",
					"tab" => "Gallery", // Place inside the "Gallery" tab
					"value" => '<div class="media-container">' . $previewHtml . "</div>",
				]);
			}
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
				self::createRelationalFields($methodName, $result, $table);
			} elseif ($result instanceof HasMany) {
				self::createRelation($methodName, $result, $table);
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
		self::applyFilters($model);
		// Get the table name and its columns
		$table = $model->getTable();
		$columns = Schema::getColumnListing($table);

		// Get the public methods of the model
		$methods = collect((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC))->filter(function (
			$method
		) use ($model) {
			return $method->class == get_class($model) && $method->getNumberOfParameters() == 0;
		});

		// Loop through each column of the table
		foreach ($columns as $column) {
			$projectBaseUrl = config("app.url") . "/admin";
			// The base project URL (Ensure correct project base path)
			// Get the type of the column in the database
			$columnType = DB::getSchemaBuilder()->getColumnType($table, $column);

			// Determine the type of field that should be created
			$fieldType = self::getFieldType($columnType);

			if ($column == "order") {
				Widget::add()->type("script")->content("static/js/draggable-sort.js");
				CRUD::addButtonFromView("top", "export_csv", "export_csv", "end");
				CRUD::addButtonFromView("top", "draggable_button", "draggable_button");
				CRUD::removeButton("create");
				CRUD::addButtonFromView("top", "create", "create_filters");
			}

			// Display a small image or media player instead of a link for media files
			if (in_array($column, ["image_path", "mp4_path", "webm_path", "ogv_path", "ogg_path", "mp3_path"])) {
				$projectBaseUrl = config("app.url") . "/storage/uploads";

				CRUD::column($column)
					->label(ucfirst(str_replace("_path", "", $column))) // Dynamically set label
					->type("custom_html")
					->value(function ($entry) use ($column, $projectBaseUrl) {
						if (!empty($entry->$column)) {
							$fileUrl = $projectBaseUrl . "/" . $entry->$column; // Correct full URL

							// Handle different file types
							if ($column === "image_path") {
								return '<a href="' .
									$fileUrl .
									'" target="_blank">
											<img src="' .
									$fileUrl .
									'" alt="Preview" 
												 style="width: 150px; height: auto; border-radius: 3px;"
												 loading="lazy"/>
										</a>';
							} elseif (in_array($column, ["mp4_path", "webm_path", "ogv_path", "ogg_path"])) {
								// Define correct MIME type for each video format
								$mimeTypes = [
									"mp4_path" => "video/mp4",
									"webm_path" => "video/webm",
									"ogv_path" => "video/ogg",
									"ogg_path" => "video/ogg",
								];

								return '<div class="video-container" style="width: 150px; height: 100px; background: #333; display: flex; align-items: center; justify-content: center; position: relative; border-radius: 5px;">
											<button style="position: absolute; width: 40px; height: 40px; background: rgba(255, 255, 255, 0.7); border: none; border-radius: 50%; font-size: 20px; cursor: pointer; z-index: 2;"
												onclick="this.style.display=\'none\'; 
														 let video = this.nextElementSibling; 
														 let container = this.parentElement;
														 container.style.background=\'transparent\'; 
														 video.style.display=\'block\'; 
														 video.play(); 
														 video.controls = true;">
												▶
											</button>
											<video 
												style="width: 100%; height: 100%; object-fit: cover; display: none;" 
												preload="none">
												<source src="' .
									$fileUrl .
									'" type="' .
									$mimeTypes[$column] .
									'">
												Your browser does not support the video tag.
											</video>
										</div>';
							} elseif ($column === "mp3_path") {
								return '<audio controls style="width: 150px;" preload="none">
											<source src="' .
									$fileUrl .
									'" type="audio/mpeg">
											Your browser does not support the audio tag.
										</audio>';
							}
						}
						return "No File";
					});
			}

			// Handle normal relationships
			elseif (str_ends_with($column, "_id") && !str_contains($column, "transaction")) {
				$relationName = str_replace("_id", "", $column);

				CRUD::column($column)
					->label(ucfirst($relationName))
					->type("custom_html")
					->value(function ($entry) use ($relationName, $projectBaseUrl) {
						if ($entry->$relationName) {
							$id = $entry->$relationName->id;

							// Determine display text based on available attributes
							$displayText = match (true) {
								isset($entry->$relationName->email) => $entry->$relationName->email,
								isset($entry->$relationName->surname, $entry->$relationName->name) => $entry->$relationName
									->name .
									" " .
									$entry->$relationName->surname,
								isset($entry->$relationName->author_surname, $entry->$relationName->author_name) => $entry
									->$relationName->author_name .
									" " .
									$entry->$relationName->author_surname,
								isset($entry->$relationName->title) => $entry->$relationName->title,
								isset($entry->$relationName->name) => $entry->$relationName->name,
								default => $id,
							};

							return '<a target="_blank" href="' .
								$projectBaseUrl .
								"/" .
								strtolower(class_basename($entry->$relationName)) .
								"/" .
								$id .
								'/edit">' .
								$displayText .
								"</a>";
						}
					});
			} else {
				// Create a normal field for the column
				$field = CRUD::column($column)->type($fieldType);
				if ($field) {
					self::handleSpecificTypeView($field, $column);
				}
			}
		}

		// Process relationships
		$methods->each(function ($method) use ($model, $projectBaseUrl) {
			$methodName = $method->name;
			if (in_array($methodName, ["sendEmailVerificationNotification"])) {
				return; // skip this iteration
			}
			if (method_exists($model, $methodName)) {
				$result = $model->$methodName();
				if ($result instanceof BelongsToMany || $result instanceof HasMany) {
					self::createRelationalFieldsView($methodName, $result, $projectBaseUrl);
				}
			}
		});

		CRUD::removeButton("show", "line");
		CRUD::addButtonFromView("line", "duplicate", "duplicate", "view");
	}

	public static function applyFilters(Model $model)
	{
		$request = Request::capture();
		$filters = $request->except("page"); // Don't remove the default page, as it is handled separately

		// Add filter for the page_filter
		if ($pageFilter = $request->get("page_filter")) {
			CRUD::addClause("where", "page", "=", $pageFilter);
		}

		foreach ($filters as $key => $value) {
			// Skip filtering for completely empty values but allow "0"
			if ($value === "" || is_array($value)) {
				continue;
			}

			if (Schema::hasColumn($model->getTable(), $key)) {
				// Get column type
				$columnType = Schema::getColumnType($model->getTable(), $key);

				// Check if the column is nullable
				$table = $model->getTable();
				$database = env("DB_DATABASE");
				$isNullable =
					DB::table("INFORMATION_SCHEMA.COLUMNS")
						->where("TABLE_SCHEMA", $database)
						->where("TABLE_NAME", $table)
						->where("COLUMN_NAME", $key)
						->value("IS_NULLABLE") === "YES";

				if ($columnType === "boolean" || $columnType === "tinyint") {
					// Explicitly check for "0" and "1", allowing null if the field is nullable
					if ($value === "1" || $value === "0") {
						CRUD::addClause("where", $key, "=", $isNullable && $value === "0" ? null : (int) $value);
					}
				} else {
					// Apply LIKE filter for text-based fields
					CRUD::addClause("where", $key, "LIKE", "%" . $value . "%");
				}
			}
		}

		CRUD::removeButton("create");
		CRUD::addButtonFromView("top", "export_csv", "export_csv", "end");
		CRUD::addButtonFromView("top", "create", "create_filters");
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
			case "mediumtext":
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
	public static function handleSpecificType($field, $fieldType, $column, $table)
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
			$field->type($fieldType);
			if ($table == "paragraphs") {
				$field->hint(trans("backpack::crud.hint_add_note"));
			}
		} elseif (str_contains($column, "url")) {
			// If the column name contains the word "url", set the field type to "url"
			$fieldType = "url";
		} elseif (str_contains($column, "import")) {
			// If the column name contains the word "import", add a prefix of "€" to the field
			$field->prefix("€");
		} elseif (str_contains($column, "hide_")) {
			$field->hint(trans("backpack::crud.hint_hide") . str_replace("hide_", "", $column));
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
			$field->options(["admin" => "Admin"]);
			$field->default("admin");
		} elseif (str_contains($column, "status") && $table == "order") {
			$fieldType = "radio";
			$field->options([
				"pending" => "Pending",
				"completed" => "Completed",
				"rejected" => "Rejected",
				"expired" => "Expired",
			]);
		} elseif ($column == "order") {
			$field->attributes(["step" => 1, "min" => 1, "max" => DB::table($table)->max("order") + 1]); // Ensure the user enters integers only
			$field->default(DB::table($table)->max("order") + 1);
		}
		$uploadFields = [
			"image_path" => "image/*",
			"mp4_path" => "video/mp4",
			"ogv_path" => "video/ogg",
			"webm_path" => "video/webm",
			"mp3_path" => "audio/mpeg",
			"ogg_path" => "video/ogg",
			"file_path" => "application/pdf",
		];

		foreach ($uploadFields as $upload => $mime) {
			if (str_contains($column, $upload)) {
				$fieldType = "upload";
				$field->upload(true);
				$field->label("Upload " . ucfirst(str_replace("_path", "", $upload)));
				$field->disk("uploads");
				$field->attributes(["accept" => $mime]);
			}
		}
		if ($column == "mp3_path") {
			$field->hint(trans("backpack::crud.hint_mp3"));
		}
		$tabsAdded = [];
		if ($table == "media") {
			$addTabDescription = function ($tab, $title, $description) use (&$tabsAdded) {
				if (!isset($tabsAdded[$tab])) {
					CRUD::addField([
						"name" => "custom_html_" . strtolower(str_replace(" ", "_", $tab)),
						"type" => "custom_html",
						"value" =>
							'
				<div class="p-3 mb-1 border-warning" style="border-left: 4px solid; background: #f8f9fa; border-radius: 5px;">
					<h4 class="m-0" style="color: #f76707;">' .
							$title .
							'</h4>
					<p class="mb-0 mt-2" >' .
							$description .
							'</p>
				</div>',
						"tab" => $tab,
					]);
					$tabsAdded[$tab] = true;
				}
			};
			$addTabDescription(
				"Uploads",
				trans("backpack::crud.disclaimer_title"),
				trans("backpack::crud.disclaimer_uploads")
			);
			$addTabDescription(
				"Paragraph",
				trans("backpack::crud.disclaimer_title"),
				trans("backpack::crud.disclaimer_paragraph")
			);
			$addTabDescription(
				"Caption",
				trans("backpack::crud.disclaimer_title"),
				trans("backpack::crud.disclaimer_caption")
			);
			$addTabDescription(
				"Chapter",
				trans("backpack::crud.disclaimer_title"),
				trans("backpack::crud.disclaimer_chapter")
			);
			$addTabDescription(
				"President",
				trans("backpack::crud.disclaimer_title"),
				trans("backpack::crud.disclaimer_president")
			);
			$addTabDescription(
				"Thought",
				trans("backpack::crud.disclaimer_title"),
				trans("backpack::crud.disclaimer_thought")
			);
			$addTabDescription("Hero", trans("backpack::crud.disclaimer_title"), trans("backpack::crud.disclaimer_hero"));
			$addTabDescription(
				"Gallery",
				trans("backpack::crud.disclaimer_title"),
				trans("backpack::crud.disclaimer_gallery")
			);
			switch ($column) {
				case str_contains($column, "paragraph"):
				case str_contains($column, "full_width_bb"):
					if (str_contains($column, "layout")) {
						$field->label(trans("backpack::crud.paragraph_layout_title") . " Paragraph");
						$fieldType = "radio";
						$field->options([
							"photo_left_overlapping" => trans("backpack::crud.photo_left_overlapping"),
							"full_width" => trans("backpack::crud.full_width"),
							"photo_left_not_overlapping" => trans("backpack::crud.photo_left_not_overlapping"),
							"vertical_left" => trans("backpack::crud.vertical_left"),
							"vertical_middle" => trans("backpack::crud.vertical_middle"),
							"vertical" => trans("backpack::crud.vertical"),
							"horizontal" => trans("backpack::crud.horizontal"),
						]);
						$field->hint(trans("backpack::crud.hint_paragraph_layout"));
					} elseif (str_contains($column, "full_width_bb")) {
						$field->label("Full Width - Blue background");
						$field->hint(trans("backpack::crud.hint_bb"));
					}
					$field->tab("Paragraph");
					break;
				case str_contains($column, "chapter"):
					$field->tab("Chapter");
					break;
				case str_contains($column, "path"):
					$field->tab("Uploads");
					break;
				case $column == "caption":
					$field->tab("Caption");
					break;
				case $column == "caption_layout":
					$fieldType = "radio";
					$field->tab("Caption");
					$field->options(["top" => "Top position", "bottom" => "Bottom position"]);
					$field->hint(trans("backpack::crud.hint_caption_layout"));
					break;
				case str_contains($column, "thought"):
					$field->tab("Thought");
					break;
			}
		}
		if ($table == "thoughts") {
			if (str_contains($column, "page")) {
				$fieldType = "radio";
				$field->options([
					"riflessioni-su-milano" => "Riflessioni su milano",
					"home" => "Homepage",
				]);
				$field->default("riflessioni-su-milano");
				$field->hint(trans("backpack::crud.hint_thought_page"));
			}
		}
		if ($table == "seo_meta_information") {
			if (str_contains($column, "code")) {
				$fieldType = "select_from_array";
				$field->options([
					"title" => "title",
					"description" => "description",
					"og_url" => "og:url",
					"og_site_name" => "og:site_name",
					"og_title" => "og:title",
					"og_description" => "og:description",
					"og_image" => "og:image",
					"og_locale" => "og:locale",
				]);
			}
		}
		if ($table == "presidents") {
			if (str_contains($column, "image_path")) {
				$field->hint(trans("backpack::crud.hint_president_image"));
			}
		}
		// Set the field type to the determined value
		$field->wrapper(["class" => "form-group col-md-6"]);
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

		if ($column === "order") {
			CRUD::orderBy("order", "asc");
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
	public static function createRelationalFields($methodName, $result, $table)
	{
		// Get the related model
		$relatedModel = $result->getRelated();
		$relatedModelClass = get_class($relatedModel);

		// Get the correct table name
		$tableName = $relatedModel->getTable();

		// Ensure the table name exists before checking columns
		if (!Schema::hasTable($tableName)) {
			return; // Prevent errors if the table does not exist
		}

		// Determine the primary attribute dynamically
		$primaryAttribute = collect([
			"email",
			"transaction_id",
			"import",
			"name",
			"title_it",
			"title",
			"title_en",
			"id",
		])->first(fn($column) => Schema::hasColumn($tableName, $column), "id"); // Default to "id";

		// Handle BelongsToMany relationship
		if ($result instanceof BelongsToMany) {
			CRUD::field($methodName)
				->type("select_multiple")
				->entity($methodName)
				->wrapper(["class" => "form-group col-md-6"])
				->model($relatedModelClass)
				->attribute($primaryAttribute)
				->pivot(true);
		}
		// Handle BelongsTo relationship
		elseif ($result instanceof BelongsTo) {
			// Get the foreign key from the relationship
			$foreignKey = $result->getForeignKeyName();

			// Check if the foreign key is present in the URL
			$defaultValue = FacadesRequest::query($foreignKey, null);

			// Create a select field with pre-selected value
			$field = CRUD::field($methodName . "_id")
				->type("select_from_array")
				->model($relatedModelClass)
				->wrapper(["class" => "form-group col-sm-12"])
				->options(
					$relatedModelClass
						::all()
						->mapWithKeys(function ($elem) use ($methodName, $primaryAttribute) {
							switch ($methodName) {
								case "thought":
									$displayText =
										$elem->author_name && $elem->author_surname
											? $elem->author_name . " " . $elem->author_surname
											: $elem->id;
									break;
								case "president":
									$displayText =
										$elem->name && $elem->surname ? $elem->name . " " . $elem->surname : $elem->id;
									break;
								default:
									$displayText = $elem->$primaryAttribute ?: $elem->id;
							}
							return [$elem->id => $displayText];
						})
						->toArray()
				)
				->value($defaultValue); // Pre-fill value from URL if available
			// Determine the tab dynamically
			$tabName = null;
			if ($table == "media") {
				switch ($methodName) {
					case "paragraph":
						$tabName = "Paragraph";
						break;
					case "chapter":
						$tabName = "Chapter";
						break;
					case "institutional":
						$tabName = "Institutional";
						break;
					case "page":
						$tabName = "Hero";
						break;
					case "thought":
						$tabName = "Thought";
						break;
					case "president":
						$tabName = "President";
						break;
				}
			}

			// Assign the tab to the field
			if ($tabName) {
				$field->tab($tabName);
			}

			// Add a preview field with the same tab
			$previewField = CRUD::field($methodName . "_preview")
				->type("custom_html")
				->value(
					'<a id="' .
						$methodName .
						'_preview_link" href="#" class="btn btn-sm btn-link" style="display: none;">
						<i class="la la-question-circle"></i> ' .
						trans("backpack::crud.open") .
						$methodName .
						' 
					</a>'
				)
				->wrapper(["class" => "form-group col-md-1 z-3"]);

			if ($tabName) {
				$previewField->tab($tabName);
			}

			// Include a script to update the preview button dynamically
			CRUD::addField([
				"name" => "script_" . $methodName,
				"type" => "custom_html",
				"value" =>
					"<script>
						document.addEventListener('DOMContentLoaded', function () {
							const select = document.querySelector('[name=\"" .
					$methodName .
					"_id\"]');
							const previewLink = document.getElementById('" .
					$methodName .
					"_preview_link');
							
							// Detect if we are in edit mode
							const isEditMode = " .
					(CRUD::getCurrentEntryId() ? "true" : "false") .
					";
							const basePath = isEditMode ? '../../../admin/' : '../../admin/';
			
							if (select && previewLink) {
								select.addEventListener('change', function () {
									const selectedId = select.value;
									if (selectedId) {
										previewLink.href = basePath + '" .
					$methodName .
					"/' + selectedId + '/edit';
										previewLink.style.display = 'inline-block';
									} else {
										previewLink.style.display = 'none';
									}
								});
			
								const initialSelectedId = select.value;
								if (initialSelectedId) {
									previewLink.href = basePath + '" .
					$methodName .
					"/' + initialSelectedId + '/edit';
									previewLink.style.display = 'inline-block';
								}
							}
						});
					</script>",
				"wrapper" => ["class" => "form-group col-md-1"],
				"tab" => $tabName, // Assign the same tab dynamically
			]);
		}
	}

	public static function createRelation($methodName, $result, $table)
	{
		// Ensure the relationship is of type HasMany
		if (!($result instanceof HasMany)) {
			return;
		}

		// Get the related model and its class
		$relatedModel = $result->getRelated();
		$relatedModelClass = get_class($relatedModel);

		// Get the current entry ID (if it's being edited)
		$id = CRUD::getCurrentEntryId();

		// If no ID exists, we're in create mode, so don't show the button
		if (!$id) {
			return;
		}

		$relatedModelBaseName = class_basename($relatedModelClass);

		// Convert it to kebab-case (for URL consistency)
		$relatedModelUrlSegment = Str::kebab($relatedModelBaseName); // Example: invoice_item → invoice-item

		// Get the foreign key (already correct)
		$foreignKey = $result->getForeignKeyName();

		// Fetch related records
		$relatedEntries = $relatedModelClass::where($foreignKey, $id)->get();

		// Build list of related entries
		$relationList = '<ul style="list-style-type: disc; padding-left: 20px; margin-top: 5px;">';
		if ($relatedEntries->count() > 0) {
			foreach ($relatedEntries as $entry) {
				$entryId = $entry->id;
				$entryLabel = $entry->name ?? ($entry->title ?? "ID: $entryId"); // Use a meaningful field
				$relationList .=
					'<li style="margin-bottom: 4px;">
									<a href="' .
					url("admin/{$relatedModelUrlSegment}/" . $entryId . "/edit") .
					'"  
									class="relation-link">
										<i class="la la-file"></i> ' .
					e($entryLabel) .
					'
									</a>
								</li>';
			}
		} else {
			$relationList .= '<li style="color: #999; font-size: 13px;">No related records found.</li>';
		}
		$relationList .= "</ul>";

		// Create a custom HTML field inside the "Associations" tab, with a title, button, and related list
		CRUD::addField([
			"name" => $methodName . "_relation_section",
			"type" => "custom_html",
			"value" =>
				'<div style="display: flex; flex-direction: column; align-items: flex-start; gap: 5px;">
					<h5 style="margin-bottom: 5px; font-weight: bold;">' .
				ucfirst($methodName) .
				'</h5>
					<a href="' .
				url("admin/{$relatedModelUrlSegment}/create?{$foreignKey}=" . $id) .
				'" 
					class="btn btn-sm btn-primary"
					style="padding: 4px 10px; font-size: 13px;">
						<i class="la la-plus"></i> ' .
				trans("backpack::crud.add") .
				ucfirst($methodName) .
				'
					</a>
					' .
				$relationList .
				'
				</div>
				<style>
					.relation-link {
						text-decoration: none;
						color: #007bff;
						font-size: 14px;
						transition: color 0.2s ease-in-out;
					}
					.relation-link:hover {
						color: #0056b3;
						text-decoration: underline;
					}
				</style>',
			"wrapper" => ["class" => "form-group col-md-12"],
			"tab" => trans("backpack::crud.associations"),
		]);
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
		$primaryAttribute = collect([
			"email",
			"transaction_id",
			"import",
			"name",
			"title_it",
			"title",
			"title_en",
			"id",
		])->first(fn($column) => Schema::hasColumn($tableModel, $column), "id");

		// Create a column for the related model name with links
		CRUD::column($methodName)
			->type("custom_html")
			->entity($methodName)
			->model($relatedModelClass)
			->value(function ($entry) use ($formattedRelatedModelName, $methodName, $primaryAttribute, $backendUrl) {
				$links = [];
				foreach ($entry->$methodName as $elem) {
					$displayValue = $elem->$primaryAttribute ?: $elem->id; // Ensure fallback to ID if attribute is missing

					$links[] =
						'<a target="_blank" href="' .
						$backendUrl .
						"/" .
						$formattedRelatedModelName .
						"/" .
						$elem->id .
						'/edit">' .
						($primaryAttribute == "import" ? "€" : "") .
						$displayValue .
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
				->value(function ($entry) use ($methodName, $primaryAttribute, $backendUrl) {
					$pivotTableName = $entry->$methodName()->getTable();
					$pivotTableName = str_replace("_", "-", $pivotTableName);
					$links = [];
					foreach ($entry->$methodName as $elem) {
						$displayValue = $elem->$primaryAttribute ?: $elem->id; // Ensure fallback to ID if attribute is missing
						$percentage = $elem->pivot->percentage;
						$pivotId = $elem->pivot->id;
						$links[] =
							'<a target="_blank" href="' .
							$backendUrl .
							"/" .
							$pivotTableName .
							"/" .
							$pivotId .
							'/edit">' .
							$displayValue .
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

	public function sort(Request $request, $modelName)
	{
		$modelName = ucfirst($modelName);
		$newOrder = $request->newSortOrder;
		$newOrderArray = explode(",", $newOrder);
		$model = "App\\Models\\$modelName";
		for ($i = 0; $i < count($newOrderArray); $i++) {
			$note = $model::find($newOrderArray[$i]);
			$note->order = $i + 1;
			$note->save();
		}
		return redirect()->back();
	}
}
