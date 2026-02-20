<?php

namespace App\Http\Controllers\Admin\Helper;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\Admin\Helper\Core\CsvHelper;
use App\Http\Controllers\Admin\Helper\Core\CsvBackupManager;
use App\Http\Controllers\Admin\Helper\Core\CsvTransformerSeeder;
use App\Models\Tiraggio;
use App\Notifications\UserCloseTiraggioNotification;
use App\Notifications\FilialeCloseTiraggioNotification;

/**
 * Import Seeder Service - Handles CSV import for database seeders
 * Optimized for bulk insert operations during database seeding
 */
class ImportSeederService
{
	/**
	 * Import CSV file for seeder
	 *
	 * @param string $seederName Seeder name (used for file path)
	 * @param string $modelClass Model class name
	 * @param string|null $uniqueField Unique field for upsert (optional)
	 * @param bool $skipTiraggio Skip tiraggio-specific operations
	 * @param bool $createBackup Create backup before import (default: false for seeders)
	 * @return array Import statistics
	 */
	public static function import(
		string $seederName,
		string $modelClass,
		?string $uniqueField = null,
		bool $skipTiraggio = false,
		bool $createBackup = false
	): array {
		// Increase execution time and memory for large imports
		set_time_limit(0);
		ini_set("memory_limit", "1G");

		// Set database timeout for large batch operations
		config([
			"database.connections.mysql.options" => [
				\PDO::ATTR_TIMEOUT => 300,
			],
		]);

		$csvPath = database_path("seeders/csv/{$seederName}.csv");

		if (!file_exists($csvPath)) {
			throw new \Exception("CSV file not found: {$csvPath}");
		}

		try {
			// Create backup if requested
			if ($createBackup) {
				$model = new $modelClass();
				$tableName = $model->getTable();
				CsvBackupManager::createBackup($tableName, "seeder", true);
			}

			return self::performImport($csvPath, $modelClass, $uniqueField, $skipTiraggio);
		} catch (\Exception $e) {
			throw $e;
		}
	}

