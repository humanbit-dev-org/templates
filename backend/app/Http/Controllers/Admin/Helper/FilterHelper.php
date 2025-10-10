<?php

namespace App\Http\Controllers\Admin\Helper;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use App\Http\Controllers\Admin\Helper\Core\FieldConfigHandler;

class FilterHelper
{
	/**
	 * Get filter configuration for the CRUD list view
	 */
	public static function getFilterConfiguration(CrudPanel $crud): array
	{
		// Check if any filters are applied (excluding pagination)
		$hasFilters = collect(request()->except(["page", "persistent-table", "_token"]))
			->filter(function ($value) {
				return $value !== null && $value !== "";
			})
			->isNotEmpty();

		// Get all columns excluding pagination and other non-filterable fields
		$columns = collect($crud->columns())->filter(function ($column) {
			return isset($column["name"]) &&
				!in_array($column["name"], ["created_at", "updated_at", "order"]) &&
				(!isset($column["relation_type"]) || $column["relation_type"] !== "HasMany");
		});

		// Get table name for select field detection
		$model = $crud->model;
		$tableName = is_string($model) ? (new $model())->getTable() : $model->getTable();

		// Group columns by type
		$textColumns = $columns->filter(function ($column) use ($tableName) {
			$type = $column["type"] ?? "";
			return !str_ends_with($column["name"], "_id") &&
				!str_ends_with($column["name"], "_path") &&
				$type != "switch" &&
				$type != "date" &&
				$type != "datetime" &&
				$column["name"] !== "page" &&
				!FieldConfigHandler::isSelectField($column["name"], $tableName);
		});

		// Date filters
		$dateColumns = $columns->filter(function ($column) {
			$type = $column["type"] ?? "";
			return $type == "date" || $type == "datetime";
		});

		// Path fields
		$pathFields = $columns->filter(function ($column) {
			return str_ends_with($column["name"], "_path");
		});

		// Boolean filters (excluding path fields)
		$booleanColumns = $columns->filter(function ($column) {
			return ($column["type"] ?? "") == "switch" && !str_ends_with($column["name"], "_path");
		});

		// Select filters (status, custom enums, etc.)
		$selectColumns = $columns->filter(function ($column) use ($tableName) {
			return FieldConfigHandler::isSelectField($column["name"], $tableName);
		});

		$relationColumns = $columns->filter(function ($column) {
			return str_ends_with($column["name"], "_id") || $column["name"] === "page";
		});

		// Get count of applied filters per section
		$textFilterCount = self::getTextFilterCount($textColumns);
		$dateFilterCount = self::getDateFilterCount($dateColumns);
		$booleanFilterCount = self::getBooleanFilterCount($booleanColumns);
		$selectFilterCount = self::getSelectFilterCount($selectColumns);
		$pathFilterCount = self::getPathFilterCount($pathFields);
		$relationFilterCount = self::getRelationFilterCount($relationColumns);

		// Check if specific sections have active filters
		$hasTextFilters = $textFilterCount > 0;
		$hasDateFilters = $dateFilterCount > 0;
		$hasBooleanFilters = $booleanFilterCount > 0;
		$hasSelectFilters = $selectFilterCount > 0;
		$hasPathFilters = $pathFilterCount > 0;
		$hasRelationFilters = $relationFilterCount > 0;

		// Determine if multiple filter sections are active
		$activeFilterSections = 0;
		if ($hasTextFilters) {
			$activeFilterSections++;
		}
		if ($hasDateFilters) {
			$activeFilterSections++;
		}
		if ($hasBooleanFilters) {
			$activeFilterSections++;
		}
		if ($hasSelectFilters) {
			$activeFilterSections++;
		}
		if ($hasPathFilters) {
			$activeFilterSections++;
		}
		if ($hasRelationFilters) {
			$activeFilterSections++;
		}

		// Only auto-open sections if exactly one section has filters
		$autoOpenSection = $activeFilterSections == 1;

		// Check if there are any non-relation filters active (for smart filtering of relation options)
		$hasNonRelationFilters =
			$hasTextFilters || $hasDateFilters || $hasBooleanFilters || $hasSelectFilters || $hasPathFilters;

		// Check if there are ANY filters active (for smart counter display)
		// This includes relation filters too!
		$hasAnyActiveFilters = $hasFilters;

		return [
			"hasFilters" => $hasFilters,
			"columns" => $columns,
			"textColumns" => $textColumns,
			"dateColumns" => $dateColumns,
			"pathFields" => $pathFields,
			"booleanColumns" => $booleanColumns,
			"selectColumns" => $selectColumns,
			"relationColumns" => $relationColumns,
			"tableName" => $tableName,
			"textFilterCount" => $textFilterCount,
			"dateFilterCount" => $dateFilterCount,
			"booleanFilterCount" => $booleanFilterCount,
			"selectFilterCount" => $selectFilterCount,
			"pathFilterCount" => $pathFilterCount,
			"relationFilterCount" => $relationFilterCount,
			"hasTextFilters" => $hasTextFilters,
			"hasDateFilters" => $hasDateFilters,
			"hasBooleanFilters" => $hasBooleanFilters,
			"hasSelectFilters" => $hasSelectFilters,
			"hasPathFilters" => $hasPathFilters,
			"hasRelationFilters" => $hasRelationFilters,
			"hasNonRelationFilters" => $hasNonRelationFilters,
			"hasAnyActiveFilters" => $hasAnyActiveFilters,
			"openTextFilters" => $hasTextFilters && $autoOpenSection,
			"openDateFilters" => $hasDateFilters && $autoOpenSection,
			"openBooleanFilters" => $hasBooleanFilters && $autoOpenSection,
			"openSelectFilters" => $hasSelectFilters && $autoOpenSection,
			"openPathFilters" => $hasPathFilters && $autoOpenSection,
			"openRelationFilters" => $hasRelationFilters && $autoOpenSection,
		];
	}

