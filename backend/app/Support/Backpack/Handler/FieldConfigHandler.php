<?php

namespace App\Support\Backpack\Handler;

use App\Models\Prodotto;
use App\Models\Tiraggio;

class FieldConfigHandler
{
	/**
	 * Get field configuration for select_from_array fields
	 * This is a centralized configuration used by both FieldTypeHandler and FilterHelper
	 *
	 * @param  string  $column  Column name
	 * @param  string  $table  Table name
	 * @return array|null Returns ['type' => 'select_from_array', 'options' => [...]] or null
	 */
	public static function getSelectFieldConfig($column, $table)
	{
		// Status field for tiraggi table
		if (str_contains($column, "status") && $table == "tiraggi") {
			$options = [];
			foreach (Tiraggio::STATUSES as $status) {
				$options[$status] = ucfirst($status);
			}

			return [
				"type" => "select_from_array",
				"options" => $options,
				"default" => "requested",
			];
		}

		// Status field for delibere table
		if (str_contains($column, "status") && $table == "delibere") {
			return [
				"type" => "select_from_array",
				"options" => [
					"open" => "Open",
					"close" => "Close",
				],
				"default" => "open",
			];
		}

		// Conguaglio method for prodotti table (enum column)
		if ($column === "metodo_conguaglio" && $table == "prodotti") {
			$options = [];
			foreach (Prodotto::METODI_CONGUAGLIO as $metodo) {
				$options[$metodo] = ucfirst($metodo);
			}

			return [
				"type" => "select_from_array",
				"options" => $options,
			];
		}

		// Repayment step months for prodotti table — all integers 1-18,
		// ProdottoRequest::stepMesiRipagamentoDivisor() enforces divisibility
		// at validation time based on the actual min/max mesi tiraggio.
		if ($column === "step_mesi_tiraggio" && $table == "prodotti") {
			$options = [];
			for ($step = 1; $step <= 18; $step++) {
				$options[$step] = $step . " mesi";
			}

			return [
				"type" => "select_from_array",
				"options" => $options,
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
	 * @param  string  $column  Column name
	 * @param  string  $table  Table name
	 * @return bool
	 */
	public static function isSelectField($column, $table)
	{
		return self::getSelectFieldConfig($column, $table) !== null;
	}
}
