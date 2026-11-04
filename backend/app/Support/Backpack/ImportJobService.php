<?php

namespace App\Support\Backpack;

use App\Http\Controllers\Admin\Action\ImportCsvAction;
use App\Support\Backpack\CsvBackupManager;
use App\Support\Backpack\CsvHelper;
use App\Support\Backpack\CsvTransformer;
use App\Services\Cache\CacheFlush;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Import Job Service - Handles CSV import for automated jobs
 * Optimized for cron job imports with logging and error handling
 */
class ImportJobService
{
    /**
     * Cache for database schema unique fields (avoids repeated INFORMATION_SCHEMA queries)
     */
    private static array $schemaCache = [];

    /**
     * Append-only models imported with a bulk INSERT IGNORE instead of the
     * per-row upsert: much faster on large files, duplicates (per the table's
     * UNIQUE constraints) are skipped. List model basenames, e.g. ['AuditLog'].
     */
    private const BULK_INSERT_MODELS = [];

    /**
     * Numeric columns that receive 0 when their CSV cell is empty, instead of
     * being left out of the row (and falling back to the column default).
     * Shared with ProcessImportChunk so both import paths map rows alike.
     */
    public const ZERO_WHEN_EMPTY_COLUMNS = [];

    /**
     * Columns whose name contains "_at" but that do not hold a date, so the
     * date parsing of mapRowData() must leave them alone.
     */
    public const NON_DATE_AT_COLUMNS = [];