	/**
	 * Get active filters for display
	 */
	public static function getActiveFilters(Collection $columns): Collection
	{
		return collect(request()->except(["page", "persistent-table"]))->filter(function ($value, $key) {
			return $value !== null && $value !== "" && $key !== "_token";
		});
	}

	/**
	 * Get display value for active filter
	 */
	public static function getFilterDisplayValue($key, $value, $columns, $crud): string
	{
		$columnName = $key === "page_filter" ? "page" : $key;
		$column = $columns->firstWhere("name", $columnName);

		if (!$column) {
			return $value;
		}

		// Get table name
		$model = $crud->model;
		$tableName = is_string($model) ? (new $model())->getTable() : $model->getTable();

		// Handle path fields
		if (str_ends_with($columnName, "_path")) {
			return trans("backpack::filters.with_file");
		}

		// Handle select fields (status, enums, etc.)
		elseif (FieldConfigHandler::isSelectField($columnName, $tableName)) {
			$config = FieldConfigHandler::getSelectFieldConfig($columnName, $tableName);
			return $config["options"][$value] ?? $value;
		}

		// Handle relation fields
		elseif (str_ends_with($columnName, "_id")) {
			return self::getRelationDisplayValue($columnName, $value, $crud);
		}

		// Handle boolean fields
		elseif (($column["type"] ?? "") == "switch") {
			return $value == "1" ? trans("backpack::filters.yes") : trans("backpack::filters.no");
		}

		return $value;
	}

	/**
	 * Get options for select fields (status, custom enums, etc.)
	 */
	public static function getSelectOptions($column, $tableName): array
	{
		$config = FieldConfigHandler::getSelectFieldConfig($column["name"], $tableName);
		return $config ? $config["options"] : [];
	}

