<?php

namespace App\Support\Backpack;

use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Schema::hasColumn() and friends, read once per table.
 *
 * Laravel re-reads information_schema on every call, and the Backpack filters
 * ask per column inside loops: about a thousand identical queries for one list.
 * Same semantics as the Schema builder (case-insensitive names, same exception).
 *
 * ponytail: per-process memo, so a schema change needs a fresh process; every
 * deploy recreates the containers.
 */
final class SchemaCache
{
	/** @var array<string, list<array<string, mixed>>> */
	private static array $columns = [];

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function getColumns(string $table): array
	{
		return self::$columns[$table] ??= Schema::getColumns($table);
	}

	/**
	 * @return list<string>
	 */
	public static function getColumnListing(string $table): array
	{
		return array_column(self::getColumns($table), "name");
	}

	public static function hasColumn(string $table, string $column): bool
	{
		return self::find($table, $column) !== null;
	}

	public static function getColumnType(string $table, string $column, bool $fullDefinition = false): string
	{
		$found =
			self::find($table, $column) ??
			throw new InvalidArgumentException("There is no column with name '$column' on table '$table'.");

		return $fullDefinition ? $found["type"] : $found["type_name"];
	}

	public static function isNullable(string $table, string $column): bool
	{
		return (bool) (self::find($table, $column)["nullable"] ?? false);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function find(string $table, string $column): ?array
	{
		foreach (self::getColumns($table) as $value) {
			if (strtolower($value["name"]) === strtolower($column)) {
				return $value;
			}
		}

		return null;
	}
}
