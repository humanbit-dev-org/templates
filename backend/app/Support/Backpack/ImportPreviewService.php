<?php

namespace App\Support\Backpack;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Analyzes a CSV file against the database state and produces a preview
 * of what the import would do (insert / update / skip) plus a list of
 * warnings for anomalous or potentially incorrect data.
 *
 * The service never writes to the database — it is read-only.
 */
class ImportPreviewService
{
    /**
     * Fields that are auto-generated in model boot() and should not be flagged as missing.
     * Mirrors ImportCsvAction::AUTO_GENERATED_FIELDS.
     */
    private const AUTO_GENERATED_FIELDS = [
        'User' => ['password', 'role_id', 'backpack_role_id'],
    ];

    /**
     * Fields that are hidden from import UI and managed by Eloquent.
     */
    private const HIDDEN_FIELDS = [
        'created_at', 'updated_at', 'token', 'token_expire',
        'token_verified', 'email_verified_at', 'remember_token',
    ];

    /**
     * Default values applied by addDefaultRequiredFields in ImportCsvAction.
     * These fields won't be flagged as missing because the importer sets them.
     */
    private const DEFAULTED_FIELDS = [
        'User' => ['role_id', 'email_verified_at'],
    ];

    /**
     * Cached required columns per model class.
     */
    private static array $requiredColumnsCache = [];

    /**
     * Status enums that are valid per model (class basename => allowed values),
     * e.g. 'Order' => ['open', 'paid', 'cancelled'].
     */
    private const VALID_STATUSES = [];

    /**
     * Columns that must be integers (not decimals) per model (class basename).
     */
    private const INTEGER_COLUMNS = [
        'Page' => ['order'],
    ];

    /**
     * Columns that accept decimals per model (class basename).
     */
    private const DECIMAL_COLUMNS = [];

