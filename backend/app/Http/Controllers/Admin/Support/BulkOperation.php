<?php

namespace App\Http\Controllers\Admin\Support;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BulkOperation extends Controller
{
	private const BULK_BOOLEAN_FIELD_BLACKLIST = ["token_verified", "email_verified", "is_admin", "remember_token"];

	public function bulkDelete(Request $request, string $crud)
	{
		$modelClass = $this->resolveModelClass($crud);

		if ($modelClass === null) {
			return response()->json([
				"success" => false,
				"message" => "Model not found.",
			]);
		}

		$model = new $modelClass();
		$ids = $this->normalizeIds($request->input("ids"), $model);

		if ($ids === []) {
			return response()->json([
				"success" => false,
				"message" => "No valid IDs provided.",
			]);
		}

		$summary = ["ok" => 0, "skipped" => 0, "errors" => 0];
		$details = [];

		foreach ($ids as $id) {
			$entry = $modelClass::query()->whereKey($id)->first();

			if ($entry === null) {
				$summary["skipped"]++;
				$details[] = [
					"id" => $id,
					"reason" => __("backpack::crud.bulk_reason_not_found"),
				];

				continue;
			}

			try {
				$entry->delete();
				$summary["ok"]++;
			} catch (QueryException $exception) {
				$summary["errors"]++;
				$details[] = [
					"id" => $id,
					"reason" => $this->mapDeleteError($exception),
				];

				Log::warning("Bulk delete failed", [
					"crud" => $crud,
					"id" => $id,
					"error" => $exception->getMessage(),
				]);
			} catch (\Throwable $exception) {
				$summary["errors"]++;
				$details[] = [
					"id" => $id,
					"reason" => __("backpack::crud.bulk_reason_unexpected"),
				];

				Log::error("Unexpected bulk delete error", [
					"crud" => $crud,
					"id" => $id,
					"error" => $exception->getMessage(),
				]);
			}
		}

		return response()->json([
			"success" => $summary["ok"] > 0,
			"summary" => $summary,
			"details" => $details,
			"message" => __("backpack::crud.bulk_action_processed"),
		]);
	}

	public function bulkDuplicate(Request $request, string $crud)
	{
		$modelClass = $this->resolveModelClass($crud);

		if ($modelClass === null) {
			return response()->json([
				"success" => false,
				"message" => "Model not found.",
			]);
		}

		$model = new $modelClass();
		$ids = $this->normalizeIds($request->input("ids"), $model);

		if ($ids === []) {
			return response()->json([
				"success" => false,
				"message" => "No valid IDs provided.",
			]);
		}

		$table = $model->getTable();
		$uniqueColumns = $this->getUniqueColumnsMetadata($table);

		$summary = ["ok" => 0, "skipped" => 0, "errors" => 0];
		$details = [];
		$newEntries = [];

		foreach ($ids as $id) {
			$entry = $modelClass::query()->whereKey($id)->first();

			if ($entry === null) {
				$summary["skipped"]++;
				$details[] = [
					"id" => $id,
					"reason" => __("backpack::crud.bulk_reason_not_found"),
				];

				continue;
			}

			$clone = $entry->replicate();

			$cannotClone = false;
			foreach ($uniqueColumns as $column => $columnMeta) {
				if (!array_key_exists($column, $clone->getAttributes())) {
					continue;
				}

				if ($clone->{$column} === null) {
					continue;
				}

				if ($columnMeta["nullable"]) {
					$clone->{$column} = null;

					continue;
				}

				$cannotClone = true;
				$summary["skipped"]++;
				$details[] = [
					"id" => $id,
					"reason" => __("backpack::crud.bulk_reason_unique_not_nullable", ["field" => $column]),
				];
				break;
			}

			if ($cannotClone) {
				continue;
			}

			if (isset($clone->order) && is_numeric($clone->order)) {
				$clone->order = (int) $clone->order + 1;
			}

			try {
				$clone->save();
				$newEntries[] = $clone->getKey();
				$summary["ok"]++;
			} catch (QueryException $exception) {
				$summary["errors"]++;
				$details[] = [
					"id" => $id,
					"reason" => $this->mapDuplicateError($exception),
				];

				Log::warning("Bulk duplicate failed", [
					"crud" => $crud,
					"id" => $id,
					"error" => $exception->getMessage(),
				]);
			} catch (\Throwable $exception) {
				$summary["errors"]++;
				$details[] = [
					"id" => $id,
					"reason" => __("backpack::crud.bulk_reason_unexpected"),
				];

				Log::error("Unexpected bulk duplicate error", [
					"crud" => $crud,
					"id" => $id,
					"error" => $exception->getMessage(),
				]);
			}
		}

		return response()->json([
			"success" => $summary["ok"] > 0,
			"summary" => $summary,
			"details" => $details,
			"new_entries" => $newEntries,
			"message" => __("backpack::crud.bulk_action_processed"),
		]);
	}

