<?php

namespace App\Http\Controllers\Admin\Helper\Core;

/**
 * CSV Helper - Common utilities for all CSV import operations
 * Centralized helper functions for CSV parsing, validation and formatting
 */
class CsvHelper
{
	/**
	 * Detect the delimiter used in a CSV file
	 *
	 * @param string $filePath Path to CSV file
	 * @return string Detected delimiter (default: comma)
	 */
	public static function detectDelimiter(string $filePath): string
	{
		$delimiters = [",", ";", "\t", "|", ":"];
		$counts = [];
		$firstLine = "";

		$handle = fopen($filePath, "r");
		if ($handle) {
			$firstLine = fgets($handle);
			fclose($handle);
		}

		if (empty($firstLine)) {
			return ","; // Default to comma if file is empty
		}

		foreach ($delimiters as $delimiter) {
			$counts[$delimiter] = count(str_getcsv($firstLine, $delimiter));
		}

		// Choose delimiter that gives most columns
		$maxCount = max($counts);
		$bestDelimiter = array_search($maxCount, $counts);

		return $bestDelimiter;
	}

	/**
	 * Count total rows in CSV file (optimized for large files)
	 *
	 * @param string $filePath Path to CSV file
	 * @param string $delimiter CSV delimiter
	 * @return int Total number of rows
	 */
	public static function countCsvRows(string $filePath, string $delimiter = ","): int
	{
		// For large files, use a faster line counting method
		$fileSize = filesize($filePath);
		if ($fileSize > 50 * 1024 * 1024) {
			// Files larger than 50MB
			$handle = fopen($filePath, "r");
			$lines = 0;
			while (!feof($handle)) {
				$lines += substr_count(fread($handle, 8192), "\n");
			}
			fclose($handle);
			return $lines + 1; // Add 1 for last line if it doesn't end with \n
		}

		// For smaller files, use the accurate method
		$rowCount = 0;
		$handle = fopen($filePath, "r");

		while (fgetcsv($handle, 0, $delimiter) !== false) {
			$rowCount++;
		}

		fclose($handle);
		return $rowCount;
	}

	/**
	 * Convert numeric string to decimal format (handles Italian comma separator)
	 * CRITICAL: Maintains support for comma as decimal separator for TAN/TAEG fields
	 *
	 * @param string|null $value Value to convert
	 * @return string|null Converted decimal value
	 */
	public static function simpleDecimal(?string $value): ?string
	{
		if ($value === null) {
			return null;
		}
		$v = trim($value);
		if ($v === "") {
			return null;
		}

		// Handle negative numbers in parentheses: (123,45) -> -123,45
		$neg = false;
		if (preg_match('/^\((.*)\)$/', $v, $m)) {
			$neg = true;
			$v = $m[1];
		}

		// Remove currency symbols/spaces: keep only digits, ., , and -
		$v = preg_replace("/[^\d,.\-]/u", "", $v);

		// No separators? Return as is (integer)
		if (!strpbrk($v, ".,")) {
			return ($neg ? "-" : "") . ltrim($v, "+-");
		}

		$lastDot = strrpos($v, ".");
		$lastComma = strrpos($v, ",");

		if ($lastDot !== false && $lastComma !== false) {
			// Last between . and , is decimal separator; other is thousands
			$decimalSep = $lastDot > $lastComma ? "." : ",";
			$thousandSep = $decimalSep === "." ? "," : ".";
			$v = str_replace($thousandSep, "", $v);
			if ($decimalSep === ",") {
				$v = str_replace(",", ".", $v);
			}
		} elseif ($lastComma !== false) {
			// Only comma -> decimal separator
			$v = str_replace(",", ".", $v);
		} // Only dot -> already OK

		if ($neg && strpos($v, "-") !== 0) {
			$v = "-" . ltrim($v, "+-");
		}
		return $v;
	}

	/**
	 * Check if value looks like a numeric value
	 *
	 * @param string|null $value Value to check
	 * @return bool True if value looks numeric
	 */
	public static function looksNumeric(?string $value): bool
	{
		if ($value === null) {
			return false;
		}
		return (bool) preg_match("/\d[.,]\d|\d{4,}[.,]\d|\d+[.,]\d+/", $value);
	}

	/**
	 * Check if column name looks like a date column
	 *
	 * @param string $name Column name
	 * @return bool True if column name suggests date field
	 */
	public static function isDateLikeColumn(string $name): bool
	{
		$n = strtolower($name);

		// Columns like max_atp are numeric despite the "_at" suffix.
		if (in_array($n, ["max_atp"], true)) {
			return false;
		}

		return str_contains($n, "_at") || str_starts_with($n, "data_") || str_contains($n, "date");
	}

	/**
	 * Check if value looks like a JSON array
	 *
	 * @param string|null $v Value to check
	 * @return bool True if value looks like array
	 */
	public static function looksLikeArray(?string $v): bool
	{
		if ($v === null) {
			return false;
		}
		$t = trim($v);
		return str_starts_with($t, "[") && str_ends_with($t, "]");
	}

	/**
	 * Parse and format date value
	 *
	 * @param string $value Date string to parse
	 * @param string $format Output format (default: Y-m-d H:i:s)
	 * @return string|null Formatted date or null on failure
	 */
	public static function parseDate(string $value, string $format = "Y-m-d H:i:s"): ?string
	{
		if (empty($value)) {
			return null;
		}

		try {
			return \Carbon\Carbon::parse($value)->format($format);
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Validate CSV file structure
	 *
	 * @param string $filePath Path to CSV file
	 * @param string $delimiter CSV delimiter
	 * @return array Validation result with 'valid' boolean and 'message' string
	 */
	public static function validateCsvFile(string $filePath, string $delimiter = ","): array
	{
		if (!file_exists($filePath)) {
			return ["valid" => false, "message" => "File not found"];
		}

		$handle = fopen($filePath, "r");
		if (!$handle) {
			return ["valid" => false, "message" => "Unable to open file"];
		}

		$headers = fgetcsv($handle, 0, $delimiter);
		fclose($handle);

		if (!$headers || empty($headers)) {
			return ["valid" => false, "message" => "No headers found in CSV"];
		}

		return ["valid" => true, "message" => "CSV file is valid", "headers" => $headers];
	}
}