    /**
     * Analyze a CSV file and return a preview report.
     *
     * @param  string  $csvPath  Full path to the CSV file
     * @param  string  $modelClass  Fully qualified model class name
     * @param  array  $columnMapping  CSV column index => DB column name
     * @param  array  $matchingFields  Fields used for upsert matching
     * @param  string  $delimiter  CSV delimiter
     * @param  string  $importBehavior  'update_insert' or 'update_only'
     * @return array{summary: array, rows: array, warnings: array}
     */
    public function analyze(
        string $csvPath,
        string $modelClass,
        array $columnMapping,
        array $matchingFields,
        string $delimiter,
        string $importBehavior = 'update_insert'
    ): array {
        $className = class_basename($modelClass);
        $model = new $modelClass;
        $tableName = $model->getTable();

        $handle = fopen($csvPath, 'r');
        if (! $handle) {
            throw new \RuntimeException("Cannot open CSV file: {$csvPath}");
        }

        // Skip header
        fgetcsv($handle, 0, $delimiter, escape: '\\');

        // Preload existing data for duplicate detection (with full record for diff)
        $existingData = $this->preloadExistingData($modelClass, $matchingFields, $className);

        // Preload required columns (NOT NULL without default, excluding auto-generated/hidden/defaulted)
        $requiredColumns = $this->getRequiredColumns($modelClass, $className);

        // Get PK and unique columns for display
        $primaryKey = (new $modelClass)->getKeyName();
        $uniqueColumns = $this->getUniqueColumns($modelClass, $className);

        // Track seen rows within the CSV itself (intra-file duplicate detection)
        $seenInFile = [];

        $summary = [
            'total_rows' => 0,
            'will_insert' => 0,
            'will_update' => 0,
            'will_skip' => 0,
            'warnings_count' => 0,
        ];

        $rows = [];
        $warnings = [];
        $rowNumber = 1;

        while (($data = fgetcsv($handle, 0, $delimiter, escape: '\\')) !== false) {
            $rowNumber++;
            $summary['total_rows']++;

            $rowData = $this->mapRow($data, $columnMapping);

            if (empty($rowData)) {
                $summary['will_skip']++;
                $rows[] = [
                    'row' => $rowNumber,
                    'action' => 'skip',
                    'reason' => 'empty_row_data',
                    'data' => [],
                ];

                continue;
            }

            // Determine action: insert, update, or skip
            $actionResult = $this->determineAction(
                $rowData,
                $matchingFields,
                $existingData,
                $importBehavior,
                $modelClass
            );
            $action = $actionResult['action'];
            $existingRecord = $actionResult['existing_record'];

            // Detect intra-file duplicates
            $intraFileDuplicate = $this->detectIntraFileDuplicate(
                $rowData,
                $matchingFields,
                $seenInFile
            );

            if ($intraFileDuplicate) {
                $warnings[] = [
                    'row' => $rowNumber,
                    'type' => 'intra_file_duplicate',
                    'severity' => 'warning',
                    'message' => "Riga {$rowNumber}: duplicato all'interno del file stesso (chiavi di matching identiche alla riga {$intraFileDuplicate}).",
                ];
            }

            // Required field validation (missing NOT NULL fields without default)
            $requiredWarnings = $this->validateRequiredFields($rowData, $requiredColumns, $className, $rowNumber, $action);
            foreach ($requiredWarnings as $w) {
                $warnings[] = $w;
            }

            // Model-specific warnings
            $modelWarnings = $this->validateRow($rowData, $className, $modelClass, $rowNumber, $action, $primaryKey);
            foreach ($modelWarnings as $w) {
                $warnings[] = $w;
            }

            $summary[$action === 'insert' ? 'will_insert' : ($action === 'update' ? 'will_update' : 'will_skip')]++;

            // For update rows, compute only changed fields
            $displayData = $rowData;
            $changedFields = array_keys($rowData);
            if ($action === 'update' && $existingRecord) {
                $displayData = [];
                $changedFields = [];
                foreach ($rowData as $col => $val) {
                    $existingVal = $existingRecord->{$col} ?? null;
                    if (! $this->valuesEqual($val, $existingVal, $className)) {
                        $displayData[$col] = $val;
                        $displayData[$col.'_old'] = $existingVal;
                        $changedFields[] = $col;
                    }
                }
                // Always include PK and unique fields even if unchanged
                foreach (array_merge([$primaryKey], $uniqueColumns) as $alwaysCol) {
                    if (! isset($displayData[$alwaysCol])) {
                        if (isset($rowData[$alwaysCol])) {
                            $displayData[$alwaysCol] = $rowData[$alwaysCol];
                        } elseif (isset($existingRecord->{$alwaysCol})) {
                            $displayData[$alwaysCol] = $existingRecord->{$alwaysCol};
                        }
                    }
                }
            } elseif ($action === 'insert') {
                // For inserts, always include PK and unique fields
                foreach (array_merge([$primaryKey], $uniqueColumns) as $alwaysCol) {
                    if (isset($rowData[$alwaysCol])) {
                        $displayData[$alwaysCol] = $rowData[$alwaysCol];
                    }
                }
            }

            $rows[] = [
                'row' => $rowNumber,
                'action' => $action,
                'data' => $displayData,
                'changed_fields' => $changedFields,
                'has_warnings' => ! empty($modelWarnings) || ! empty($requiredWarnings),
            ];
        }

        fclose($handle);

        // Add model-level global warnings
        $globalWarnings = $this->validateGlobal($className, $modelClass, $rows);
        foreach ($globalWarnings as $w) {
            $warnings[] = $w;
        }

        $summary['warnings_count'] = count($warnings);
        $summary['error_count'] = count(array_filter($warnings, fn ($w) => $w['severity'] === 'error'));

        return [
            'summary' => $summary,
            'rows' => $rows,
            'warnings' => $warnings,
            'meta' => [
                'primary_key' => $primaryKey,
                'unique_columns' => $uniqueColumns,
            ],
        ];
    }

    /**
     * Map CSV row data to DB columns using the column mapping.
     */
    private function mapRow(array $data, array $columnMapping): array
    {
        $rowData = [];
        foreach ($columnMapping as $csvIndex => $dbColumn) {
            if (empty($dbColumn) || ! isset($data[$csvIndex])) {
                continue;
            }
            $value = $data[$csvIndex];
            if ($value === '') {
                continue;
            }
            $rowData[$dbColumn] = $value;
        }

        return $rowData;
    }

    /**
     * Determine whether a row would be inserted, updated, or skipped.
     * Returns ['action' => string, 'existing_record' => Model|null]
     */
    private function determineAction(
        array $rowData,
        array $matchingFields,
        array $existingData,
        string $importBehavior,
        string $modelClass
    ): array {
        if (empty($matchingFields)) {
            return ['action' => 'insert', 'existing_record' => null];
        }

        $matchingCriteria = [];
        $hasAllMatchingFields = true;

        foreach ($matchingFields as $field) {
            if (! empty($rowData[$field])) {
                $matchingCriteria[$field] = $rowData[$field];
            } else {
                $hasAllMatchingFields = false;
            }
        }

        if (! $hasAllMatchingFields || empty($matchingCriteria)) {
            return ['action' => ($importBehavior === 'update_only' ? 'skip' : 'insert'), 'existing_record' => null];
        }

        // Check against preloaded data
        $key = implode('|', array_values($matchingCriteria));
        if (isset($existingData['exists'][$key])) {
            return ['action' => 'update', 'existing_record' => $existingData['records'][$key] ?? null];
        }

        return ['action' => ($importBehavior === 'update_only' ? 'skip' : 'insert'), 'existing_record' => null];
    }

