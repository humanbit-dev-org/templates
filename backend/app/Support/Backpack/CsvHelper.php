<?php

namespace App\Support\Backpack;

use Carbon\Carbon;

/**
 * CSV Helper - Common utilities for all CSV import operations
 * Centralized helper functions for CSV parsing, validation, formatting and import statistics
 */
class CsvHelper
{
    // Generic import counters (used by all import operations)
    private static int $insertedCount = 0;

    private static int $updatedCount = 0;

    private static int $skippedCount = 0;

    // Custom counters for operation-specific logging (key => count)
    private static array $customCounters = [];

    /**
     * Reset all import counters to zero
     * Call this at the start of each import operation
     */
    public static function resetCounters(): void
    {
        self::$insertedCount = 0;
        self::$updatedCount = 0;
        self::$skippedCount = 0;
        self::$customCounters = [];
    }

    /**
     * Increment the inserted records counter
     */
    public static function incrementInserted(int $count = 1): void
    {
        self::$insertedCount += $count;
    }

    /**
     * Increment the updated records counter
     */
    public static function incrementUpdated(int $count = 1): void
    {
        self::$updatedCount += $count;
    }

    /**
     * Increment the skipped records counter
     */
    public static function incrementSkipped(int $count = 1): void
    {
        self::$skippedCount += $count;
    }

    /**
     * Increment a custom counter by name
     * Use for operation-specific tracking (e.g., 'missing_users', 'invalid_dates')
     *
     * @param  string  $name  Counter name
     * @param  int  $count  Amount to increment (default: 1)
     */
    public static function incrementCustom(string $name, int $count = 1): void
    {
        if (! isset(self::$customCounters[$name])) {
            self::$customCounters[$name] = 0;
        }
        self::$customCounters[$name] += $count;
    }

    /**
     * Get current statistics without resetting
     *
     * @return array Array with current counter values
     */
    public static function getCounters(): array
    {
        return [
            'inserted' => self::$insertedCount,
            'updated' => self::$updatedCount,
            'skipped' => self::$skippedCount,
            'custom' => self::$customCounters,
        ];
    }

    /**
     * Detect the delimiter used in a CSV file
     *
     * @param  string  $filePath  Path to CSV file
     * @return string Detected delimiter (default: comma)
     */
    public static function detectDelimiter(string $filePath): string
    {
        $delimiters = [',', ';', "\t", '|', ':'];
        $counts = [];
        $firstLine = '';

        $handle = fopen($filePath, 'r');
        if ($handle) {
            $firstLine = fgets($handle);
            fclose($handle);
        }

        if (empty($firstLine)) {
            return ','; // Default to comma if file is empty
        }

        foreach ($delimiters as $delimiter) {
            $counts[$delimiter] = count(str_getcsv($firstLine, $delimiter, escape: '\\'));
        }

        // Choose delimiter that gives most columns
        $maxCount = max($counts);
        $bestDelimiter = array_search($maxCount, $counts);

        return $bestDelimiter;
    }

    /**
     * Count total rows in CSV file (optimized for large files)
     *
     * @param  string  $filePath  Path to CSV file
     * @param  string  $delimiter  CSV delimiter
     * @return int Total number of rows
     */
    public static function countCsvRows(string $filePath, string $delimiter = ','): int
    {
        // For large files, use a faster line counting method
        $fileSize = filesize($filePath);
        if ($fileSize > 50 * 1024 * 1024) {
            // Files larger than 50MB
            $handle = fopen($filePath, 'r');
            $lines = 0;
            while (! feof($handle)) {
                $lines += substr_count(fread($handle, 8192), "\n");
            }
            fclose($handle);

            return $lines + 1; // Add 1 for last line if it doesn't end with \n
        }

        // For smaller files, use the accurate method
        $rowCount = 0;
        $handle = fopen($filePath, 'r');

        while (fgetcsv($handle, 0, $delimiter, escape: '\\') !== false) {
            $rowCount++;
        }

        fclose($handle);

        return $rowCount;
    }