	/**
	 * Perform the actual import operation
	 *
	 * @param string $csvPath Full path to CSV file
	 * @param string $modelClass Model class name
	 * @param string|null $uniqueField Unique field for upsert
	 * @param bool $skipTiraggio Skip tiraggio-specific operations
	 * @return array Import statistics
	 */
	private static function performImport(
		string $csvPath,
		string $modelClass,
		?string $uniqueField = null,
		bool $skipTiraggio = false
	): array {
		$delimiter = CsvHelper::detectDelimiter($csvPath);
		$handle = fopen($csvPath, "r");

		if (!$handle) {
			throw new \Exception("Unable to open CSV file: {$csvPath}");
		}

		// Read headers
		$headers = fgetcsv($handle, 0, $delimiter);
		if (!$headers) {
			fclose($handle);
			throw new \Exception("Invalid CSV format: no headers found");
		}

		// Get model information
		$model = new $modelClass();
		$tableColumns = Schema::getColumnListing($model->getTable());
		$fillableColumns = $model->getFillable();

		// Create column mapping automatically
		$columnMapping = self::createAutoMapping($headers, $tableColumns, $fillableColumns);

		if (empty($columnMapping)) {
			fclose($handle);
			throw new \Exception("No valid column mappings found between CSV and model");
		}

		// No cache - using direct DB queries instead

		// Count total rows for progress (optimized for large files)
		$fileSize = filesize($csvPath);
		$totalRows =
			$fileSize > 100 * 1024 * 1024 ? intval($fileSize / 150) : CsvHelper::countCsvRows($csvPath, $delimiter) - 1;

		// Statistics
		$stats = [
			"created" => 0,
			"updated" => 0,
			"skipped" => 0,
			"errors" => 0,
			"processed" => 0,
		];

		// Batch configuration - balanced for speed and stability
		$batchSize = 200;
		$batch = [];
		$lastProgress = -1;
		$processedInTransaction = 0;
		$maxTransactionRows = 5000;

		echo "Processing {$totalRows} rows in batches of {$batchSize}...\n";

		// MySQL optimizations for ultra-fast bulk inserts
		try {
			DB::statement("SET FOREIGN_KEY_CHECKS=0");
			DB::statement("SET UNIQUE_CHECKS=0");
		} catch (\Exception $e) {
			echo $e->getMessage();
		}

		DB::beginTransaction();

		try {
			while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
				$stats["processed"]++;

				// Map row data
				$rowData = self::mapRowData($row, $columnMapping, $headers, $modelClass);

				if (empty($rowData)) {
					$stats["skipped"]++;
					continue;
				}

				// Check if this is a multiple records case (for Proiezione)
				if (isset($rowData["__multiple_records__"])) {
					foreach ($rowData["__multiple_records__"] as $record) {
						$batch[] = $record;
						$processedInTransaction++;
					}
				} else {
					$batch[] = $rowData;
					$processedInTransaction++;
				}

				// Process batch when full or at end of file
				if (count($batch) >= $batchSize || $stats["processed"] >= $totalRows) {
					$batchStats = self::processBatch($batch, $modelClass, null);

					$stats["created"] += $batchStats["created"];
					$stats["updated"] += $batchStats["updated"];
					$stats["skipped"] += $batchStats["skipped"];
					$stats["errors"] += $batchStats["errors"];

					$batch = [];

					// No cache reload needed - using direct DB queries

					// Commit transaction periodically to prevent long-running transactions
					if ($processedInTransaction >= $maxTransactionRows || $stats["processed"] >= $totalRows) {
						DB::commit();
						if ($stats["processed"] < $totalRows) {
							DB::beginTransaction();
						}
						$processedInTransaction = 0;
					}

					// Show progress every 10%
					$progress = round((($stats["processed"] / $totalRows) * 100) / 10) * 10;
					if ($progress !== $lastProgress && $progress % 10 == 0) {
						echo "Progress: {$progress}% ({$stats["processed"]}/{$totalRows}) - Memory: " .
							round(memory_get_usage(true) / 1024 / 1024, 2) .
							"MB\n";
						$lastProgress = $progress;
					}

					// Force garbage collection periodically
					if ($stats["processed"] % 2000 == 0) {
						gc_collect_cycles();
					}
				}
			}

			// Handle Trattenuta-specific operations
			// Note: Trattenuta import should only insert trattenute, not update tiraggi
			// Tiraggio updates should be handled separately if needed
			if (str_contains($modelClass, "Trattenuta")) {
				echo "Trattenuta import completed - no tiraggio updates performed\n";
			}

			DB::commit();

			// Create filiale managers after Filiale import
			if (str_contains($modelClass, "Filiale")) {
				echo "Creating filiale managers after import...\n";
				self::createFilialeManagersAfterImport();
			}

			// Link Tiraggi to Delibere after Tiraggio import
			if ($modelClass === \App\Models\Tiraggio::class) {
				self::linkTiraggiToDelibere();
			}
		} catch (\Exception $e) {
			DB::rollBack();
			throw $e;
		} finally {
			// Restore MySQL settings
			try {
				DB::statement("SET FOREIGN_KEY_CHECKS=1");
				DB::statement("SET UNIQUE_CHECKS=1");
			} catch (\Exception $e) {
				// Ignore restore errors
			}
			fclose($handle);
		}