    /**
     * Import CSV file for job
     *
     * @param  string  $fileName  File name
     * @param  string  $subfolder  Subfolder name
     * @param  string  $modelClass  Model class name
     * @param  string|null  $uniqueField  Unique field for upsert (optional)
     * @return array Import statistics
     */
    public static function import(
        string $fileName,
        string $subfolder,
        string $modelClass,
        ?string $uniqueField = null
    ): array {
        Log::channel('import')->info('Import file in import/'.$subfolder.'/'.$fileName.' avviato alle '.now());

        // Increase execution time; use reasonable memory limit (Horizon watchdog at 300MB)
        set_time_limit(0);
        ini_set('memory_limit', '384M');

        // Set database timeout for large batch operations
        config([
            'database.connections.mysql.options' => [
                \PDO::ATTR_TIMEOUT => 300,
            ],
        ]);

        // File is in pending/ subfolder during job processing
        $csvPath = database_path("import/{$subfolder}/pending/{$fileName}");

        if (! file_exists($csvPath)) {
            // Fallback: check root subfolder (for manual/legacy imports)
            $csvPathLegacy = database_path("import/{$subfolder}/{$fileName}");
            if (file_exists($csvPathLegacy)) {
                $csvPath = $csvPathLegacy;
            } else {
                throw new \Exception("CSV file not found in pending/ or root: {$csvPath}");
            }
        }

        try {
            $model = new $modelClass;
            $tableName = $model->getTable();

            // Create backup before import
            Log::channel('import')->info('Creating backup for table '.$tableName);
            CsvBackupManager::createBackup($tableName, 'job', true);
            Log::channel('import')->info('Backup for table '.$tableName.' created');

            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            $result = self::performImport($csvPath, $modelClass, $uniqueField);

            // Rows written in bulk bypass the Observers: drop the dependent cache once.
            CacheFlush::afterImport($modelClass);

            // Hash passwords if importing User model
            if (str_contains($modelClass, 'User')) {
                Log::channel('import')->info('Hashing user passwords');
                ImportCsvAction::hashUserPasswordsAfterImport(true);
                Log::channel('import')->info('User passwords hashed');
            }

            return $result;
        } catch (\Exception $e) {
            Log::channel('import')->error('Import failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Perform the actual import operation
     *
     * @param  string  $csvPath  Full path to CSV file
     * @param  string  $modelClass  Model class name
     * @param  string|null  $uniqueField  Unique field for upsert
     * @return array Import statistics
     */
    private static function performImport(string $csvPath, string $modelClass, ?string $uniqueField = null): array
    {
        $delimiter = CsvHelper::detectDelimiter($csvPath);
        $handle = fopen($csvPath, 'r');

        if (! $handle) {
            throw new \Exception("Unable to open CSV file: {$csvPath}");
        }

        // Read headers
        $headers = fgetcsv($handle, 0, $delimiter, escape: '\\');
        if (! $headers) {
            fclose($handle);
            throw new \Exception('Invalid CSV format: no headers found');
        }

        // Get model information
        $model = new $modelClass;
        $tableColumns = SchemaCache::getColumnListing($model->getTable());
        $fillableColumns = $model->getFillable();

        // Create column mapping automatically
        $columnMapping = self::createAutoMapping($headers, $tableColumns, $fillableColumns);

        // if (empty($columnMapping)) {
        // 	fclose($handle);
        // 	throw new \Exception("No valid column mappings found between CSV and model");
        // }

        // Preload data for models that need it (reduces N+1 queries)
        $preloadedData = self::preloadDataForModel($modelClass);

        // Count total rows for progress (optimized for large files)
        $fileSize = filesize($csvPath);
        $totalRows =
            $fileSize > 100 * 1024 * 1024 ? intval($fileSize / 150) : CsvHelper::countCsvRows($csvPath, $delimiter) - 1;

        // Reset global counters at start of import
        CsvHelper::resetCounters();

        // Statistics
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'processed' => 0,
        ];

        // Batch configuration
        $batchSize = 200;
        $batch = [];
        $lastProgress = -1;
        $processedInTransaction = 0;
        $maxTransactionRows = 5000;

        Log::channel('import')->info("Processing {$totalRows} rows in batches of {$batchSize}");

        // MySQL optimizations
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::statement('SET UNIQUE_CHECKS=0');
        } catch (\Exception $e) {
            Log::channel('import')->warning('Could not set MySQL optimizations: '.$e->getMessage());
        }

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle, 0, $delimiter, escape: '\\')) !== false) {
                $stats['processed']++;

                // Map row data
                $rowData = self::mapRowData($row, $columnMapping ?? [], $headers, $modelClass);

                if (empty($rowData)) {
                    $stats['skipped']++;
                    unset($rowData);

                    continue;
                }

                // Skip records a transformation marked for skip (e.g. a missing foreign key)
                if (! empty($rowData['__skip_record'])) {
                    $stats['skipped']++;
                    unset($rowData);

                    continue;
                }

                // Apply model-specific transformations (with preloaded data for performance)
                $rowData = CsvTransformer::transform($rowData, $row, $columnMapping ?? [], $headers, $modelClass, $preloadedData);

                if (empty($rowData)) {
                    $stats['skipped']++;
                    unset($rowData);

                    continue;
                }

                // A transformation may expand one CSV row into several records
                if (isset($rowData['__multiple_records__'])) {
                    foreach ($rowData['__multiple_records__'] as $record) {
                        $batch[] = $record;
                        $processedInTransaction++;
                    }
                    unset($rowData);
                } else {
                    $batch[] = $rowData;
                    $processedInTransaction++;
                }

                // Process batch when full or at end of file
                if (count($batch) >= $batchSize || $stats['processed'] >= $totalRows) {
                    $batchStats = self::processBatch($batch, $modelClass, null);

                    $stats['created'] += $batchStats['created'];
                    $stats['updated'] += $batchStats['updated'];
                    $stats['skipped'] += $batchStats['skipped'];
                    $stats['errors'] += $batchStats['errors'];

                    $batch = [];

                    // Commit transaction periodically
                    if ($processedInTransaction >= $maxTransactionRows || $stats['processed'] >= $totalRows) {
                        DB::commit();

                        // Memory safety check: exit cleanly if approaching limit
                        $memoryUsed = memory_get_usage(true);
                        if ($memoryUsed > 320 * 1024 * 1024) {
                            Log::channel('import')->warning(
                                "Memory threshold reached at row {$stats['processed']} (".
                                    round($memoryUsed / 1024 / 1024, 1).'MB). '.
                                    'Stopping safely. Remaining rows will be processed on next run.'
                            );
                            break;
                        }

                        if ($stats['processed'] < $totalRows) {
                            DB::beginTransaction();
                        }
                        $processedInTransaction = 0;
                    }

                    // Log progress every 25% (reduced frequency to minimize disk I/O)
                    $progress = round((($stats['processed'] / $totalRows) * 100) / 25) * 25;
                    if ($progress !== $lastProgress && $progress % 25 == 0 && $progress > 0) {
                        Log::channel('import')->info(
                            "Progress: {$progress}% ({$stats['processed']}/{$totalRows}) - Memory: ".
                                round(memory_get_usage(true) / 1024 / 1024, 2).
                                'MB'
                        );
                        $lastProgress = $progress;
                    }

                    // Force garbage collection more frequently for memory efficiency
                    if ($stats['processed'] % 1000 == 0) {
                        gc_collect_cycles();

                        // Periodic memory logging for diagnostics
                        if ($stats['processed'] % 5000 == 0) {
                            Log::channel('import')->info(
                                "Memory at row {$stats['processed']}: ".
                                    round(memory_get_usage(true) / 1024 / 1024, 1).'MB'
                            );
                        }
                    }
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        } finally {
            // Restore MySQL settings
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                DB::statement('SET UNIQUE_CHECKS=1');
            } catch (\Exception $e) {
                // Ignore restore errors
            }

            // Ensure file handle is always closed
            if (is_resource($handle)) {
                fclose($handle);
            }

            // Clear batch and force final garbage collection
            unset($batch);
            gc_collect_cycles();

            // Log final summary instead of per-skip logs
            if ($stats['skipped'] > 0) {
                $customCounters = CsvHelper::getCounters()['custom'] ?? [];
                $summaryParts = ["Import completed with {$stats['skipped']} skipped rows"];

                // Custom counters set by the transformations (CsvHelper::incrementCustom()).
                foreach ($customCounters as $counter => $count) {
                    if ($count > 0) {
                        $summaryParts[] = Str::headline($counter).": {$count}";
                    }
                }

                Log::channel('import')->warning(implode(' | ', $summaryParts));
            }
        }

        // Sync local stats with global CsvHelper counters
        CsvHelper::incrementInserted($stats['created']);
        CsvHelper::incrementUpdated($stats['updated']);
        CsvHelper::incrementSkipped($stats['skipped']);

        return $stats;
    }

    /**
     * Public wrapper for preloadDataForModel (used by ProcessImportFile for batch mode)
     *
     * @param  string  $modelClass  Model class name
     * @return array Preloaded data arrays
     */
    public static function preloadDataForModelPublic(string $modelClass): array
    {
        return self::preloadDataForModel($modelClass);
    }

    /**
     * Preload data for models that benefit from batch lookups instead of per-row queries
     *
     * @param  string  $modelClass  Model class name
     * @return array Preloaded data arrays keyed by lookup field
     */
    private static function preloadDataForModel(string $modelClass): array
    {
        $preloaded = [];

        // Add per-model lookups here (e.g. existing ids keyed for fast
        // duplicate detection) and read them in CsvTransformer::transform().
        match (class_basename($modelClass)) {
            default => null,
        };

        return $preloaded;
    }

    /**
     * Create automatic column mapping between CSV and database
     *
     * @param  array  $headers  CSV headers
     * @param  array  $tableColumns  Database table columns
     * @param  array  $fillableColumns  Fillable columns from model
     * @return array Column mapping (csv_index => db_column)
     */
    private static function createAutoMapping(array $headers, array $tableColumns, array $fillableColumns): array
    {
        $mapping = [];

        foreach ($headers as $index => $header) {
            $normalizedHeader = Str::snake(strtolower(trim($header)));

            // Check if column exists in table and is fillable
            if (
                in_array($normalizedHeader, $tableColumns) &&
                (empty($fillableColumns) || in_array($normalizedHeader, $fillableColumns))
            ) {
                $mapping[$index] = $normalizedHeader;
            }
        }

        return $mapping;
    }

    /**
     * Map CSV row data to database columns
     *
     * @param  array  $row  CSV row data
     * @param  array  $columnMapping  Column mapping
     * @param  array  $headers  CSV headers
     * @param  string  $modelClass  Model class name
     * @return array Mapped row data
     */
    private static function mapRowData(array $row, array $columnMapping, array $headers, string $modelClass): array
    {
        $rowData = [];

        // One CSV column can map to multiple DB columns (an array in $columnMapping).
        foreach ($columnMapping as $csvIndex => $dbColumnOrColumns) {
            $dbColumns = is_array($dbColumnOrColumns) ? $dbColumnOrColumns : [$dbColumnOrColumns];
            if (! isset($row[$csvIndex])) {
                continue;
            }
            $value = trim($row[$csvIndex]);

            foreach ($dbColumns as $dbColumn) {
                if ($dbColumn === '' || $dbColumn === null) {
                    continue;
                }
                $cellValue = $value;

                // Handle numeric values (preserving comma as decimal separator)
                if (! CsvHelper::isDateLikeColumn($dbColumn)) {
                    if (CsvHelper::looksNumeric($cellValue)) {
                        $cellValue = CsvHelper::simpleDecimal($cellValue);
                    }
                }

                // Handle special date formats
                if (
                    str_contains($dbColumn, '_at') &&
                    ! in_array($dbColumn, self::NON_DATE_AT_COLUMNS, true) &&
                    $cellValue !== '' &&
                    $cellValue !== null
                ) {
                    $parsedDate = CsvHelper::parseDate($cellValue);
                    if ($parsedDate) {
                        $cellValue = $parsedDate;
                    }
                }

                // Handle empty values for numeric fields - convert to 0 instead of skipping
                if ($cellValue === '' && in_array($dbColumn, self::ZERO_WHEN_EMPTY_COLUMNS, true)) {
                    $rowData[$dbColumn] = 0;
                } elseif ($cellValue !== '') {
                    $rowData[$dbColumn] = $cellValue;
                }
            }
        }

        // Add default values for missing required fields
        $rowData = self::addDefaultRequiredFields($rowData, $modelClass);

        return $rowData;
    }

    /**
     * Add default values for missing required fields
     *
     * @param  array  $rowData  Current row data
     * @param  string  $modelClass  Model class name
     * @return array Row data with defaults added
     */
    private static function addDefaultRequiredFields(array $rowData, string $modelClass): array
    {
        $className = class_basename($modelClass);

        switch ($className) {
            case 'User':
                // Set default role_id for users if not present
                if (! isset($rowData['role_id']) || empty($rowData['role_id'])) {
                    $rowData['role_id'] = 2;
                }

                // Ensure email_verified_at is set
                if (! isset($rowData['email_verified_at']) || empty($rowData['email_verified_at'])) {
                    $rowData['email_verified_at'] = now();
                }
                break;
        }

        return $rowData;
    }

    /**
     * Public wrapper for processBatch (used by ProcessImportChunk)
     *
     * @param  array  $batch  Batch of records to process
     * @param  string  $modelClass  Model class name
     * @return array Batch processing statistics
     */
    public static function processBatchPublic(array $batch, string $modelClass): array
    {
        return self::processBatch($batch, $modelClass, null);
    }

    /**
     * Process a batch of records with optimized bulk operations
     *
     * @param  array  $batch  Batch of records to process
     * @param  string  $modelClass  Model class name
     * @param  string|null  $uniqueField  Unique field for upsert
     * @return array Batch processing statistics
     */
    private static function processBatch(array $batch, string $modelClass, ?string $uniqueField = null): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

        if (empty($batch)) {
            return $stats;
        }

        // For job imports: implement upsert logic (update if exists, insert if not)
        try {
            $model = new $modelClass;
            $tableName = $model->getTable();
            $className = class_basename($modelClass);

            // Add timestamps if model uses them
            if ($model->usesTimestamps()) {
                $now = now();
                foreach ($batch as &$item) {
                    $item['created_at'] = $item['created_at'] ?? $now;
                    $item['updated_at'] = $item['updated_at'] ?? $now;
                }
            }

            // Append-only models: bulk insert for better performance
            if (in_array($className, self::BULK_INSERT_MODELS, true) && ! empty($batch)) {
                // Clean batch: remove internal fields
                $cleanBatch = [];
                foreach ($batch as $rowData) {
                    // Remove internal fields that shouldn't be inserted
                    unset($rowData['__skip_record']);
                    unset($rowData['__skip_reason']);
                    unset($rowData['__existing_record_id']);
                    $cleanBatch[] = $rowData;
                }

                if (! empty($cleanBatch)) {
                    try {
                        // Get column names from first row
                        $columns = array_keys($cleanBatch[0]);
                        $columnList = '`'.implode('`, `', $columns).'`';

                        // Build VALUES clause with placeholders
                        $placeholders = '('.implode(', ', array_fill(0, count($columns), '?')).')';
                        $valuesClause = implode(', ', array_fill(0, count($cleanBatch), $placeholders));

                        // Flatten all values in correct order
                        $allValues = [];
                        foreach ($cleanBatch as $row) {
                            foreach ($columns as $column) {
                                $allValues[] = $row[$column] ?? null;
                            }
                        }

                        // Execute bulk INSERT IGNORE (ignores duplicates).
                        // affectingStatement, not statement: INSERT IGNORE reports
                        // the rows it actually wrote, and the difference is exactly
                        // the duplicates it swallowed. Counting the whole batch as
                        // created made every import read 100% success.
                        $inserted = DB::affectingStatement(
                            "INSERT IGNORE INTO `{$tableName}` ({$columnList}) VALUES {$valuesClause}",
                            $allValues
                        );

                        $stats['created'] += $inserted;
                        $stats['skipped'] += count($cleanBatch) - $inserted;
                    } catch (\Exception $e) {
                        // Fallback to individual inserts if bulk fails
                        Log::channel('import')->warning(
                            "Bulk insert failed for {$className}, falling back to individual inserts: ".$e->getMessage()
                        );
                        foreach ($cleanBatch as $rowData) {
                            try {
                                $modelClass::create($rowData);
                                $stats['created']++;
                            } catch (\Exception $e2) {
                                $stats['errors']++;
                                Log::channel('import')->warning("Failed to insert {$className}: ".$e2->getMessage());
                            }
                        }
                    }
                }

                return $stats;
            }

            // Process each record individually with model-specific logic
            foreach ($batch as $rowData) {
                try {
                    // Explicit existing record override: a transformation found the row to update
                    if (! empty($rowData['__existing_record_id'])) {
                        $existingId = $rowData['__existing_record_id'];
                        unset($rowData['__existing_record_id']);

                        $existingRecord = $modelClass::find($existingId);
                        if ($existingRecord) {
                            $existingRecord->update($rowData);
                            $stats['updated']++;

                            continue;
                        }
                    }

                    // For other models: use upsert logic
                    $uniqueFieldToUse = self::getUniqueFieldForModel($modelClass, $rowData);

                    if (! $uniqueFieldToUse || ! isset($rowData[$uniqueFieldToUse])) {
                        // No unique field or value, just insert
                        $modelClass::create($rowData);
                        $stats['created']++;

                        continue;
                    }

                    // Check if record exists with this unique value
                    $existingRecord = self::findExistingRecord($modelClass, $rowData, $uniqueFieldToUse);

                    if ($existingRecord) {
                        // Update existing record
                        $existingRecord->update($rowData);
                        $stats['updated']++;
                    } else {
                        // Insert new record
                        $modelClass::create($rowData);
                        $stats['created']++;
                    }
                } catch (\Exception $e) {
                    $stats['skipped']++;
                    Log::channel('import')->warning('Skipped row due to error: '.$e->getMessage());
                }
            }
        } catch (\Exception $e) {
            $stats['errors'] += count($batch);
            Log::channel('import')->error('Batch processing error: '.$e->getMessage());
        }

        return $stats;
    }

    /**
     * Determine the unique field for a model based on database schema
     *
     * @param  string  $modelClass  Model class name
     * @param  array  $rowData  Row data to check for unique fields
     * @return string|null Unique field name or null if not found
     */
    private static function getUniqueFieldForModel(string $modelClass, array $rowData): ?string
    {
        $model = new $modelClass;
        $tableName = $model->getTable();

        // Get unique indexes from database schema
        $uniqueFields = self::getUniqueFieldsFromSchema($tableName);

        if (empty($uniqueFields)) {
            return null;
        }

        // For single unique fields, return the first one that exists in rowData
        foreach ($uniqueFields as $field) {
            if (isset($rowData[$field]) && ! empty($rowData[$field])) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Get unique fields from database schema (cached to avoid repeated INFORMATION_SCHEMA queries)
     *
     * @param  string  $tableName  Table name
     * @return array Array of unique field names
     */
    private static function getUniqueFieldsFromSchema(string $tableName): array
    {
        if (isset(self::$schemaCache[$tableName])) {
            return self::$schemaCache[$tableName];
        }

        try {
            $uniqueIndexes = DB::select(
                'SELECT COLUMN_NAME
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = ?
                 AND TABLE_NAME = ?
                 AND NON_UNIQUE = 0
                 AND INDEX_NAME != \'PRIMARY\'
                 ORDER BY SEQ_IN_INDEX',
                [config('database.connections.mysql.database'), $tableName]
            );

            // array_filter: a functional key part (an expression index) has no COLUMN_NAME.
            self::$schemaCache[$tableName] = array_values(array_filter(array_column($uniqueIndexes, 'COLUMN_NAME')));
        } catch (\Exception $e) {
            Log::channel('import')->warning("Could not get unique fields for table {$tableName}: ".$e->getMessage());
            self::$schemaCache[$tableName] = [];
        }

        return self::$schemaCache[$tableName];
    }

    /**
     * Find existing record based on unique field(s)
     *
     * @param  string  $modelClass  Model class name
     * @param  array  $rowData  Row data
     * @param  string  $uniqueField  Primary unique field
     */
    private static function findExistingRecord(
        string $modelClass,
        array $rowData,
        string $uniqueField
    ): ?\Illuminate\Database\Eloquent\Model {
        $model = new $modelClass;
        $tableName = $model->getTable();

        // Get all unique fields from schema
        $uniqueFields = self::getUniqueFieldsFromSchema($tableName);

        if (empty($uniqueFields)) {
            return null;
        }

        // Build query with all unique fields that have values in rowData
        $query = $modelClass::query();
        $hasConditions = false;

        foreach ($uniqueFields as $field) {
            if (isset($rowData[$field]) && ! empty($rowData[$field])) {
                $query->where($field, $rowData[$field]);
                $hasConditions = true;
            }
        }

        // Only execute query if we have at least one condition
        return $hasConditions ? $query->first() : null;
    }

    /**
     * Move a file between import states (pending, processed, failed)
     *
     * @param  string  $subfolder  Import subfolder name
     * @param  string  $fileName  File name to move
     * @param  string  $fromState  Source state folder (pending, processed, failed)
     * @param  string  $toState  Destination state folder (pending, processed, failed)
     * @return bool True if move was successful
     */
    public static function moveBetweenStates(string $subfolder, string $fileName, string $fromState, string $toState): bool
    {
        $baseFolder = database_path("import/{$subfolder}");
        $sourcePath = "{$baseFolder}/{$fromState}/{$fileName}";
        $destFolder = "{$baseFolder}/{$toState}";
        $destPath = "{$destFolder}/{$fileName}";

        if (! file_exists($sourcePath)) {
            Log::channel('import')->warning("Cannot move file: source not found at {$sourcePath}");

            return false;
        }

        if (! is_dir($destFolder)) {
            @mkdir($destFolder, 0755, true);
        }

        if (@rename($sourcePath, $destPath)) {
            Log::channel('import')->info("File moved: {$fromState}/{$fileName} -> {$toState}/{$fileName}");

            return true;
        }

        Log::channel('import')->error("Failed to move file: {$sourcePath} -> {$destPath}");

        return false;
    }
}
