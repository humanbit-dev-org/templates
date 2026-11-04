<?php

namespace App\Services\Translate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TranslateVariableService
{
	// Matches both {$model.field} (new) and {$field} (backward compat)
	private const PLACEHOLDER_PATTERN = '/\{\$([a-zA-Z_][a-zA-Z0-9_]*)(?:\.([a-zA-Z_][a-zA-Z0-9_]*))?\}/';

	/**
	 * Return all registered dynamic variables.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function getRegistry(): array
	{
		$modelMap = config("translate_vars.models", []);
		$blacklist = collect(config("translate_vars.blacklist", []))
			->map(static fn($field): string => (string) $field)
			->all();

		$registry = [];

		foreach ($modelMap as $modelKey => $modelClass) {
			if (!is_string($modelClass) || !class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
				continue;
			}

			/** @var Model $model */
			$model = app($modelClass);
			$fillable = $model->getFillable();

			foreach ($fillable as $field) {
				if (!is_string($field) || in_array($field, $blacklist, true)) {
					continue;
				}

				// Keys always qualified as "model.field" — no collision possible.
				$key = (string) $modelKey . "." . $field;
				$registry[$key] = self::buildDefinition((string) $modelKey, $field);
			}
		}

		$overrides = config("translate_vars.overrides", []);
		if (is_array($overrides)) {
			foreach ($overrides as $key => $override) {
				if (!is_string($key) || !is_array($override)) {
					continue;
				}

				$base = $registry[$key] ?? self::buildDefinition("generic", $key);
				$registry[$key] = array_merge($base, $override);
			}
		}

		return $registry;
	}

	/**
	 * Return registry grouped by model key.
	 *
	 * Includes frontend-only variables defined in translate_vars.frontend_vars
	 * as a separate 'frontend' group.
	 *
	 * @return array<string, array<string, array<string, string>>>
	 */
	public static function getRegistryGroupedByModel(): array
	{
		$grouped = [];

		foreach (self::getRegistry() as $key => $definition) {
			$model = $definition["model"] ?? "generic";
			$grouped[$model][$key] = $definition;
		}

		// Append frontend-only variables as a separate group
		$frontendVars = config("translate_vars.frontend_vars", []);
		if (is_array($frontendVars) && !empty($frontendVars)) {
			foreach ($frontendVars as $key => $definition) {
				if (!is_string($key) || !is_array($definition)) {
					continue;
				}

				// Frontend vars also use qualified key: "frontend.key"
				$qualifiedKey = "frontend." . $key;
				$grouped["frontend"][$qualifiedKey] = array_merge(
					[
						"model" => "frontend",
						"field" => $key,
						"label_it" => $key,
						"label_en" => $key,
						"description" => "",
					],
					$definition
				);
			}
		}

		return $grouped;
	}

	/**
	 * Build a default variable definition from model/field names.
	 *
	 * @return array<string, string>
	 */
	private static function buildDefinition(string $model, string $field): array
	{
		$label = Str::headline($field);

		return [
			"model" => $model,
			"field" => $field,
			"label_it" => $label,
			"label_en" => $label,
			"description" => "Auto-generated from {$model}.{$field}",
		];
	}
}