    /**
     * Detect if the same matching keys were already seen in this file.
     *
     * @return int|false The first row number where this key was seen, or false.
     */
    private function detectIntraFileDuplicate(
        array $rowData,
        array $matchingFields,
        array &$seenInFile
    ): int|false {
        if (empty($matchingFields)) {
            return false;
        }

        $key = '';
        $hasAll = true;
        foreach ($matchingFields as $field) {
            if (! empty($rowData[$field])) {
                $key .= $rowData[$field].'|';
            } else {
                $hasAll = false;
            }
        }

        if (! $hasAll || $key === '') {
            return false;
        }

        if (isset($seenInFile[$key])) {
            return $seenInFile[$key];
        }

        // Track current row number via count
        $seenInFile[$key] = count($seenInFile) + 2; // +2 because row 1 is header

        return false;
    }

    /**
     * Get NOT NULL columns without default value, excluding auto-generated, hidden, and defaulted fields.
     * Mirrors ImportCsvAction::getRequiredColumns logic.
     */
    private function getRequiredColumns(string $modelClass, string $className): array
    {
        if (isset(self::$requiredColumnsCache[$modelClass])) {
            return self::$requiredColumnsCache[$modelClass];
        }

        $model = new $modelClass;
        $table = $model->getTable();
        $database = config('database.connections.mysql.database');

        $requiredColumns = DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('IS_NULLABLE', 'NO')
            ->where('COLUMN_NAME', '!=', 'id')
            ->where('COLUMN_DEFAULT', null)
            ->pluck('COLUMN_NAME')
            ->toArray();

        // Exclude auto-generated fields
        $autoGenerated = self::AUTO_GENERATED_FIELDS[$className] ?? [];
        $requiredColumns = array_diff($requiredColumns, $autoGenerated);

        // Exclude hidden fields
        $requiredColumns = array_diff($requiredColumns, self::HIDDEN_FIELDS);

        // Exclude defaulted fields (set by addDefaultRequiredFields)
        $defaulted = self::DEFAULTED_FIELDS[$className] ?? [];
        $requiredColumns = array_diff($requiredColumns, $defaulted);

        $result = array_values($requiredColumns);
        self::$requiredColumnsCache[$modelClass] = $result;

        return $result;
    }

    /**
     * Validate that all required columns are present in the row data.
     * Only flags missing fields for rows that would be inserted (updates may omit required fields).
     */
    private function validateRequiredFields(
        array $rowData,
        array $requiredColumns,
        string $className,
        int $rowNumber,
        string $action
    ): array {
        $warnings = [];

        // For updates, required fields may be omitted (only updated fields are sent)
        if ($action === 'update' || $action === 'skip') {
            return $warnings;
        }

        // For inserts, check all required fields
        foreach ($requiredColumns as $column) {
            if (! isset($rowData[$column]) || $rowData[$column] === '' || $rowData[$column] === null) {
                $warnings[] = [
                    'row' => $rowNumber,
                    'type' => 'missing_required_field',
                    'severity' => 'error',
                    'message' => "Riga {$rowNumber}: campo obbligatorio '{$column}' mancante o vuoto. L'inserimento fallirà con un errore del database.",
                ];
            }
        }

        return $warnings;
    }

    /**
     * Preload existing records from the database for matching fields.
     * Returns ['exists' => bool[], 'records' => Model[]]
     */
    private function preloadExistingData(string $modelClass, array $matchingFields, string $className): array
    {
        if (empty($matchingFields)) {
            return ['exists' => [], 'records' => []];
        }

        $exists = [];
        $records = [];
        $query = $modelClass::query();

        $rows = $query->select('*')->get();

        foreach ($rows as $row) {
            $keyParts = [];
            foreach ($matchingFields as $field) {
                $keyParts[] = $row->{$field} ?? '';
            }
            $key = implode('|', $keyParts);
            $exists[$key] = true;
            $records[$key] = $row;
        }

        return ['exists' => $exists, 'records' => $records];
    }