    /**
     * Convert numeric string to decimal format (handles Italian comma separator)
     * CRITICAL: Maintains support for comma as decimal separator for TAN/TAEG fields
     *
     * @param  string|null  $value  Value to convert
     * @return string|null Converted decimal value
     */
    public static function simpleDecimal(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = trim($value);
        if ($v === '') {
            return null;
        }

        // Handle negative numbers in parentheses: (123,45) -> -123,45
        $neg = false;
        if (preg_match('/^\((.*)\)$/', $v, $m)) {
            $neg = true;
            $v = $m[1];
        }

        // Remove currency symbols/spaces: keep only digits, ., , and -
        $v = preg_replace("/[^\d,.\-]/u", '', $v);

        // No separators? Return as is (integer)
        if (! strpbrk($v, '.,')) {
            return ($neg ? '-' : '').ltrim($v, '+-');
        }

        $lastDot = strrpos($v, '.');
        $lastComma = strrpos($v, ',');

        if ($lastDot !== false && $lastComma !== false) {
            // Last between . and , is decimal separator; other is thousands
            $decimalSep = $lastDot > $lastComma ? '.' : ',';
            $thousandSep = $decimalSep === '.' ? ',' : '.';
            $v = str_replace($thousandSep, '', $v);
            if ($decimalSep === ',') {
                $v = str_replace(',', '.', $v);
            }
        } elseif ($lastComma !== false) {
            // Only comma -> decimal separator
            $v = str_replace(',', '.', $v);
        } // Only dot -> already OK

        if ($neg && strpos($v, '-') !== 0) {
            $v = '-'.ltrim($v, '+-');
        }

        return $v;
    }

    /**
     * Check if value looks like a numeric value
     *
     * @param  string|null  $value  Value to check
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
     * @param  string  $name  Column name
     * @return bool True if column name suggests date field
     */
    public static function isDateLikeColumn(string $name): bool
    {
        $n = strtolower($name);

        // Columns like max_atp are numeric despite the "_at" suffix.
        if (in_array($n, ['max_atp_consigliato', 'max_atp_effettivo'], true)) {
            return false;
        }

        return str_contains($n, '_at') || str_starts_with($n, 'data_') || str_contains($n, 'date');
    }

    /**
     * Parse and format date value
     *
     * @param  string  $value  Date string to parse
     * @param  string  $format  Output format (default: Y-m-d H:i:s)
     * @return string|null Formatted date or null on failure
     */
    public static function parseDate(string $value, string $format = 'Y-m-d H:i:s'): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format($format);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Find the index of a header in CSV headers array (case-insensitive)
     * Accepts multiple candidate names for flexibility
     *
     * @param  string|array  $candidates  Header name or array of possible header names
     * @param  array  $headers  CSV headers array
     * @return int|false Index of found header or false if not found
     */
    public static function findHeaderIndex($candidates, array $headers)
    {
        // Support both single string and array of candidates
        $candidateList = is_array($candidates) ? $candidates : [$candidates];

        foreach ($candidateList as $candidate) {
            foreach ($headers as $index => $header) {
                if (strcasecmp((string) $header, $candidate) === 0) {
                    return $index;
                }
            }
        }

        return false;
    }

    /**
     * Alias for findHeaderIndex - drop-in replacement for array_search with case-insensitive matching
     * Usage: CsvHelper::headerIndex('EMAIL', $headers) instead of array_search('EMAIL', $headers)
     *
     * @param  string|array  $headerName  Header name or array of candidate names
     * @param  array  $headers  CSV headers array
     * @return int|false Index of found header or false if not found
     */
    public static function headerIndex($headerName, array $headers)
    {
        return self::findHeaderIndex($headerName, $headers);
    }

    /**
     * Get value from CSV row by header name (case-insensitive)
     * Convenience method that combines findHeaderIndex with value extraction
     *
     * @param  array  $row  CSV row data
     * @param  array  $headers  CSV headers array
     * @param  string|array  $headerName  Header name or array of candidate names
     * @param  mixed  $default  Default value if header not found
     * @return mixed Value from row or default
     */
    public static function getValueByHeader(array $row, array $headers, $headerName, $default = null)
    {
        $candidates = is_array($headerName) ? $headerName : [$headerName];
        $index = self::findHeaderIndex($candidates, $headers);

        if ($index === false || ! isset($row[$index])) {
            return $default;
        }

        return trim($row[$index]);
    }
}