	public function bulkFields(string $crud)
	{
		$modelClass = $this->resolveModelClass($crud);

		if ($modelClass === null) {
			return response()->json([
				"success" => false,
				"fields" => [],
				"message" => "Model not found.",
			]);
		}

		$model = new $modelClass();
		$table = $model->getTable();
		$columns = $this->getColumnsMetadata($table);
		$fields = [];
		$bulkConfig = (array) config("backpack.bulk_actions.models", []);
		$modelBulkConfig = (array) ($bulkConfig[$modelClass] ?? []);

		foreach ($columns as $column) {
			if (in_array($column["COLUMN_NAME"], ["id", "created_at", "updated_at", "deleted_at"], true)) {
				continue;
			}

			$dataType = strtolower((string) $column["DATA_TYPE"]);
			$columnType = strtolower((string) $column["COLUMN_TYPE"]);
			$name = (string) $column["COLUMN_NAME"];
			$label = Str::headline($name);

			if (in_array($name, self::BULK_BOOLEAN_FIELD_BLACKLIST, true)) {
				continue;
			}

			if ($this->isBooleanColumn($dataType, $columnType)) {
				$rawFieldConfig = (array) ($modelBulkConfig["boolean"][$name] ?? []);
				$labelsConfig = (array) ($rawFieldConfig["labels"] ?? []);
				if ($labelsConfig === []) {
					// Backward-compatible: allow the old schema
					// boolean[field] => ['1' => '...', '0' => '...']
					$labelsConfig = $rawFieldConfig;
				}

				$iconsConfig = (array) ($rawFieldConfig["icons"] ?? ($rawFieldConfig["action_icons"] ?? []));
				$colorsConfig = (array) ($rawFieldConfig["colors"] ?? ($rawFieldConfig["action_colors"] ?? []));

				$labelsConfig = array_intersect_key($labelsConfig, array_flip(["1", "0"]));
				$iconsConfig = array_intersect_key($iconsConfig, array_flip(["1", "0"]));
				$colorsConfig = array_intersect_key($colorsConfig, array_flip(["1", "0"]));

				$allowedColors = ["danger", "warning", "success", "info"];
				$actionColors = [
					"1" => in_array(strtolower((string) ($colorsConfig["1"] ?? "")), $allowedColors, true)
						? strtolower((string) $colorsConfig["1"])
						: "",
					"0" => in_array(strtolower((string) ($colorsConfig["0"] ?? "")), $allowedColors, true)
						? strtolower((string) $colorsConfig["0"])
						: "",
				];

				$fields[] = [
					"name" => $name,
					"label" => $label,
					"type" => "boolean",
					"options" => [
						["value" => "1", "label" => __("backpack::crud.bulk_value_true")],
						["value" => "0", "label" => __("backpack::crud.bulk_value_false")],
					],
					"action_labels" => [
						"1" => (string) ($labelsConfig["1"] ?? ""),
						"0" => (string) ($labelsConfig["0"] ?? ""),
					],
					"action_icons" => [
						"1" => (string) ($iconsConfig["1"] ?? ""),
						"0" => (string) ($iconsConfig["0"] ?? ""),
					],
					"action_colors" => $actionColors,
				];

				continue;
			}

			if ($dataType === "enum" || str_starts_with($columnType, "enum(")) {
				$enumValues = $this->parseEnumValues($columnType);
				if ($enumValues === []) {
					continue;
				}

				$enumConfig = (array) ($modelBulkConfig["enum"][$name] ?? null ?: []);
				$optionLabels = (array) ($enumConfig["options"] ?? null ?: []);
				$applyIcon = (string) ($enumConfig["apply_icon"] ?? ($enumConfig["icon"] ?? ""));
				$applyColor = strtolower((string) ($enumConfig["apply_color"] ?? ($enumConfig["color"] ?? "")));

				$allowedColors = ["danger", "warning", "success", "info"];
				if (!in_array($applyColor, $allowedColors, true)) {
					$applyColor = "";
				}

				$options = array_map(function (string $value) use ($optionLabels) {
					$fallbackLabel = Str::headline($value);

					return [
						"value" => $value,
						"label" => (string) ($optionLabels[$value] ?? $fallbackLabel),
					];
				}, $enumValues);

				$fields[] = [
					"name" => $name,
					"label" => $label,
					"type" => "enum",
					"options" => $options,
					"apply_label" => (string) ($enumConfig["apply_label"] ?? ""),
					"apply_icon" => $applyIcon,
					"apply_color" => $applyColor,
				];

				continue;
			}
		}

		return response()->json([
			"success" => true,
			"fields" => $fields,
		]);
	}

