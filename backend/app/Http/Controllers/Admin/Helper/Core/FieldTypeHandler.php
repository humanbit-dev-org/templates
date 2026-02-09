<?php

namespace App\Http\Controllers\Admin\Helper\Core;

use Illuminate\Support\Facades\DB;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Http\Controllers\Admin\Helper\Core\MediaHandler;
use App\Http\Controllers\Admin\Helper\Core\FieldConfigHandler;

class FieldTypeHandler
{
	/**
	 * Determines the appropriate field type based on the database column type.
	 *
	 * Mapping:
	 * - string, varchar → text
	 * - text → textarea
	 * - int, smallint, float, double, decimal → number
	 * - tinyint → switch
	 * - date → date
	 * - datetime, timestamp → datetime
	 * - bigint → break
	 * - others → text
	 *
	 * @param string $columnType The database column type
	 * @return string The corresponding form field type
	 */
	public static function getFieldType($columnType)
	{
		switch ($columnType) {
			case "string":
			case "varchar":
				return "text";
			case "text":
			case "mediumtext":
			case "longtext":
				return "textarea";
			case "int":
			case "smallint":
			case "decimal":
			case "float":
			case "double":
				return "number";
			case "tinyint":
				return "switch";
			case "date":
				return "date";
			case "datetime":
			case "timestamp":
				return "datetime";
			case "bigint":
				return "break";
			case "json":
				return "select_from_array";
			default:
				return "text";
		}
	}

	/**
	 * Handles specific field types for the CRUD List operation.
	 *
	 * Applies formatting based on column name patterns:
	 * - "import" columns get € prefix
	 * - "percentage" columns get % suffix
	 * - "order" columns get sorted ascending
	 *
	 * @param \Backpack\CRUD\app\Library\CrudPanel\CrudColumn $field Field to modify
	 * @param string $column Column name
	 */
	public static function handleSpecificTypeView($field, $columnType, $column)
	{
		$field->thousands_sep(".");
		$field->dec_point(",");

		if ($columnType == "decimal" || $columnType == "float") {
			$field->decimals(2);
			if ($column == "tan" || $column == "taeg") {
				$field->decimals(3);
			}
		}

		if (
			str_contains($column, "importo") ||
			str_contains($column, "importo_transato") ||
			str_contains($column, "trattenuta") ||
			str_contains($column, "interessi") ||
			str_contains($column, "costo") ||
			str_contains($column, "max_atp") ||
			str_contains($column, "min_atp") ||
			str_contains($column, "forecast") ||
			str_contains($column, "baseline")
		) {
			$field->prefix("€");
		}

		if ($column === "order") {
			CRUD::orderBy("order", "asc");
		}

		if (str_contains($column, "percentuale") || str_contains($column, "tan") || str_contains($column, "taeg")) {
			$field->suffix("%");
		}
	}

	/**
	 * Customizes field types based on column name patterns.
	 *
	 * Applied rules:
	 * - "upload" → upload field
	 * - "password" → password field
	 * - "email" → email field
	 * - "formatted" → summernote editor
	 * - "url" → url field
	 * - "import" → adds € prefix
	 * - "hide_" → adds hint
	 * - "percentage" → adds % suffix with min/max values
	 * - Special handling for roles, order status, and SEO fields
	 *
	 * @param \Backpack\CRUD\app\Library\CrudPanel\CrudField $field Field to modify
	 * @param string $fieldType Current field type
	 * @param string $column Column name
	 * @param string $table Table name
	 * @return string Updated field type
	 */
	public static function handleSpecificType($field, $fieldType, $columnType, $column, $table)
	{
		$isUploadField = MediaHandler::configureUploadField($column, $field);

		if ($isUploadField) {
			$fieldType = "upload";
		} elseif (str_contains($column, "password")) {
			$fieldType = "password";
		} elseif ($column === "email" || str_ends_with($column, "_email")) {
			$fieldType = "email";
		} elseif (str_contains($column, "formatted")) {
			$fieldType = "summernote";
			$field->type($fieldType);
		} elseif (str_contains($column, "url")) {
			$fieldType = "url";
		} elseif (
			str_contains($column, "importo") ||
			str_contains($column, "importo_transato") ||
			str_contains($column, "trattenuta") ||
			str_contains($column, "interessi") ||
			str_contains($column, "costo") ||
			str_contains($column, "max_atp") ||
			str_contains($column, "min_atp") ||
			str_contains($column, "forecast") ||
			str_contains($column, "baseline")
		) {
			$field->prefix("€");
		} elseif (str_contains($column, "hide_")) {
			$field->hint(trans("backpack::crud.hint_hide") . str_replace("hide_", "", $column));
		} elseif (str_contains($column, "percentuale") || str_contains($column, "tan") || str_contains($column, "taeg")) {
			$field->suffix("%");
			$field->attributes(["min" => 0, "max" => 100]);
		} elseif ($column == "order") {
			$field->attributes(["step" => 1, "min" => 1, "max" => DB::table($table)->max("order") + 1]);
			$field->default(DB::table($table)->max("order") + 1);
		} elseif (str_contains($column, "color")) {
			$fieldType = "color";
		}

		// Check if this field has a custom select configuration
		$selectConfig = FieldConfigHandler::getSelectFieldConfig($column, $table);
		if ($selectConfig !== null) {
			$fieldType = $selectConfig["type"];
			$field->options($selectConfig["options"]);
			if (isset($selectConfig["default"])) {
				$field->default($selectConfig["default"]);
			}
		}

		if ($columnType == "decimal" || $columnType == "float") {
			$field->attributes(["step" => 0.01]);
			if ($column == "tan" || $column == "taeg") {
				$field->attributes(["step" => 0.001]);
			}
		}

		$field->dec_point(",");

		// Set wrapper and field type
		$field->wrapper(["class" => "form-group col-md-6"]);
		$field->type($fieldType);
		return $fieldType;
	}
}
