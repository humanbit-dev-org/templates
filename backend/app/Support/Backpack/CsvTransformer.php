<?php

namespace App\Support\Backpack;

/**
 * CSV Transformer for Job Imports.
 *
 * Model-specific transformations applied to every row of the folder-based
 * imports (ImportJobService, ProcessImportChunk), after the column mapping
 * has produced $rowData and before the row is written. Kept separate from
 * the seeder transformations to allow different filtering logic.
 *
 * The template ships with no model-specific rule: rows are imported as
 * mapped. To add one, add a case to transform() and a private method that
 * returns the adjusted $rowData — or [] to skip the row. For example:
 *
 *     case 'User':
 *         return self::transformUser($rowData, $row, $headers);
 *
 *     private static function transformUser(array $rowData, array $row, array $headers): array
 *     {
 *         $rowData['email'] = strtolower(CsvHelper::getValueByHeader($row, $headers, 'EMAIL'));
 *
 *         return $rowData;
 *     }
 */
class CsvTransformer
{
    /**
     * Apply model-specific transformations for job import.
     *
     * @param  array  $rowData  Current row data (already mapped to model columns)
     * @param  array  $row  Raw CSV row
     * @param  array  $columnMapping  Column mapping configuration
     * @param  array  $headers  CSV headers
     * @param  string  $modelClass  Model class name
     * @param  array  $preloadedData  Preloaded data for batch lookups (optional)
     * @return array Transformed row data; an empty array skips the row
     */
    public static function transform(
        array $rowData,
        array $row,
        array $columnMapping,
        array $headers,
        string $modelClass,
        array $preloadedData = []
    ): array {
        return match (class_basename($modelClass)) {
            default => $rowData,
        };
    }
}