	public function bulkUpdate(Request $request, string $crud)
	{
		$modelClass = $this->resolveModelClass($crud);
		if ($modelClass === null) {
			return response()->json(
				[
					"success" => false,
					"message" => "Model not found.",
				],
				404
			);
		}

		$model = new $modelClass();
		$ids = $this->normalizeIds($request->input("ids"), $model);
		$field = (string) $request->input("field", "");
		$value = $request->input("value");

		if ($ids === [] || $field === "" || $value === null) {
			return response()->json(
				[
					"success" => false,
					"message" => __("backpack::crud.bulk_update_invalid_payload"),
				],
				422
			);
		}

		$fieldsResponse = $this->bulkFields($crud)->getData(true);
		$allowedFields = collect($fieldsResponse["fields"] ?? [])->keyBy("name");

		if (!$allowedFields->has($field)) {
			return response()->json(
				[
					"success" => false,
					"message" => __("backpack::crud.bulk_update_field_not_allowed"),
				],
				422
			);
		}

		$fieldConfig = $allowedFields->get($field);
		$availableValues = collect($fieldConfig["options"] ?? [])
			->pluck("value")
			->map(fn($item) => (string) $item)
			->all();

		if (!in_array((string) $value, $availableValues, true)) {
			return response()->json(
				[
					"success" => false,
					"message" => __("backpack::crud.bulk_update_value_not_allowed"),
				],
				422
			);
		}

		$normalizedValue = $fieldConfig["type"] === "boolean" ? ((string) $value === "1" ? 1 : 0) : (string) $value;

		$summary = ["ok" => 0, "skipped" => 0, "errors" => 0];
		$details = [];

		foreach ($ids as $id) {
			$entry = $modelClass::query()->whereKey($id)->first();
			if ($entry === null) {
				$summary["skipped"]++;
				$details[] = [
					"id" => $id,
					"reason" => __("backpack::crud.bulk_reason_not_found"),
				];

				continue;
			}

			try {
				$entry->{$field} = $normalizedValue;
				$entry->save();
				$summary["ok"]++;
			} catch (QueryException $exception) {
				$summary["errors"]++;
				$details[] = [
					"id" => $id,
					"reason" => $this->mapGenericQueryError($exception),
				];

				Log::warning("Bulk update failed", [
					"crud" => $crud,
					"id" => $id,
					"field" => $field,
					"error" => $exception->getMessage(),
				]);
			} catch (\Throwable $exception) {
				$summary["errors"]++;
				$details[] = [
					"id" => $id,
					"reason" => __("backpack::crud.bulk_reason_unexpected"),
				];

				Log::error("Unexpected bulk update error", [
					"crud" => $crud,
					"id" => $id,
					"field" => $field,
					"error" => $exception->getMessage(),
				]);
			}
		}

		return response()->json([
			"success" => $summary["ok"] > 0,
			"summary" => $summary,
			"details" => $details,
			"message" => __("backpack::crud.bulk_action_processed"),
		]);
	}