    /**
     * Compare two values for equality, handling numeric precision issues.
     * E.g. 4.500 from DB vs 4.5 from CSV should be considered equal.
     */
    private function valuesEqual(mixed $val1, mixed $val2, string $className): bool
    {
        // If both are null/empty, they're equal
        if (($val1 === null || $val1 === '') && ($val2 === null || $val2 === '')) {
            return true;
        }
        // If one is null/empty and the other isn't, they're different
        if (($val1 === null || $val1 === '') !== ($val2 === null || $val2 === '')) {
            return false;
        }

        // If both are numeric, compare with bccomp (scale 10 is more than enough for decimal(10,3))
        if (is_numeric($val1) && is_numeric($val2)) {
            return bccomp((string) $val1, (string) $val2, 10) === 0;
        }

        // Fallback: string comparison
        return (string) $val1 === (string) $val2;
    }

    /**
     * Get unique columns (besides PK) for a model from INFORMATION_SCHEMA.
     */
    private function getUniqueColumns(string $modelClass, string $className): array
    {
        $model = new $modelClass;
        $table = $model->getTable();
        $database = config('database.connections.mysql.database');

        $uniqueIndexes = DB::table('INFORMATION_SCHEMA.STATISTICS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('NON_UNIQUE', 0)
            ->where('INDEX_NAME', '!=', 'PRIMARY')
            ->pluck('COLUMN_NAME')
            ->unique()
            ->values()
            ->toArray();

        return $uniqueIndexes;
    }

    /**
     * Check unique constraint violations for insert rows.
     * Queries the DB for each unique index to see if a record with the same values already exists.
     */
    private function checkUniqueConstraints(array $rowData, string $className, string $modelClass, int $rowNumber, string $primaryKey = ''): array
    {
        $warnings = [];

        $model = new $modelClass;
        $table = $model->getTable();
        $database = config('database.connections.mysql.database');

        // Check PK duplicate first
        if ($primaryKey !== '' && isset($rowData[$primaryKey]) && $rowData[$primaryKey] !== '' && $rowData[$primaryKey] !== null) {
            $pkExists = $modelClass::where($primaryKey, $rowData[$primaryKey])->exists();
            if ($pkExists) {
                $warnings[] = [
                    'row' => $rowNumber,
                    'type' => 'primary_key_duplicate',
                    'severity' => 'error',
                    'message' => "Riga {$rowNumber}: esiste già un record con chiave primaria {$primaryKey}={$rowData[$primaryKey]}. L'inserimento fallirà.",
                ];
            }
        }

        // Get unique indexes grouped by index name
        $uniqueIndexes = DB::table('INFORMATION_SCHEMA.STATISTICS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('NON_UNIQUE', 0)
            ->where('INDEX_NAME', '!=', 'PRIMARY')
            ->orderBy('INDEX_NAME')
            ->orderBy('SEQ_IN_INDEX')
            ->get()
            ->groupBy('INDEX_NAME');

        foreach ($uniqueIndexes as $indexName => $columns) {
            $columnNames = $columns->pluck('COLUMN_NAME')->toArray();

            // Check if all columns for this unique index are present in the row data
            $allPresent = true;
            $query = $modelClass::query();
            $values = [];
            foreach ($columnNames as $col) {
                if (! isset($rowData[$col]) || $rowData[$col] === '' || $rowData[$col] === null) {
                    $allPresent = false;
                    break;
                }
                $query->where($col, $rowData[$col]);
                $values[] = $col.'='.$rowData[$col];
            }

            if (! $allPresent) {
                continue;
            }

            $exists = $query->exists();
            if ($exists) {
                $warnings[] = [
                    'row' => $rowNumber,
                    'type' => 'unique_constraint_violation',
                    'severity' => 'error',
                    'message' => "Riga {$rowNumber}: esiste già un record con ".implode(', ', $values).' (vincolo univoco: '.implode('+', $columnNames)."). L'inserimento fallirà.",
                ];
            }
        }

        return $warnings;
    }

    /**
     * Validate a single row and return warnings.
     */
    private function validateRow(
        array $rowData,
        string $className,
        string $modelClass,
        int $rowNumber,
        string $action,
        string $primaryKey = ''
    ): array {
        $warnings = [];

        // Unique constraint check for insert rows (includes PK)
        if ($action === 'insert') {
            $uniqueWarnings = $this->checkUniqueConstraints($rowData, $className, $modelClass, $rowNumber, $primaryKey);
            foreach ($uniqueWarnings as $w) {
                $warnings[] = $w;
            }
        }

        // Status validation
        if (isset($rowData['status']) && isset(self::VALID_STATUSES[$className])) {
            if (! in_array($rowData['status'], self::VALID_STATUSES[$className], true)) {
                $validList = implode(', ', self::VALID_STATUSES[$className]);
                $warnings[] = [
                    'row' => $rowNumber,
                    'type' => 'invalid_status',
                    'severity' => 'error',
                    'message' => "Riga {$rowNumber}: status '{$rowData['status']}' non valido per {$className}. Valori ammessi: {$validList}.",
                ];
            }
        }

        // Integer column validation
        if (isset(self::INTEGER_COLUMNS[$className])) {
            foreach (self::INTEGER_COLUMNS[$className] as $col) {
                if (isset($rowData[$col]) && $rowData[$col] !== '' && $rowData[$col] !== null) {
                    if (! $this->isIntegerValue($rowData[$col])) {
                        $warnings[] = [
                            'row' => $rowNumber,
                            'type' => 'type_mismatch',
                            'severity' => 'error',
                            'message' => "Riga {$rowNumber}: il campo '{$col}' deve essere un numero intero, ma contiene '{$rowData[$col]}'.",
                        ];
                    }
                }
            }
        }

        // Decimal column validation (must be numeric, can have decimals)
        if (isset(self::DECIMAL_COLUMNS[$className])) {
            foreach (self::DECIMAL_COLUMNS[$className] as $col) {
                if (isset($rowData[$col]) && $rowData[$col] !== '' && $rowData[$col] !== null) {
                    if (! $this->isNumericValue($rowData[$col])) {
                        $warnings[] = [
                            'row' => $rowNumber,
                            'type' => 'type_mismatch',
                            'severity' => 'error',
                            'message' => "Riga {$rowNumber}: il campo '{$col}' deve essere un numero decimale, ma contiene '{$rowData[$col]}'.",
                        ];
                    }
                }
            }
        }

        // Model-specific validations
        match ($className) {
            'User' => $warnings = array_merge($warnings, $this->validateUserRow($rowData, $rowNumber)),
            default => null,
        };

        return $warnings;
    }

    /**
     * User-specific row validation.
     */
    private function validateUserRow(array $rowData, int $rowNumber): array
    {
        $warnings = [];

        // Email format check
        if (isset($rowData['email']) && ! empty($rowData['email'])) {
            if (! filter_var($rowData['email'], FILTER_VALIDATE_EMAIL)) {
                $warnings[] = [
                    'row' => $rowNumber,
                    'type' => 'invalid_email',
                    'severity' => 'error',
                    'message' => "Riga {$rowNumber}: email '{$rowData['email']}' non ha un formato valido.",
                ];
            }
        }

        // FK validation: the frontend and Backpack roles must exist
        foreach (['role_id' => 'roles', 'backpack_role_id' => 'backpack_roles'] as $column => $table) {
            if (isset($rowData[$column]) && ! empty($rowData[$column])) {
                if (! DB::table($table)->where('id', $rowData[$column])->exists()) {
                    $warnings[] = [
                        'row' => $rowNumber,
                        'type' => 'foreign_key_violation',
                        'severity' => 'error',
                        'message' => "Riga {$rowNumber}: il valore '{$rowData[$column]}' di '{$column}' non esiste in {$table}.",
                    ];
                }
            }
        }

        return $warnings;
    }

    /**
     * Global validations across all rows (e.g., total count anomalies).
     */
    private function validateGlobal(string $className, string $modelClass, array $rows): array
    {
        $warnings = [];

        // Check if too many rows would be inserted (potential mistake)
        $insertCount = count(array_filter($rows, fn ($r) => $r['action'] === 'insert'));
        if ($insertCount > 10000) {
            $warnings[] = [
                'row' => 0,
                'type' => 'large_insert',
                'severity' => 'info',
                'message' => "L'import creerà {$insertCount} nuovi record. Verificare che sia corretto prima di confermare.",
            ];
        }

        return $warnings;
    }

    /**
     * Check if a value is a valid integer (no decimals).
     */
    private function isIntegerValue(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        // Accept strings like "123", "123.0", "123,0" but not "12.5" or "12,5"
        $normalized = str_replace(',', '.', (string) $value);

        if (! is_numeric($normalized)) {
            return false;
        }

        return floor((float) $normalized) == (float) $normalized;
    }

    /**
     * Check if a value is numeric (integer or decimal).
     */
    private function isNumericValue(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $normalized = str_replace(',', '.', (string) $value);

        return is_numeric($normalized);
    }
}
