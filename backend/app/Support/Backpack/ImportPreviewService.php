<?php

namespace App\Support\Backpack;

use App\Models\Delibera;
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
		"User" => ["email", "password", "role_id", "backpack_role_id"],
		"Tiraggio" => ["tiraggio_id"],
		"Delibera" => ["delibera_id"],
		"Trattenuta" => ["trattenuta_id"],
		"Proiezione" => ["proiezione_id"],
		"Storico" => ["storico_id"],
		"Previsionale" => ["previsionale_id"],
		"Banca" => ["banca_abi"],
		"Filiale" => ["filiale_id"],
		"Interfaccia" => ["interfaccia_id"],
		"Prodotto" => ["prodotto_id"],
		"Esclusione" => ["esclusione_id"],
	];

	/**
	 * Fields that are hidden from import UI and managed by Eloquent.
	 */
	private const HIDDEN_FIELDS = [
		"created_at",
		"updated_at",
		"token",
		"token_expire",
		"token_verified",
		"email_verified_at",
		"remember_token",
	];

	/**
	 * Default values applied by addDefaultRequiredFields in ImportCsvAction.
	 * These fields won't be flagged as missing because the importer sets them.
	 */
	private const DEFAULTED_FIELDS = [
		"User" => ["role_id", "email_verified_at"],
	];

	/**
	 * Cached required columns per model class.
	 */
	private static array $requiredColumnsCache = [];

	/**
	 * Status enums that are valid per model. Derived from the DB schema.
	 */
	private const VALID_STATUSES = [
		"Tiraggio" => ["open", "requested", "accepted", "rejected", "paid", "dropped"],
		"Delibera" => ["open", "close"],
	];

	/**
	 * Columns that must be integers (not decimals) per model.
	 */
	private const INTEGER_COLUMNS = [
		"Tiraggio" => ["importo_richiesto", "importo_deliberato", "percentuale", "mesi", "quota_interessi", "costo_totale"],
		"Delibera" => ["max_atp_consigliato", "max_atp_effettivo"],
		"User" => ["sospeso"],
		"Storico" => ["numero_transazioni", "importo_transato"],
		"Previsionale" => ["importo_transato_previsto"],
		"Proiezione" => ["forecast", "baseline"],
		"Trattenuta" => ["importo_trattenuto"],
	];

	/**
	 * Columns that accept decimals per model.
	 */
	private const DECIMAL_COLUMNS = [
		"Tiraggio" => ["tan", "taeg", "trattenuta_totale"],
		"Delibera" => ["tan", "taeg"],
	];

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
		string $importBehavior = "update_insert"
	): array {
		$className = class_basename($modelClass);
		$model = new $modelClass();
		$tableName = $model->getTable();

		$handle = fopen($csvPath, "r");
		if (!$handle) {
			throw new \RuntimeException("Cannot open CSV file: {$csvPath}");
		}

		// Skip header
		fgetcsv($handle, 0, $delimiter, escape: "\\");

		// Preload existing data for duplicate detection (with full record for diff)
		$existingData = $this->preloadExistingData($modelClass, $matchingFields, $className);

		// Preload required columns (NOT NULL without default, excluding auto-generated/hidden/defaulted)
		$requiredColumns = $this->getRequiredColumns($modelClass, $className);

		// Get PK and unique columns for display
		$primaryKey = (new $modelClass())->getKeyName();
		$uniqueColumns = $this->getUniqueColumns($modelClass, $className);

		// Track seen rows within the CSV itself (intra-file duplicate detection)
		$seenInFile = [];

		$summary = [
			"total_rows" => 0,
			"will_insert" => 0,
			"will_update" => 0,
			"will_skip" => 0,
			"warnings_count" => 0,
		];

		$rows = [];
		$warnings = [];
		$rowNumber = 1;

		while (($data = fgetcsv($handle, 0, $delimiter, escape: "\\")) !== false) {
			$rowNumber++;
			$summary["total_rows"]++;

			$rowData = $this->mapRow($data, $columnMapping);

			if (empty($rowData)) {
				$summary["will_skip"]++;
				$rows[] = [
					"row" => $rowNumber,
					"action" => "skip",
					"reason" => "empty_row_data",
					"data" => [],
				];

				continue;
			}

			// Determine action: insert, update, or skip
			$actionResult = $this->determineAction($rowData, $matchingFields, $existingData, $importBehavior, $modelClass);
			$action = $actionResult["action"];
			$existingRecord = $actionResult["existing_record"];

			// Detect intra-file duplicates
			$intraFileDuplicate = $this->detectIntraFileDuplicate($rowData, $matchingFields, $seenInFile);

			if ($intraFileDuplicate) {
				$warnings[] = [
					"row" => $rowNumber,
					"type" => "intra_file_duplicate",
					"severity" => "warning",
					"message" => "Riga {$rowNumber}: duplicato all'interno del file stesso (chiavi di matching identiche alla riga {$intraFileDuplicate}).",
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

			$summary[$action === "insert" ? "will_insert" : ($action === "update" ? "will_update" : "will_skip")]++;

			// For update rows, compute only changed fields
			$displayData = $rowData;
			$changedFields = array_keys($rowData);
			if ($action === "update" && $existingRecord) {
				$displayData = [];
				$changedFields = [];
				foreach ($rowData as $col => $val) {
					$existingVal = $existingRecord->{$col} ?? null;
					if (!$this->valuesEqual($val, $existingVal, $className)) {
						$displayData[$col] = $val;
						$displayData[$col . "_old"] = $existingVal;
						$changedFields[] = $col;
					}
				}
				// Always include PK and unique fields even if unchanged
				foreach (array_merge([$primaryKey], $uniqueColumns) as $alwaysCol) {
					if (!isset($displayData[$alwaysCol])) {
						if (isset($rowData[$alwaysCol])) {
							$displayData[$alwaysCol] = $rowData[$alwaysCol];
						} elseif (isset($existingRecord->{$alwaysCol})) {
							$displayData[$alwaysCol] = $existingRecord->{$alwaysCol};
						}
					}
				}
			} elseif ($action === "insert") {
				// For inserts, always include PK and unique fields
				foreach (array_merge([$primaryKey], $uniqueColumns) as $alwaysCol) {
					if (isset($rowData[$alwaysCol])) {
						$displayData[$alwaysCol] = $rowData[$alwaysCol];
					}
				}
			}

			$rows[] = [
				"row" => $rowNumber,
				"action" => $action,
				"data" => $displayData,
				"changed_fields" => $changedFields,
				"has_warnings" => !empty($modelWarnings) || !empty($requiredWarnings),
			];
		}

		fclose($handle);

		// Add model-level global warnings
		$globalWarnings = $this->validateGlobal($className, $modelClass, $rows);
		foreach ($globalWarnings as $w) {
			$warnings[] = $w;
		}

		$summary["warnings_count"] = count($warnings);
		$summary["error_count"] = count(array_filter($warnings, fn($w) => $w["severity"] === "error"));

		return [
			"summary" => $summary,
			"rows" => $rows,
			"warnings" => $warnings,
			"meta" => [
				"primary_key" => $primaryKey,
				"unique_columns" => $uniqueColumns,
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
			if (empty($dbColumn) || !isset($data[$csvIndex])) {
				continue;
			}
			$value = $data[$csvIndex];
			if ($value === "") {
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
			return ["action" => "insert", "existing_record" => null];
		}

		$matchingCriteria = [];
		$hasAllMatchingFields = true;

		foreach ($matchingFields as $field) {
			if (!empty($rowData[$field])) {
				$matchingCriteria[$field] = $rowData[$field];
			} else {
				$hasAllMatchingFields = false;
			}
		}

		if (!$hasAllMatchingFields || empty($matchingCriteria)) {
			return ["action" => $importBehavior === "update_only" ? "skip" : "insert", "existing_record" => null];
		}

		// Check against preloaded data
		$key = implode("|", array_values($matchingCriteria));
		if (isset($existingData["exists"][$key])) {
			return ["action" => "update", "existing_record" => $existingData["records"][$key] ?? null];
		}

		return ["action" => $importBehavior === "update_only" ? "skip" : "insert", "existing_record" => null];
	}

	/**
	 * Detect if the same matching keys were already seen in this file.
	 *
	 * @return int|false The first row number where this key was seen, or false.
	 */
	private function detectIntraFileDuplicate(array $rowData, array $matchingFields, array &$seenInFile): int|false
	{
		if (empty($matchingFields)) {
			return false;
		}

		$key = "";
		$hasAll = true;
		foreach ($matchingFields as $field) {
			if (!empty($rowData[$field])) {
				$key .= $rowData[$field] . "|";
			} else {
				$hasAll = false;
			}
		}

		if (!$hasAll || $key === "") {
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

		$model = new $modelClass();
		$table = $model->getTable();
		$database = config("database.connections.mysql.database");

		$requiredColumns = DB::table("INFORMATION_SCHEMA.COLUMNS")
			->where("TABLE_SCHEMA", $database)
			->where("TABLE_NAME", $table)
			->where("IS_NULLABLE", "NO")
			->where("COLUMN_NAME", "!=", "id")
			->where("COLUMN_DEFAULT", null)
			->pluck("COLUMN_NAME")
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
		if ($action === "update" || $action === "skip") {
			return $warnings;
		}

		// For inserts, check all required fields
		foreach ($requiredColumns as $column) {
			if (!isset($rowData[$column]) || $rowData[$column] === "" || $rowData[$column] === null) {
				$warnings[] = [
					"row" => $rowNumber,
					"type" => "missing_required_field",
					"severity" => "error",
					"message" => "Riga {$rowNumber}: campo obbligatorio '{$column}' mancante o vuoto. L'inserimento fallirà con un errore del database.",
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
			return ["exists" => [], "records" => []];
		}

		$exists = [];
		$records = [];
		$query = $modelClass::query();

		$rows = $query->select("*")->get();

		foreach ($rows as $row) {
			$keyParts = [];
			foreach ($matchingFields as $field) {
				$keyParts[] = $row->{$field} ?? "";
			}
			$key = implode("|", $keyParts);
			$exists[$key] = true;
			$records[$key] = $row;
		}

		return ["exists" => $exists, "records" => $records];
	}

	/**
	 * Compare two values for equality, handling numeric precision issues.
	 * E.g. 4.500 from DB vs 4.5 from CSV should be considered equal.
	 */
	private function valuesEqual(mixed $val1, mixed $val2, string $className): bool
	{
		// If both are null/empty, they're equal
		if (($val1 === null || $val1 === "") && ($val2 === null || $val2 === "")) {
			return true;
		}
		// If one is null/empty and the other isn't, they're different
		if (($val1 === null || $val1 === "") !== ($val2 === null || $val2 === "")) {
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
		$model = new $modelClass();
		$table = $model->getTable();
		$database = config("database.connections.mysql.database");

		$uniqueIndexes = DB::table("INFORMATION_SCHEMA.STATISTICS")
			->where("TABLE_SCHEMA", $database)
			->where("TABLE_NAME", $table)
			->where("NON_UNIQUE", 0)
			->where("INDEX_NAME", "!=", "PRIMARY")
			->pluck("COLUMN_NAME")
			->unique()
			->values()
			->toArray();

		return $uniqueIndexes;
	}

	/**
	 * Check unique constraint violations for insert rows.
	 * Queries the DB for each unique index to see if a record with the same values already exists.
	 */
	private function checkUniqueConstraints(
		array $rowData,
		string $className,
		string $modelClass,
		int $rowNumber,
		string $primaryKey = ""
	): array {
		$warnings = [];

		$model = new $modelClass();
		$table = $model->getTable();
		$database = config("database.connections.mysql.database");

		// Check PK duplicate first
		if (
			$primaryKey !== "" &&
			isset($rowData[$primaryKey]) &&
			$rowData[$primaryKey] !== "" &&
			$rowData[$primaryKey] !== null
		) {
			$pkExists = $modelClass::where($primaryKey, $rowData[$primaryKey])->exists();
			if ($pkExists) {
				$warnings[] = [
					"row" => $rowNumber,
					"type" => "primary_key_duplicate",
					"severity" => "error",
					"message" => "Riga {$rowNumber}: esiste già un record con chiave primaria {$primaryKey}={$rowData[$primaryKey]}. L'inserimento fallirà.",
				];
			}
		}

		// Get unique indexes grouped by index name
		$uniqueIndexes = DB::table("INFORMATION_SCHEMA.STATISTICS")
			->where("TABLE_SCHEMA", $database)
			->where("TABLE_NAME", $table)
			->where("NON_UNIQUE", 0)
			->where("INDEX_NAME", "!=", "PRIMARY")
			->orderBy("INDEX_NAME")
			->orderBy("SEQ_IN_INDEX")
			->get()
			->groupBy("INDEX_NAME");

		foreach ($uniqueIndexes as $indexName => $columns) {
			$columnNames = $columns->pluck("COLUMN_NAME")->toArray();

			// Check if all columns for this unique index are present in the row data
			$allPresent = true;
			$query = $modelClass::query();
			$values = [];
			foreach ($columnNames as $col) {
				if (!isset($rowData[$col]) || $rowData[$col] === "" || $rowData[$col] === null) {
					$allPresent = false;
					break;
				}
				$query->where($col, $rowData[$col]);
				$values[] = $col . "=" . $rowData[$col];
			}

			if (!$allPresent) {
				continue;
			}

			$exists = $query->exists();
			if ($exists) {
				$warnings[] = [
					"row" => $rowNumber,
					"type" => "unique_constraint_violation",
					"severity" => "error",
					"message" =>
						"Riga {$rowNumber}: esiste già un record con " .
						implode(", ", $values) .
						" (vincolo univoco: " .
						implode("+", $columnNames) .
						"). L'inserimento fallirà.",
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
		string $primaryKey = ""
	): array {
		$warnings = [];

		// Unique constraint check for insert rows (includes PK)
		if ($action === "insert") {
			$uniqueWarnings = $this->checkUniqueConstraints($rowData, $className, $modelClass, $rowNumber, $primaryKey);
			foreach ($uniqueWarnings as $w) {
				$warnings[] = $w;
			}
		}

		// Status validation
		if (isset($rowData["status"]) && isset(self::VALID_STATUSES[$className])) {
			if (!in_array($rowData["status"], self::VALID_STATUSES[$className], true)) {
				$validList = implode(", ", self::VALID_STATUSES[$className]);
				$warnings[] = [
					"row" => $rowNumber,
					"type" => "invalid_status",
					"severity" => "error",
					"message" => "Riga {$rowNumber}: status '{$rowData["status"]}' non valido per {$className}. Valori ammessi: {$validList}.",
				];
			}
		}

		// Integer column validation
		if (isset(self::INTEGER_COLUMNS[$className])) {
			foreach (self::INTEGER_COLUMNS[$className] as $col) {
				if (isset($rowData[$col]) && $rowData[$col] !== "" && $rowData[$col] !== null) {
					if (!$this->isIntegerValue($rowData[$col])) {
						$warnings[] = [
							"row" => $rowNumber,
							"type" => "type_mismatch",
							"severity" => "error",
							"message" => "Riga {$rowNumber}: il campo '{$col}' deve essere un numero intero, ma contiene '{$rowData[$col]}'.",
						];
					}
				}
			}
		}

		// Decimal column validation (must be numeric, can have decimals)
		if (isset(self::DECIMAL_COLUMNS[$className])) {
			foreach (self::DECIMAL_COLUMNS[$className] as $col) {
				if (isset($rowData[$col]) && $rowData[$col] !== "" && $rowData[$col] !== null) {
					if (!$this->isNumericValue($rowData[$col])) {
						$warnings[] = [
							"row" => $rowNumber,
							"type" => "type_mismatch",
							"severity" => "error",
							"message" => "Riga {$rowNumber}: il campo '{$col}' deve essere un numero decimale, ma contiene '{$rowData[$col]}'.",
						];
					}
				}
			}
		}

		// Model-specific validations
		match ($className) {
			"Tiraggio" => ($warnings = array_merge(
				$warnings,
				$this->validateTiraggioRow($rowData, $rowNumber, $modelClass, $action)
			)),
			"User" => ($warnings = array_merge($warnings, $this->validateUserRow($rowData, $rowNumber))),
			"Delibera" => ($warnings = array_merge($warnings, $this->validateDeliberaRow($rowData, $rowNumber, $action))),
			"Trattenuta" => ($warnings = array_merge($warnings, $this->validateTrattenutaRow($rowData, $rowNumber))),
			default => null,
		};

		return $warnings;
	}

	/**
	 * Tiraggio-specific row validation.
	 */
	private function validateTiraggioRow(
		array $rowData,
		int $rowNumber,
		string $modelClass,
		string $action = "insert"
	): array {
		$warnings = [];

		// Check for duplicate active tiraggi for the same user (only on insert)
		if ($action === "insert" && isset($rowData["user_id"]) && isset($rowData["status"])) {
			if (in_array($rowData["status"], ["accepted", "requested"], true)) {
				$existingCount = $modelClass
					::where("user_id", $rowData["user_id"])
					->where("status", $rowData["status"])
					->count();

				if ($existingCount > 0) {
					$warnings[] = [
						"row" => $rowNumber,
						"type" => "duplicate_active_tiraggio",
						"severity" => "warning",
						"message" => "Riga {$rowNumber}: esiste già un tiraggio con status '{$rowData["status"]}' per l'utente '{$rowData["user_id"]}'. Verrà creato un duplicato.",
					];
				}
			}
		}

		// Percentuale range check (1-100)
		if (isset($rowData["percentuale"]) && $rowData["percentuale"] !== "" && $rowData["percentuale"] !== null) {
			$pct = (int) $rowData["percentuale"];
			if ($pct < 1 || $pct > 100) {
				$warnings[] = [
					"row" => $rowNumber,
					"type" => "value_out_of_range",
					"severity" => "warning",
					"message" => "Riga {$rowNumber}: percentuale {$pct} fuori dal range valido (1-100).",
				];
			}
		}

		// Mesi range check (1-18)
		if (isset($rowData["mesi"]) && $rowData["mesi"] !== "" && $rowData["mesi"] !== null) {
			$mesi = (int) $rowData["mesi"];
			if ($mesi < 1 || $mesi > 18) {
				$warnings[] = [
					"row" => $rowNumber,
					"type" => "value_out_of_range",
					"severity" => "warning",
					"message" => "Riga {$rowNumber}: mesi {$mesi} fuori dal range valido (1-18).",
				];
			}
		}

		// Product constraint check: if user_id is known, check against product limits
		if (isset($rowData["user_id"]) && !empty($rowData["user_id"])) {
			$productLimits = $this->getProductLimitsForUser($rowData["user_id"]);
			if ($productLimits) {
				// Percentuale vs product min/max
				if (isset($rowData["percentuale"]) && $rowData["percentuale"] !== "" && $rowData["percentuale"] !== null) {
					$pct = (int) $rowData["percentuale"];
					if ($productLimits["min_percentuale"] !== null && $pct < $productLimits["min_percentuale"]) {
						$warnings[] = [
							"row" => $rowNumber,
							"type" => "product_constraint",
							"severity" => "warning",
							"message" => "Riga {$rowNumber}: percentuale {$pct} inferiore al minimo del prodotto ({$productLimits["min_percentuale"]}).",
						];
					}
					if ($productLimits["max_percentuale"] !== null && $pct > $productLimits["max_percentuale"]) {
						$warnings[] = [
							"row" => $rowNumber,
							"type" => "product_constraint",
							"severity" => "warning",
							"message" => "Riga {$rowNumber}: percentuale {$pct} superiore al massimo del prodotto ({$productLimits["max_percentuale"]}).",
						];
					}
				}

				// Mesi vs product min/max
				if (isset($rowData["mesi"]) && $rowData["mesi"] !== "" && $rowData["mesi"] !== null) {
					$mesi = (int) $rowData["mesi"];
					if ($productLimits["min_mesi_tiraggio"] !== null && $mesi < $productLimits["min_mesi_tiraggio"]) {
						$warnings[] = [
							"row" => $rowNumber,
							"type" => "product_constraint",
							"severity" => "warning",
							"message" => "Riga {$rowNumber}: mesi {$mesi} inferiori al minimo del prodotto ({$productLimits["min_mesi_tiraggio"]}).",
						];
					}
					if ($productLimits["max_mesi_tiraggio"] !== null && $mesi > $productLimits["max_mesi_tiraggio"]) {
						$warnings[] = [
							"row" => $rowNumber,
							"type" => "product_constraint",
							"severity" => "warning",
							"message" => "Riga {$rowNumber}: mesi {$mesi} superiori al massimo del prodotto ({$productLimits["max_mesi_tiraggio"]}).",
						];
					}
				}

				// TAN vs product max_tan
				if (isset($rowData["tan"]) && $rowData["tan"] !== "" && $rowData["tan"] !== null) {
					$tan = (float) str_replace(",", ".", (string) $rowData["tan"]);
					if ($productLimits["max_tan"] !== null && $tan > $productLimits["max_tan"]) {
						$warnings[] = [
							"row" => $rowNumber,
							"type" => "product_constraint",
							"severity" => "warning",
							"message" => "Riga {$rowNumber}: TAN {$tan} superiore al massimo del prodotto ({$productLimits["max_tan"]}).",
						];
					}
				}

				// TAEG vs product max_taeg
				if (isset($rowData["taeg"]) && $rowData["taeg"] !== "" && $rowData["taeg"] !== null) {
					$taeg = (float) str_replace(",", ".", (string) $rowData["taeg"]);
					if ($productLimits["max_taeg"] !== null && $taeg > $productLimits["max_taeg"]) {
						$warnings[] = [
							"row" => $rowNumber,
							"type" => "product_constraint",
							"severity" => "warning",
							"message" => "Riga {$rowNumber}: TAEG {$taeg} superiore al massimo del prodotto ({$productLimits["max_taeg"]}).",
						];
					}
				}

				// Importo richiesto vs product max_atp
				if (
					isset($rowData["importo_richiesto"]) &&
					$rowData["importo_richiesto"] !== "" &&
					$rowData["importo_richiesto"] !== null
				) {
					$importo = (int) $rowData["importo_richiesto"];
					if ($productLimits["max_atp"] !== null && $importo > $productLimits["max_atp"]) {
						$warnings[] = [
							"row" => $rowNumber,
							"type" => "product_constraint",
							"severity" => "warning",
							"message" => "Riga {$rowNumber}: importo richiesto {$importo} superiore al massimo ATP del prodotto ({$productLimits["max_atp"]}).",
						];
					}
					if ($productLimits["min_atp"] !== null && $importo < $productLimits["min_atp"]) {
						$warnings[] = [
							"row" => $rowNumber,
							"type" => "product_constraint",
							"severity" => "warning",
							"message" => "Riga {$rowNumber}: importo richiesto {$importo} inferiore al minimo ATP del prodotto ({$productLimits["min_atp"]}).",
						];
					}
				}

				// Percentuale vs product max_atp_percentuale
				if (isset($rowData["percentuale"]) && $rowData["percentuale"] !== "" && $rowData["percentuale"] !== null) {
					$pct = (int) $rowData["percentuale"];
					if ($productLimits["max_atp_percentuale"] !== null && $pct > $productLimits["max_atp_percentuale"]) {
						$warnings[] = [
							"row" => $rowNumber,
							"type" => "product_constraint",
							"severity" => "warning",
							"message" => "Riga {$rowNumber}: percentuale {$pct} superiore al massimo percentuale ATP del prodotto ({$productLimits["max_atp_percentuale"]}).",
						];
					}
				}
			}
		}

		// FK validation: user_id must exist in users table
		if (isset($rowData["user_id"]) && !empty($rowData["user_id"])) {
			$userExists = User::where("user_id", $rowData["user_id"])->exists();
			if (!$userExists) {
				$warnings[] = [
					"row" => $rowNumber,
					"type" => "foreign_key_violation",
					"severity" => "error",
					"message" => "Riga {$rowNumber}: l'utente '{$rowData["user_id"]}' non esiste nel database.",
				];
			}
		}

		// FK validation: delibera_id must exist if provided
		if (isset($rowData["delibera_id"]) && !empty($rowData["delibera_id"])) {
			$deliberaExists = Delibera::where("delibera_id", $rowData["delibera_id"])->exists();
			if (!$deliberaExists) {
				$warnings[] = [
					"row" => $rowNumber,
					"type" => "foreign_key_violation",
					"severity" => "error",
					"message" => "Riga {$rowNumber}: la delibera '{$rowData["delibera_id"]}' non esiste nel database.",
				];
			}
		}

		return $warnings;
	}

	/**
	 * User-specific row validation.
	 */
	private function validateUserRow(array $rowData, int $rowNumber): array
	{
		$warnings = [];

		// Email format check
		if (isset($rowData["email"]) && !empty($rowData["email"])) {
			if (!filter_var($rowData["email"], FILTER_VALIDATE_EMAIL)) {
				$warnings[] = [
					"row" => $rowNumber,
					"type" => "invalid_email",
					"severity" => "error",
					"message" => "Riga {$rowNumber}: email '{$rowData["email"]}' non ha un formato valido.",
				];
			}
		}

		// Sospeso must be 0 or 1
		if (isset($rowData["sospeso"]) && $rowData["sospeso"] !== "" && $rowData["sospeso"] !== null) {
			$val = $rowData["sospeso"];
			if (!in_array((string) $val, ["0", "1"], true)) {
				$warnings[] = [
					"row" => $rowNumber,
					"type" => "type_mismatch",
					"severity" => "error",
					"message" => "Riga {$rowNumber}: il campo 'sospeso' deve essere 0 o 1, ma contiene '{$val}'.",
				];
			}
		}

		// FK validation: banca_abi must exist in banche table
		if (isset($rowData["banca_abi"]) && !empty($rowData["banca_abi"])) {
			$bancaExists = DB::table("banche")->where("banca_abi", $rowData["banca_abi"])->exists();
			if (!$bancaExists) {
				$warnings[] = [
					"row" => $rowNumber,
					"type" => "foreign_key_violation",
					"severity" => "error",
					"message" => "Riga {$rowNumber}: la banca '{$rowData["banca_abi"]}' non esiste nel database.",
				];
			}
		}

		// FK validation: filiale_id must exist if provided
		if (isset($rowData["filiale_id"]) && !empty($rowData["filiale_id"])) {
			$filialeExists = DB::table("filiali")->where("filiale_id", $rowData["filiale_id"])->exists();
			if (!$filialeExists) {
				$warnings[] = [
					"row" => $rowNumber,
					"type" => "foreign_key_violation",
					"severity" => "error",
					"message" => "Riga {$rowNumber}: la filiale '{$rowData["filiale_id"]}' non esiste nel database.",
				];
			}
		}

		return $warnings;
	}

	/**
	 * Trattenuta-specific row validation.
	 */
	private function validateTrattenutaRow(array $rowData, int $rowNumber): array
	{
		$warnings = [];

		// FK validation: user_id must exist
		if (isset($rowData["user_id"]) && !empty($rowData["user_id"])) {
			$userExists = User::where("user_id", $rowData["user_id"])->exists();
			if (!$userExists) {
				$warnings[] = [
					"row" => $rowNumber,
					"type" => "foreign_key_violation",
					"severity" => "error",
					"message" => "Riga {$rowNumber}: l'utente '{$rowData["user_id"]}' non esiste nel database.",
				];
			}
		}

		// FK validation: tiraggio_numero_pratica must exist in tiraggi table
		if (isset($rowData["tiraggio_numero_pratica"]) && !empty($rowData["tiraggio_numero_pratica"])) {
			$tiraggioExists = DB::table("tiraggi")->where("numero_pratica", $rowData["tiraggio_numero_pratica"])->exists();
			if (!$tiraggioExists) {
				$warnings[] = [
					"row" => $rowNumber,
					"type" => "foreign_key_violation",
					"severity" => "error",
					"message" => "Riga {$rowNumber}: il tiraggio con numero pratica '{$rowData["tiraggio_numero_pratica"]}' non esiste nel database.",
				];
			}
		}

		return $warnings;
	}

	/**
	 * Delibera-specific row validation.
	 */
	private function validateDeliberaRow(array $rowData, int $rowNumber, string $action = "insert"): array
	{
		$warnings = [];

		// Check for existing open delibera for the same user (only on insert)
		if (
			$action === "insert" &&
			isset($rowData["user_id"]) &&
			isset($rowData["status"]) &&
			$rowData["status"] === "open"
		) {
			$existingOpen = Delibera::where("user_id", $rowData["user_id"])->where("status", "open")->exists();

			if ($existingOpen) {
				$warnings[] = [
					"row" => $rowNumber,
					"type" => "duplicate_open_delibera",
					"severity" => "warning",
					"message" => "Riga {$rowNumber}: esiste già una delibera 'open' per l'utente '{$rowData["user_id"]}'. L'import creerà un duplicato.",
				];
			}
		}

		// Product constraint check: if user_id is known, check against product limits
		if (isset($rowData["user_id"]) && !empty($rowData["user_id"])) {
			$productLimits = $this->getProductLimitsForUser($rowData["user_id"]);
			if ($productLimits) {
				// TAN vs product max_tan
				if (isset($rowData["tan"]) && $rowData["tan"] !== "" && $rowData["tan"] !== null) {
					$tan = (float) str_replace(",", ".", (string) $rowData["tan"]);
					if ($productLimits["max_tan"] !== null && $tan > $productLimits["max_tan"]) {
						$warnings[] = [
							"row" => $rowNumber,
							"type" => "product_constraint",
							"severity" => "warning",
							"message" => "Riga {$rowNumber}: TAN {$tan} superiore al massimo del prodotto ({$productLimits["max_tan"]}).",
						];
					}
				}

				// TAEG vs product max_taeg
				if (isset($rowData["taeg"]) && $rowData["taeg"] !== "" && $rowData["taeg"] !== null) {
					$taeg = (float) str_replace(",", ".", (string) $rowData["taeg"]);
					if ($productLimits["max_taeg"] !== null && $taeg > $productLimits["max_taeg"]) {
						$warnings[] = [
							"row" => $rowNumber,
							"type" => "product_constraint",
							"severity" => "warning",
							"message" => "Riga {$rowNumber}: TAEG {$taeg} superiore al massimo del prodotto ({$productLimits["max_taeg"]}).",
						];
					}
				}

				// max_atp_consigliato vs product max_atp
				if (
					isset($rowData["max_atp_consigliato"]) &&
					$rowData["max_atp_consigliato"] !== "" &&
					$rowData["max_atp_consigliato"] !== null
				) {
					$atp = (int) $rowData["max_atp_consigliato"];
					if ($productLimits["max_atp"] !== null && $atp > $productLimits["max_atp"]) {
						$warnings[] = [
							"row" => $rowNumber,
							"type" => "product_constraint",
							"severity" => "warning",
							"message" => "Riga {$rowNumber}: max ATP consigliato {$atp} superiore al massimo ATP del prodotto ({$productLimits["max_atp"]}).",
						];
					}
				}

				// max_atp_effettivo vs product max_atp
				if (
					isset($rowData["max_atp_effettivo"]) &&
					$rowData["max_atp_effettivo"] !== "" &&
					$rowData["max_atp_effettivo"] !== null
				) {
					$atp = (int) $rowData["max_atp_effettivo"];
					if ($productLimits["max_atp"] !== null && $atp > $productLimits["max_atp"]) {
						$warnings[] = [
							"row" => $rowNumber,
							"type" => "product_constraint",
							"severity" => "warning",
							"message" => "Riga {$rowNumber}: max ATP effettivo {$atp} superiore al massimo ATP del prodotto ({$productLimits["max_atp"]}).",
						];
					}
				}
			}

			// FK validation: user_id must exist
			$userExists = User::where("user_id", $rowData["user_id"])->exists();
			if (!$userExists) {
				$warnings[] = [
					"row" => $rowNumber,
					"type" => "foreign_key_violation",
					"severity" => "error",
					"message" => "Riga {$rowNumber}: l'utente '{$rowData["user_id"]}' non esiste nel database.",
				];
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
		$insertCount = count(array_filter($rows, fn($r) => $r["action"] === "insert"));
		if ($insertCount > 10000) {
			$warnings[] = [
				"row" => 0,
				"type" => "large_insert",
				"severity" => "info",
				"message" => "L'import creerà {$insertCount} nuovi record. Verificare che sia corretto prima di confermare.",
			];
		}

		return $warnings;
	}

	/**
	 * Get product limits for a user via their bank.
	 */
	private function getProductLimitsForUser(string $userId): ?array
	{
		$user = User::where("user_id", $userId)->first();
		if (!$user) {
			return null;
		}

		$prodotto = $user->banca?->prodotto;
		if (!$prodotto) {
			return null;
		}

		return [
			"min_percentuale" => $prodotto->min_percentuale !== null ? (int) $prodotto->min_percentuale : null,
			"max_percentuale" => $prodotto->max_percentuale !== null ? (int) $prodotto->max_percentuale : null,
			"min_mesi_tiraggio" => $prodotto->min_mesi_tiraggio !== null ? (int) $prodotto->min_mesi_tiraggio : null,
			"max_mesi_tiraggio" => $prodotto->max_mesi_tiraggio !== null ? (int) $prodotto->max_mesi_tiraggio : null,
			"max_tan" => $prodotto->max_tan !== null ? (float) $prodotto->max_tan : null,
			"max_taeg" => $prodotto->max_taeg !== null ? (float) $prodotto->max_taeg : null,
			"max_atp" => $prodotto->max_atp !== null ? (int) $prodotto->max_atp : null,
			"max_atp_percentuale" => $prodotto->max_atp_percentuale !== null ? (int) $prodotto->max_atp_percentuale : null,
			"min_atp" => $prodotto->min_atp !== null ? (int) $prodotto->min_atp : null,
			"durata_max_delibera" => $prodotto->durata_max_delibera !== null ? (int) $prodotto->durata_max_delibera : null,
			"min_mesi_storico" => $prodotto->min_mesi_storico !== null ? (int) $prodotto->min_mesi_storico : null,
			"min_importo_storico" => $prodotto->min_importo_storico !== null ? (int) $prodotto->min_importo_storico : null,
			"min_transazioni_storico" =>
				$prodotto->min_transazioni_storico !== null ? (int) $prodotto->min_transazioni_storico : null,
		];
	}

	/**
	 * Check if a value is a valid integer (no decimals).
	 */
	private function isIntegerValue(mixed $value): bool
	{
		if ($value === null || $value === "") {
			return true;
		}

		// Accept strings like "123", "123.0", "123,0" but not "12.5" or "12,5"
		$normalized = str_replace(",", ".", (string) $value);

		if (!is_numeric($normalized)) {
			return false;
		}

		return floor((float) $normalized) == (float) $normalized;
	}

	/**
	 * Check if a value is numeric (integer or decimal).
	 */
	private function isNumericValue(mixed $value): bool
	{
		if ($value === null || $value === "") {
			return true;
		}

		$normalized = str_replace(",", ".", (string) $value);

		return is_numeric($normalized);
	}
}