		return $stats;
	}

	/**
	 * Create automatic column mapping between CSV and database
	 *
	 * @param array $headers CSV headers
	 * @param array $tableColumns Database table columns
	 * @param array $fillableColumns Fillable columns from model
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
	 * @param array $row CSV row data
	 * @param array $columnMapping Column mapping
	 * @param array $headers CSV headers
	 * @param string $modelClass Model class name
	 * @return array Mapped row data
	 */
	private static function mapRowData(array $row, array $columnMapping, array $headers, string $modelClass): array
	{
		$rowData = [];

		foreach ($columnMapping as $csvIndex => $dbColumn) {
			if (isset($row[$csvIndex])) {
				$value = trim($row[$csvIndex]);

				// Handle numeric values (preserving comma as decimal separator)
				if (!CsvHelper::isDateLikeColumn($dbColumn)) {
					if (CsvHelper::looksNumeric($value)) {
						$value = CsvHelper::simpleDecimal($value);
					}
				}

				// Handle special date formats
				if (CsvHelper::isDateLikeColumn($dbColumn) && $value && $value !== "") {
					$parsedDate = CsvHelper::parseDate($value, "Y-m-d");
					if ($parsedDate) {
						$value = $parsedDate;
					}
				}

				// Handle empty date fields - set to null but include in rowData
				if (CsvHelper::isDateLikeColumn($dbColumn) && $value === "") {
					$rowData[$dbColumn] = null;
				}
				// Handle empty values for numeric fields - convert to 0 instead of skipping
				elseif (
					$value === "" &&
					in_array($dbColumn, [
						"importo_richiesto",
						"costo_totale",
						"importo_transato",
						"numero_transazioni",
						"importo_previsto",
						"importo_deliberato",
						"trattenuta_totale",
						"importo_trattenuto",
						"mesi",
						"mese",
						"anno",
						"percentuale",
						"quota_interessi",
						"forecast",
						"baseline",
						"max_atp",
					])
				) {
					$rowData[$dbColumn] = 0;
				} elseif ($value !== "") {
					$rowData[$dbColumn] = $value;
				}
			}
		}

		// Apply model-specific transformations
		$rowData = CsvTransformerSeeder::transform($rowData, $row, $columnMapping, $headers, $modelClass);

		// Add default values for missing required fields
		$rowData = self::addDefaultRequiredFields($rowData, $modelClass);

		return $rowData;
	}

	/**
	 * Add default values for missing required fields
	 *
	 * @param array $rowData Current row data
	 * @param string $modelClass Model class name
	 * @return array Row data with defaults added
	 */
	private static function addDefaultRequiredFields(array $rowData, string $modelClass): array
	{
		$className = class_basename($modelClass);

		switch ($className) {
			case "User":
				// Set default role_id for users if not present
				if (!isset($rowData["role_id"]) || empty($rowData["role_id"])) {
					$rowData["role_id"] = 2;
				}

				// Ensure email_verified_at is set
				if (!isset($rowData["email_verified_at"]) || empty($rowData["email_verified_at"])) {
					$rowData["email_verified_at"] = now();
				}
				break;
		}

		return $rowData;
	}

	/**
	 * Process a batch of records with optimized bulk operations
	 *
	 * @param array $batch Batch of records to process
	 * @param string $modelClass Model class name
	 * @param string|null $uniqueField Unique field for upsert
	 * @return array Batch processing statistics
	 */
	private static function processBatch(array $batch, string $modelClass, ?string $uniqueField = null): array
	{
		$stats = ["created" => 0, "updated" => 0, "skipped" => 0, "errors" => 0];

		if (empty($batch)) {
			return $stats;
		}

		// Ultra-fast bulk insert for seeders (with individual record handling for Mensile)
		try {
			$model = new $modelClass();
			$tableName = $model->getTable();
			$className = class_basename($modelClass);

			// Add timestamps if model uses them
			if ($model->usesTimestamps()) {
				$now = now();
				foreach ($batch as &$item) {
					$item["created_at"] = $item["created_at"] ?? $now;
					$item["updated_at"] = $item["updated_at"] ?? $now;
				}
			}

			// Special handling for Mensile: process individually to handle updates
			if ($className === "Mensile" && !empty($batch)) {
				foreach ($batch as $rowData) {
					try {
						// Handle explicit existing record override (set by transformMensile when record exists)
						if (!empty($rowData["__existing_record_id"])) {
							$existingId = $rowData["__existing_record_id"];
							unset($rowData["__existing_record_id"]);

							$existingRecord = $modelClass::find($existingId);
							if ($existingRecord) {
								$existingRecord->update($rowData);
								$stats["updated"]++;
							}
							continue;
						}

						// New record: check if exists by ndg_year_month
						if (isset($rowData["ndg_year_month"]) && !empty($rowData["ndg_year_month"])) {
							$existing = $modelClass::where("ndg_year_month", $rowData["ndg_year_month"])->first();
							if ($existing) {
								$existing->update($rowData);
								$stats["updated"]++;
							} else {
								$modelClass::create($rowData);
								$stats["created"]++;
							}
						} else {
							$modelClass::create($rowData);
							$stats["created"]++;
						}
					} catch (\Exception $e) {
						$stats["errors"]++;
					}
				}
			} elseif (!empty($batch)) {
				// Optimized bulk INSERT IGNORE approach for other models
				try {
					// Get column names from first row
					$columns = array_keys($batch[0]);
					$columnList = "`" . implode("`, `", $columns) . "`";

					// Build VALUES clause with placeholders
					$placeholders = "(" . implode(", ", array_fill(0, count($columns), "?")) . ")";
					$valuesClause = implode(", ", array_fill(0, count($batch), $placeholders));

					// Flatten all values in correct order
					$allValues = [];
					foreach ($batch as $row) {
						foreach ($columns as $column) {
							$allValues[] = $row[$column] ?? null;
						}
					}

					// Execute bulk INSERT IGNORE
					DB::statement("INSERT IGNORE INTO `{$tableName}` ({$columnList}) VALUES {$valuesClause}", $allValues);
					$stats["created"] += count($batch);
				} catch (\Exception $e) {
					// Fallback to individual inserts if bulk fails
					foreach ($batch as $rowData) {
						try {
							$columns = array_keys($rowData);
							$placeholders = array_fill(0, count($columns), "?");
							$values = array_values($rowData);

							DB::statement(
								"INSERT IGNORE INTO `{$tableName}` (`" .
									implode("`, `", $columns) .
									"`) VALUES (" .
									implode(", ", $placeholders) .
									")",
								$values
							);
							$stats["created"]++;
						} catch (\Exception $e2) {
							$stats["errors"]++;
						}
					}
				}
			}
		} catch (\Exception $e) {
			$stats["errors"] += count($batch);
			echo "Batch failed: " . substr($e->getMessage(), 0, 100) . "...\n";
		}

		return $stats;
	}

	/**
	 * Link Tiraggi to Delibere based on normalized id_pratica = id_delibera
	 * This is a post-processing step to ensure all tiraggi are linked to their delibere
	 */
	private static function linkTiraggiToDelibere(): void
	{
		echo "Linking tiraggi to delibere...\n";

		// Get all tiraggi with id_pratica but no delibera_numero_pratica
		$tiraggi = \App\Models\Tiraggio::whereNotNull("id_pratica")->whereNull("delibera_numero_pratica")->get();

		$linked = 0;
		foreach ($tiraggi as $tiraggio) {
			// Normalize id_pratica (remove leading zeros)
			$normalizedId = ltrim((string) $tiraggio->id_pratica, "0");
			if ($normalizedId !== "") {
				// Direct DB lookup for delibera
				$delibera = \App\Models\Delibera::where("numero_pratica", $normalizedId)->first();
				if ($delibera) {
					$tiraggio->delibera_numero_pratica = $delibera->numero_pratica;
					$tiraggio->save();
					$linked++;
				}
			}
		}

		echo "Linked {$linked} tiraggi to delibere\n";
	}

	/**
	 * Hash all user passwords after import completion
	 * This method should be called after successful User import to secure passwords
	 * Uses bulk operations for maximum speed (200+ users/sec)
	 *
	 * @param bool $silent Silent mode flag
	 * @return void
	 */
	public static function hashUserPasswords(bool $silent = false): void
	{
		if (!$silent) {
			echo "Starting password hashing for imported users...\n";
		}

		// Get all users with plain text passwords using raw query for speed
		$users = DB::table("users")->where("password", "not like", '$2y$%')->select("ndg", "password")->get();

		if ($users->isEmpty()) {
			if (!$silent) {
				echo "No users with plain text passwords found.\n";
			}
			return;
		}

		$total = $users->count();
		if (!$silent) {
			echo "Found {$total} users to hash passwords for...\n";
		}

		$batchSize = 500;
		$processed = 0;
		$startTime = microtime(true);

		// Process in large batches with bulk SQL operations
		$users->chunk($batchSize)->each(function ($userBatch) use (&$processed, $total, $startTime, $silent) {
			$updates = [];
			$ndgs = [];

			// Pre-hash all passwords in the batch
			foreach ($userBatch as $user) {
				$hashedPassword = password_hash($user->password, PASSWORD_BCRYPT);
				$updates[] = $hashedPassword;
				$ndgs[] = $user->ndg;
			}

			// Build optimized bulk UPDATE query
			$caseStatements = [];
			foreach ($updates as $index => $hashedPassword) {
				$ndg = addslashes($ndgs[$index]);
				$caseStatements[] = "WHEN '{$ndg}' THEN '" . addslashes($hashedPassword) . "'";
			}

			// Execute single bulk UPDATE
			$sql =
				"UPDATE users SET password = CASE ndg " .
				implode(" ", $caseStatements) .
				" END WHERE ndg IN ('" .
				implode("','", array_map("addslashes", $ndgs)) .
				"')";

			DB::statement($sql);
			$processed += count($updates);

			// Show progress with speed calculation
			if (!$silent) {
				$progress = round(($processed / $total) * 100);
				$elapsed = microtime(true) - $startTime;
				$rate = $processed / $elapsed;
				$eta = ($total - $processed) / $rate;

				echo "Progress: {$progress}% ({$processed}/{$total}) - Rate: " .
					round($rate, 1) .
					" users/sec - ETA: " .
					round($eta, 1) .
					"s\n";
			}

			// Force garbage collection periodically
			if ($processed % 2000 === 0) {
				gc_collect_cycles();
			}
		});

		if (!$silent) {
			$totalTime = microtime(true) - $startTime;
			echo "Password hashing completed!\n";
			echo "Processed: {$processed} users\n";
			echo "Total time: " . round($totalTime, 2) . " seconds\n";
			echo "All passwords are now securely hashed with bcrypt cost=10!\n";
		}

		// CRITICAL: Delete password cache file after import is complete
		// The file was created at the start of migrate:fresh:safe
		// Now that passwords are in DB, we don't need the file anymore
		// It will be recreated by migrate:fresh:safe on next run
		self::deletePasswordCache($silent);
	}

	/**
	 * Delete password cache file after import is complete
	 * The file is recreated by migrate:fresh:safe before each drop
	 *
	 * @param bool $silent Silent mode flag
	 * @return void
	 */
	private static function deletePasswordCache(bool $silent = false): void
	{
		$cacheFile = storage_path("app/cache/user_passwords_backup.json");

		try {
			if (file_exists($cacheFile)) {
				unlink($cacheFile);

				if (!$silent) {
					echo "Deleted password cache file for security\n";
					echo "File will be recreated on next migrate:fresh:safe\n\n";
				}
			}
		} catch (\Exception $e) {
			if (!$silent) {
				echo "Warning: Could not delete password cache: " . $e->getMessage() . "\n\n";
			}
		}
	}

	/**
	 * Close tiraggi that have reached their importo_deliberato limit (direct DB query)
	 */
	private static function closeTiraggiAfterTrattenuta(): void
	{
		// Find all accepted tiraggi where trattenuta_totale >= importo_deliberato
		$tiraggiToClose = Tiraggio::where("status", "accepted")
			->whereRaw("trattenuta_totale >= importo_deliberato")
			->where("importo_deliberato", ">", 0)
			->get();

		if ($tiraggiToClose->isEmpty()) {
			echo "No tiraggi to close\n";
			return;
		}

		echo "Closing {$tiraggiToClose->count()} tiraggi...\n";

		foreach ($tiraggiToClose as $tiraggio) {
			// Refresh from DB to get ACTUAL current status
			$tiraggio->refresh();

			// CRITICAL: Check if tiraggio is already closed - skip if already paid
			if ($tiraggio->status === "paid") {
				Log::channel("trattenuta")->warning("Tiraggio {$tiraggio->id} is already closed (status: paid) - skipping");
				continue;
			}

			// Only process if status is still "accepted"
			if ($tiraggio->status !== "accepted") {
				Log::channel("trattenuta")->warning(
					"Tiraggio {$tiraggio->id} has unexpected status: {$tiraggio->status} - skipping"
				);
				continue;
			}

			// Check if user has filiale role - skip notifications
			$userRoleName = $tiraggio->user->role->name ?? null;
			$isFilialeUser = $userRoleName && strtolower($userRoleName) === "filiale";

			$oldTrattenuta = $tiraggio->trattenuta_totale;
			$delibera_numero_pratica = $tiraggio->delibera_numero_pratica;
			$tiraggio->status = "paid";
			$tiraggio->trattenuta_totale = $tiraggio->importo_deliberato;
			$tiraggio->data_fine = now();
			$tiraggio->save();
			$tiraggio->refresh();

			$tiraggio_open = Tiraggio::where("user_ndg", $tiraggio->user_ndg)->where("status", "open")->first();
			if (!$tiraggio_open) {
				$tiraggio_new = Tiraggio::create([
					"user_ndg" => $tiraggio->user_ndg,
					"status" => "open",
					"tan" => $tiraggio->tan ?? ($tiraggio->delibera->tan ?? null),
					"taeg" => $tiraggio->taeg ?? ($tiraggio->delibera->taeg ?? null),
					"delibera_numero_pratica" => $delibera_numero_pratica,
				]);
				Log::channel("trattenuta")->info(
					"Tiraggio {$tiraggio->id} closed - trattenuta: {$oldTrattenuta} → {$tiraggio->importo_deliberato} - Open new tiraggio {$tiraggio_new->id}"
				);
			} else {
				Log::channel("trattenuta")->info(
					"Tiraggio {$tiraggio->id} closed - trattenuta: {$oldTrattenuta} → {$tiraggio->importo_deliberato} - Tiraggio with status open already exists"
				);
			}

			// Send notifications ONLY when closing for the first time AND user is NOT filiale
			if (!$isFilialeUser) {
				// $tiraggio->user->notify(new UserCloseTiraggioNotification($tiraggio, $tiraggio->user));
				Log::channel("trattenuta")->info(
					"Notification sent to user {$tiraggio->user_ndg} for tiraggio {$tiraggio->id}"
				);
			} else {
				Log::channel("trattenuta")->warning(
					"Skipping notification for user {$tiraggio->user_ndg} (filiale role: {$userRoleName})"
				);
			}

			// Always notify filiale (if exists)
			if ($tiraggio->user->filiale) {
				$tiraggio->user->filiale->notify(new FilialeCloseTiraggioNotification($tiraggio, $tiraggio->user->filiale));
			}
		}

		echo "Closed {$tiraggiToClose->count()} tiraggi\n";
	}

	/**
	 * Update tiraggio trattenuta_totale from actual trattenute in DB
	 */
	private static function updateTiraggioTrattenutaFromDB(): void
	{
		// Get all accepted tiraggi
		$tiraggi = Tiraggio::where("status", "accepted")->get();

		if ($tiraggi->isEmpty()) {
			echo "No accepted tiraggi to update\n";
			return;
		}

		echo "Updating tiraggi trattenuta from DB...\n";
		$updated = 0;

		foreach ($tiraggi as $tiraggio) {
			// Skip if already paid
			if ($tiraggio->status === "paid") {
				continue;
			}

			// Calculate actual trattenuta_totale from trattenute
			$actualTrattenutaTotale = \App\Models\Trattenuta::where(
				"tiraggio_numero_pratica",
				$tiraggio->numero_pratica
			)->sum("importo_trattenuto");

			$oldTrattenuta = $tiraggio->trattenuta_totale ?? 0;

			// Only update if there's an actual change
			if (abs($actualTrattenutaTotale - $oldTrattenuta) > 0.01) {
				$tiraggio->trattenuta_totale = $actualTrattenutaTotale;
				$tiraggio->save();
				$updated++;
				Log::channel("trattenuta")->info(
					"Tiraggio {$tiraggio->id} updated: old trattenuta {$oldTrattenuta} → new trattenuta {$actualTrattenutaTotale}"
				);
			}
		}

		echo "Updated {$updated} tiraggi\n";
	}

	/**
	 * Create filiale manager users after filiale import
	 */
	private static function createFilialeManagersAfterImport(): void
	{
		echo "Creating filiale managers after import...\n";

		// Get role_id for 'filiale' role
		$filialeRole = \App\Models\Role::where("name", "filiale")->first();
		if (!$filialeRole) {
			echo "Error: 'filiale' role not found\n";
			return;
		}

		// Get all filiali with email from database (after import)
		$filiali = \App\Models\Filiale::whereNotNull("email")->get();

		$created = 0;
		$skipped = 0;

		foreach ($filiali as $filiale) {
			// Check if manager already exists
			$existingManager = \App\Models\User::where("email", $filiale->email)->first();
			if ($existingManager) {
				$skipped++;
				continue;
			}

			// Create email prefix (first part before @)
			$emailPrefix = explode("@", $filiale->email)[0];
			$password = $emailPrefix . "d3s10666";

			// Create manager user
			$manager = new \App\Models\User();
			$manager->ndg = "FILIALE_" . $filiale->codice;
			$manager->email = $filiale->email;
			$manager->password = bcrypt($password);
			$manager->ragione_sociale = "Gestore " . $filiale->descrizione;
			$manager->filiale_id = $filiale->id;
			$manager->role_id = $filialeRole->id;
			$manager->sospeso = false;
			$manager->save();

			$created++;
			echo "Created manager for filiale {$filiale->codice}: {$filiale->email}\n";
		}

		echo "Filiale managers created: {$created}, skipped: {$skipped}\n";
	}
}
