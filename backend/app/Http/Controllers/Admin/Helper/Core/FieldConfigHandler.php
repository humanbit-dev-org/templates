<?php

namespace App\Http\Controllers\Admin\Helper\Core;

use Illuminate\Support\Facades\DB;

class FieldConfigHandler
{
	/**
	 * Get field configuration for select_from_array fields
	 * This is a centralized configuration used by both FieldTypeHandler and FilterHelper
	 *
	 * @param string $column Column name
	 * @param string $table Table name
	 * @return array|null Returns ['type' => 'select_from_array', 'options' => [...]] or null
	 */
	public static function getSelectFieldConfig($column, $table)
	{
		// Status field for tiraggi table
		if (str_contains($column, "status") && $table == "tiraggi") {
			return [
				"type" => "select_from_array",
				"options" => [
					"open" => "Open",
					"requested" => "Requested",
					"accepted" => "Accepted",
					"rejected" => "Rejected",
					"paid" => "Paid",
				],
				"default" => "requested",
			];
		}

		// Status field for delibere table
		if (str_contains($column, "status") && $table == "delibere") {
			return [
				"type" => "select_from_array",
				"options" => [
					"new" => "New",
					"open" => "Open",
					"close" => "Close",
				],
				"default" => "new",
			];
		}

		// Backpack role field
		if (str_contains($column, "backpack_role")) {
			return [
				"type" => "select_from_array",
				"options" => [
					"admin" => "Admin",
				],
				"default" => "admin",
			];
		}

		// SEO metadata code field
		if ($table == "metadata" && str_contains($column, "code")) {
			return [
				"type" => "select_from_array",
				"options" => [
					"title" => "title",
					"description" => "description",
					"og_url" => "og:url",
					"og_site_name" => "og:site_name",
					"og_title" => "og:title",
					"og_description" => "og:description",
					"og_image" => "og:image",
					"og_locale" => "og:locale",
				],
			];
		}

		return null;
	}

	/**
	 * Check if a column should be a select field
	 *
	 * @param string $column Column name
	 * @param string $table Table name
	 * @return bool
	 */
	public static function isSelectField($column, $table)
	{
		return self::getSelectFieldConfig($column, $table) !== null;
	}
}