	/**
	 * Get options for relation filter with efficient counting and dynamic filtering
	 */
	public static function getRelationOptions($column, $crud): array
	{
		// First try to get the related model from the actual relation method
		$relationName = str_replace("_id", "", $column["name"]);
		$relatedModel = null;

		// Check if the relation method exists in the model
		if (method_exists($crud->model, $relationName)) {
			try {
				$relationQuery = $crud->model->$relationName();
				$relatedModel = get_class($relationQuery->getRelated());
			} catch (\Exception $e) {
				// Fallback to the old method if relation method fails
				$relatedModelName = Str::studly($relationName);
				$relatedModel = "App\Models\\" . $relatedModelName;
			}
		} else {
			// Fallback to the old method
			$relatedModelName = Str::studly($relationName);
			$relatedModel = "App\Models\\" . $relatedModelName;
		}

		$options = [];

		if (class_exists($relatedModel)) {
			// Get current model info for efficient counting
			$currentTableName = $crud->model->getTable();
			$foreignKeyColumn = $relationName . "_id";

			// Get all related records first
			$relatedItems = call_user_func([$relatedModel, "all"]);

			// Build query for counting with active filters applied
			$countsQuery = DB::table($currentTableName)
				->select($foreignKeyColumn, DB::raw("COUNT(*) as relation_count"))
				->whereNotNull($foreignKeyColumn);

			// Apply active filters to the count query (excluding the current relation filter and pagination)
			$activeFilters = collect(
				request()->except([
					"page",
					"persistent-table",
					"_token",
					$column["name"], // Exclude current filter
					"page_filter",
				])
			);

			foreach ($activeFilters as $filterKey => $filterValue) {
				if ($filterValue === "" || $filterValue === null) {
					continue;
				}

				// Check if column exists in the table
				if (!\Illuminate\Support\Facades\Schema::hasColumn($currentTableName, $filterKey)) {
					continue;
				}

				// Apply the same filter logic as FilterHandler
				if (str_ends_with($filterKey, "_path")) {
					// Path field filter
					if ($filterValue === "1") {
						$countsQuery->whereNotNull($filterKey)->where($filterKey, "!=", "");
					}
				} elseif (str_ends_with($filterKey, "_id")) {
					// Relation filter
					$countsQuery->where($filterKey, "=", (int) $filterValue);
				} else {
					// Get column type
					$columnType = \Illuminate\Support\Facades\Schema::getColumnType($currentTableName, $filterKey);

					if ($columnType === "boolean" || $columnType === "tinyint") {
						// Boolean filter
						if ($filterValue === "1" || $filterValue === "0") {
							$countsQuery->where($filterKey, "=", (int) $filterValue);
						}
					} else {
						// Text filter with LIKE
						$countsQuery->where($filterKey, "LIKE", "%" . $filterValue . "%");
					}
				}
			}

			// Get counts grouped by foreign key
			$counts = $countsQuery->groupBy($foreignKeyColumn)->pluck("relation_count", $foreignKeyColumn);

			// Check if there are active filters (to decide whether to hide 0-count options)
			$hasActiveFilters = $activeFilters
				->filter(function ($value) {
					return $value !== null && $value !== "";
				})
				->isNotEmpty();

			$options = $relatedItems
				->mapWithKeys(function ($item) use ($counts, $hasActiveFilters) {
					// Get count from the pre-calculated counts array
					$relationCount = $counts[$item->id] ?? 0;

					// If there are active filters and this item has 0 results, skip it
					if ($hasActiveFilters && $relationCount === 0) {
						return [];
					}

					// Check for getDisplayAttribute method first
					if (method_exists($item, "getDisplayAttribute")) {
						$displayValue = $item->getDisplayAttribute();
					} else {
						// Fallback to ID if no other field found
						$displayValue = $item->id;
					}

					// Use safe ASCII characters that work everywhere
					$displayValue .= " • [{$relationCount}]";

					return [$item->id => $displayValue];
				})
				->toArray();

			// Sort options alphabetically
			asort($options);
		}

		return $options;
	}

	/**
	 * Check if relation filter should use modal (for large datasets)
	 */
	public static function shouldUseModal($column, $crud): bool
	{
		$options = self::getRelationOptions($column, $crud);
		return count($options) > 100; // Use modal only for very large datasets
	}

