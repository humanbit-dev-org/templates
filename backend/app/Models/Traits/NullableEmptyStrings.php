<?php

namespace App\Models\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Trait to automatically convert empty strings to null for nullable columns
 * Uses schema introspection with caching for performance
 */
trait NullableEmptyStrings
{
	/**
	 * Boot the trait - register saving event
	 */
	public static function bootNullableEmptyStrings(): void
	{
		static::saving(function ($model) {
			$model->convertEmptyStringsToNull();
		});
	}

	/**
	 * Convert empty strings to null for all nullable columns
	 */
	protected function convertEmptyStringsToNull(): void
	{
		$nullableColumns = $this->getNullableColumns();

		foreach ($nullableColumns as $column) {
			if ($this->getAttribute($column) === "") {
				$this->setAttribute($column, null);
			}
		}
	}

	/**
	 * Get nullable columns for this model's table (cached)
	 *
	 * @return array List of nullable column names
	 */
	protected function getNullableColumns(): array
	{
		$table = $this->getTable();
		$cacheKey = "nullable_columns_{$table}";

		return Cache::remember($cacheKey, 3600, function () use ($table) {
			if (!Schema::hasTable($table)) {
				return [];
			}

			return collect(Schema::getColumns($table))
				->filter(fn($col) => $col["nullable"] === true)
				->pluck("name")
				->toArray();
		});
	}
}
