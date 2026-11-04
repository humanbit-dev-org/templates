<?php

namespace App\Support\Backpack\Handler;

use Backpack\CRUD\app\Library\CrudPanel\CrudColumn;
use Backpack\CRUD\app\Library\CrudPanel\CrudField;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class FieldTypeHandler
{
	/**
	 * Per-table and per-column overrides for generated Backpack field types.
	 *
	 * @var array<string, array<string, string>>
	 */
	private const FIELD_TYPE_OVERRIDES = [
		"translates" => [
			"it" => "richtext_with_vars",
			"en" => "richtext_with_vars",
			"text_it" => "richtext_with_vars",
			"text_en" => "richtext_with_vars",
		],
	];

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
	 * @param  string  $columnType  The database column type
	 * @return string The corresponding form field type
	 */
	public static function getFieldType($columnType, ?string $table = null, ?string $column = null)
	{
		if ($table !== null && $column !== null && isset(self::FIELD_TYPE_OVERRIDES[$table][$column])) {
			return self::FIELD_TYPE_OVERRIDES[$table][$column];
		}

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
	 * @param  CrudColumn  $field  Field to modify
	 * @param  string  $column  Column name
	 */
	public static function handleSpecificTypeView($field, $columnType, $column)
	{
		$format = self::resolveListColumnNumberFormat($columnType, $column);

		$field->thousands_sep($format["thousands_sep"]);
		$field->dec_point($format["dec_point"]);
		$field->decimals($format["decimals"]);

		if ($format["prefix"] !== "") {
			$field->prefix($format["prefix"]);
		}

		if ($format["suffix"] !== "") {
			$field->suffix($format["suffix"]);
		}

		if ($column === "order") {
			CRUD::orderBy("order", "asc");
		}
	}

	/**
	 * Format a raw column value for list display (mirrors Backpack number column + handleSpecificTypeView).
	 */
	public static function formatListColumnValue(mixed $value, string $columnType, string $column): string
	{
		if ($value === null || $value === "") {
			return "";
		}

		$fieldType = self::getFieldType($columnType, null, $column);

		// The first list column is rendered as custom_html to carry the edit
		// link, so it never reaches Backpack's date/datetime column views.
		// Format it here with the same locale and config, otherwise a table
		// whose first column is a date shows the raw "2026-05-29".
		if ($fieldType === "date" || $fieldType === "datetime") {
			$format = backpack_theme_config($fieldType === "date" ? "default_date_format" : "default_datetime_format");

			try {
				return Carbon::parse($value)->locale(App::getLocale())->isoFormat($format);
			} catch (\Exception) {
				return is_scalar($value) ? (string) $value : "";
			}
		}

		if ($fieldType !== "number" || !is_numeric($value)) {
			return is_scalar($value) ? (string) $value : (json_encode($value, JSON_UNESCAPED_UNICODE) ?: "");
		}

		$format = self::resolveListColumnNumberFormat($columnType, $column);
		$formatted = number_format((float) $value, $format["decimals"], $format["dec_point"], $format["thousands_sep"]);

		return $format["prefix"] . $formatted . $format["suffix"];
	}

	/**
	 * Number formatting rules shared by list columns and first-column edit links.
	 *
	 * @return array{decimals: int, dec_point: string, thousands_sep: string, prefix: string, suffix: string}
	 */
	private static function resolveListColumnNumberFormat(string $columnType, string $column): array
	{
		$decimals = 0;
		if ($columnType === "decimal" || $columnType === "float" || $columnType === "double") {
			$decimals = 2;
			if (str_contains($column, "tan") || str_contains($column, "taeg")) {
				$decimals = 3;
			}
		}

		$prefix = self::isCurrencyColumn($column) ? "€" : "";
		$suffix =
			str_contains($column, "percentuale") || str_contains($column, "tan") || str_contains($column, "taeg") ? "%" : "";

		return [
			"decimals" => $decimals,
			"dec_point" => ",",
			"thousands_sep" => ".",
			"prefix" => $prefix,
			"suffix" => $suffix,
		];
	}

<<<<<<< HEAD
	/**
	 * Whether a column name represents a monetary amount.
	 */
	private static function isCurrencyColumn(string $column): bool
	{
		return str_contains($column, "importo") ||
			str_contains($column, "importo_transato") ||
			str_contains($column, "trattenuta") ||
			str_contains($column, "interessi") ||
			str_contains($column, "costo") ||
			str_contains($column, "_atp") ||
			str_contains($column, "forecast") ||
			str_contains($column, "baseline");
	}
=======
    /**
     * Whether a column name represents a monetary amount.
     */
    private static function isCurrencyColumn(string $column): bool
    {
        return str_contains($column, 'importo')
            || str_contains($column, 'prezzo')
            || str_contains($column, 'price')
            || str_contains($column, 'amount')
            || str_contains($column, 'interessi')
            || str_contains($column, 'costo');
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

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
	 * @param  CrudField  $field  Field to modify
	 * @param  string  $fieldType  Current field type
	 * @param  string  $column  Column name
	 * @param  string  $table  Table name
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
		} elseif (self::isCurrencyColumn($column)) {
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
			if ($column == "tan" || $column == "taeg" || $column == "max_tan" || $column == "max_taeg") {
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