	/**
	 * Get text filter count
	 */
	private static function getTextFilterCount(Collection $textColumns): int
	{
		return collect(request()->all())
			->filter(function ($value, $key) use ($textColumns) {
				return $textColumns->contains("name", $key) && $value !== null && $value !== "";
			})
			->count();
	}

	/**
	 * Get boolean filter count
	 */
	private static function getBooleanFilterCount(Collection $booleanColumns): int
	{
		return collect(request()->all())
			->filter(function ($value, $key) use ($booleanColumns) {
				return $booleanColumns->contains("name", $key) && $value !== null && $value !== "";
			})
			->count();
	}

	/**
	 * Get date filter count
	 */
	private static function getDateFilterCount(Collection $dateColumns): int
	{
		return collect(request()->all())
			->filter(function ($value, $key) use ($dateColumns) {
				return $dateColumns->contains("name", $key) && $value !== null && $value !== "";
			})
			->count();
	}

	/**
	 * Get select filter count
	 */
	private static function getSelectFilterCount(Collection $selectColumns): int
	{
		return collect(request()->all())
			->filter(function ($value, $key) use ($selectColumns) {
				return $selectColumns->contains("name", $key) && $value !== null && $value !== "";
			})
			->count();
	}

	/**
	 * Get path filter count
	 */
	private static function getPathFilterCount(Collection $pathFields): int
	{
		return collect(request()->all())
			->filter(function ($value, $key) use ($pathFields) {
				return $pathFields->contains("name", $key) && $value !== null && $value !== "";
			})
			->count();
	}

	/**
	 * Get relation filter count
	 */
	private static function getRelationFilterCount(Collection $relationColumns): int
	{
		return collect(request()->all())
			->filter(function ($value, $key) use ($relationColumns) {
				if ($key === "page_filter" && $value !== null && $value !== "") {
					return true;
				}
				return $relationColumns->contains("name", $key) && $value !== null && $value !== "";
			})
			->count();
	}

	/**
	 * Get relation display value for active filters
	 */
	private static function getRelationDisplayValue($columnName, $value, $crud): string
	{
		// Get related model using the same improved logic
		$relationName = str_replace("_id", "", $columnName);
		$relatedModel = null;

		// Check if the relation method exists in the model
		if (method_exists($crud->model, $relationName)) {
			try {
				$relationQuery = $crud->model->$relationName();
				$relatedModel = get_class($relationQuery->getRelated());
			} catch (\Exception $e) {
				// Fallback
				$relatedModelName = Str::studly($relationName);
				$relatedModel = "App\\Models\\" . $relatedModelName;
			}
		} else {
			// Fallback
			$relatedModelName = Str::studly($relationName);
			$relatedModel = "App\\Models\\" . $relatedModelName;
		}

		$displayValue = "ID: " . $value; // Default fallback

		if (class_exists($relatedModel)) {
			$item = call_user_func([$relatedModel, "find"], $value);
			if ($item) {
				// Check for getDisplayAttribute method first
				if (method_exists($item, "getDisplayAttribute")) {
					$displayValue = $item->getDisplayAttribute();
				} else {
					$displayValue = $item->id;
				}
			}
		}

		return $displayValue;
	}

	/**
	 * Convert numbers to Unicode bold characters for better visibility
	 */
	private static function convertToBoldNumbers($number): string
	{
		$boldDigits = [
			"0" => "𝟎",
			"1" => "𝟏",
			"2" => "𝟐",
			"3" => "𝟑",
			"4" => "𝟒",
			"5" => "𝟓",
			"6" => "𝟔",
			"7" => "𝟕",
			"8" => "𝟖",
			"9" => "𝟗",
		];

		$numberStr = (string) $number;
		$result = "";

		for ($i = 0; $i < strlen($numberStr); $i++) {
			$digit = $numberStr[$i];
			$result .= $boldDigits[$digit] ?? $digit;
		}

		return $result;
	}
}