	private function resolveModelClass(string $crud): ?string
	{
		$candidates = ["App\\Models\\" . Str::studly(Str::singular($crud)), "App\\Models\\" . Str::studly($crud)];

		foreach ($candidates as $candidate) {
			if (class_exists($candidate)) {
				return $candidate;
			}
		}

		return null;
	}

	private function normalizeIds(mixed $ids, object $model): array
	{
		if (!is_array($ids)) {
			return [];
		}

		$keyType = method_exists($model, "getKeyType") ? (string) $model->getKeyType() : "int";
		$isIntegerKey = in_array(strtolower($keyType), ["int", "integer"], true);

		return collect($ids)
			->map(function ($id) use ($isIntegerKey) {
				if (is_array($id) || is_object($id)) {
					return null;
				}

				$normalized = trim((string) $id);
				if ($normalized === "") {
					return null;
				}

				if ($isIntegerKey) {
					return ctype_digit($normalized) ? (int) $normalized : null;
				}

				return $normalized;
			})
			->filter(fn($id) => $id !== null && $id !== "")
			->unique()
			->values()
			->all();
	}

	private function getUniqueColumnsMetadata(string $table): array
	{
		$uniqueRows = DB::select(
			'SELECT DISTINCT COLUMN_NAME
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND NON_UNIQUE = 0
               AND INDEX_NAME <> "PRIMARY"',
			[$table]
		);

		$nullableRows = DB::select(
			'SELECT COLUMN_NAME, IS_NULLABLE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?',
			[$table]
		);

		$nullableMap = collect($nullableRows)
			->mapWithKeys(fn($row) => [(string) $row->COLUMN_NAME => strtoupper((string) $row->IS_NULLABLE) === "YES"])
			->all();

		$result = [];
		foreach ($uniqueRows as $row) {
			$column = (string) $row->COLUMN_NAME;
			$result[$column] = [
				"nullable" => (bool) ($nullableMap[$column] ?? false),
			];
		}

		return $result;
	}

	private function getColumnsMetadata(string $table): array
	{
		$columns = DB::select(
			'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?',
			[$table]
		);

		return array_map(function ($row) {
			return [
				"COLUMN_NAME" => (string) $row->COLUMN_NAME,
				"DATA_TYPE" => (string) $row->DATA_TYPE,
				"COLUMN_TYPE" => (string) $row->COLUMN_TYPE,
			];
		}, $columns);
	}

	private function parseEnumValues(string $columnType): array
	{
		if (!str_starts_with($columnType, "enum(")) {
			return [];
		}

		preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $columnType, $matches);

		return array_map(static fn($item) => stripcslashes($item), $matches[1] ?? []);
	}

	private function isBooleanColumn(string $dataType, string $columnType): bool
	{
		if ($dataType === "boolean") {
			return true;
		}

		return $dataType === "tinyint" && ($columnType === "tinyint(1)" || $columnType === "tinyint(1) unsigned");
	}

	private function mapDeleteError(QueryException $exception): string
	{
		$message = $exception->getMessage();
		if (str_contains($message, "1451")) {
			return __("backpack::crud.bulk_reason_fk_constraint");
		}

		return $this->mapGenericQueryError($exception);
	}

	private function mapDuplicateError(QueryException $exception): string
	{
		$message = $exception->getMessage();
		if (str_contains($message, "1062")) {
			return __("backpack::crud.bulk_reason_duplicate_conflict");
		}
		if (str_contains($message, "1048")) {
			return __("backpack::crud.bulk_reason_not_null_violation");
		}

		return $this->mapGenericQueryError($exception);
	}

	private function mapGenericQueryError(QueryException $exception): string
	{
		$message = $exception->getMessage();

		if (str_contains($message, "1451")) {
			return __("backpack::crud.bulk_reason_fk_constraint");
		}
		if (str_contains($message, "1062")) {
			return __("backpack::crud.bulk_reason_duplicate_conflict");
		}
		if (str_contains($message, "1048")) {
			return __("backpack::crud.bulk_reason_not_null_violation");
		}

		return __("backpack::crud.bulk_reason_generic_db");
	}
}
