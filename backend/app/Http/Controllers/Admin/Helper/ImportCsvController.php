<?php

namespace App\Http\Controllers\Admin\Helper;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Admin\Helper\Core\CsvHelper;
use App\Http\Controllers\Admin\Helper\Core\CsvBackupManager;
use App\Http\Controllers\Admin\Helper\ImportSeederService;
use App\Http\Controllers\Admin\Helper\ImportJobService;
use App\Services\LogEncryptionService;

class ImportCsvController extends Controller
{
	/**
	 * Import CSV for seeder - uses ImportSeederService
	 *
	 * @param string $seederName Seeder name
	 * @param string $modelClass Model class name
	 * @param string|null $uniqueField Unique field for upsert
	 * @param bool $skipTiraggio Skip tiraggio-specific operations
	 * @return void
	 */
	public static function importForSeeder(
		string $seederName,
		string $modelClass,
		?string $uniqueField = null,
		bool $skipTiraggio = false
	): void {
		ImportSeederService::import($seederName, $modelClass, $uniqueField, $skipTiraggio, false);
	}

	/**
	 * Import CSV for job - uses ImportJobService
	 *
	 * @param string $fileName File name
	 * @param string $subfolder Subfolder name
	 * @param string $modelClass Model class name
	 * @param string|null $uniqueField Unique field for upsert
	 * @return array Import statistics
	 */
	public static function importForJob(
		string $fileName,
		string $subfolder,
		string $modelClass,
		?string $uniqueField = null
	): array {
		return ImportJobService::import($fileName, $subfolder, $modelClass, $uniqueField);
	}

	/**
	 * Backup existing hashed passwords BEFORE seeding starts
	 * CRITICAL: Called at the START of DatabaseSeeder, BEFORE any truncate
	 * This preserves passwords across migrate:fresh operations
	 *
	 * @return void
	 */
	public static function backupPasswordsBeforeSeeding(): void
	{
		$cacheFile = storage_path("app/cache/user_passwords_backup.json");

		// Create cache directory if not exists
		$cacheDir = dirname($cacheFile);
		if (!file_exists($cacheDir)) {
			mkdir($cacheDir, 0755, true);
		}

		try {
			// Check if users table exists (might not exist yet if this is first migration)
			if (!Schema::hasTable("users")) {
				echo "Users table does not exist yet (first migration)\n";
				echo "   Skipping password backup\n\n";
				return;
			}

			// Check if table has any data
			$totalUsers = DB::table("users")->count();
			echo "BACKUP: Saving existing hashed passwords...\n";
			echo "Current users in database: {$totalUsers}\n";

			if ($totalUsers === 0) {
				echo "No users to backup (empty table)\n\n";
				return;
			}

			// Get users with bcrypt hashed passwords
			// Use LIKE for better compatibility - bcrypt always starts with $2y$, $2a$, $2b$, or $2x$
			$users = DB::table("users")
				->select("email", "password")
				->where(function ($query) {
					$query
						->where("password", "like", "\$2y\$%")
						->orWhere("password", "like", "\$2a\$%")
						->orWhere("password", "like", "\$2b\$%")
						->orWhere("password", "like", "\$2x\$%");
				})
				->get();

			echo "Found " . $users->count() . " users with bcrypt passwords\n";

			if ($users->isEmpty()) {
				echo "No hashed passwords found (all plain text or first import)\n\n";
				return;
			}

			// Build cache array indexed by email
			$passwordCache = [];
			foreach ($users as $user) {
				if ($user->email) {
					$passwordCache[$user->email] = $user->password;
				}
			}

			// Save to JSON file (survives migrate:fresh!)
			file_put_contents($cacheFile, json_encode($passwordCache, JSON_PRETTY_PRINT));

			echo "Backed up " . count($passwordCache) . " hashed passwords\n";
			echo "File: storage/app/cache/user_passwords_backup.json\n";
			echo "These passwords will be reused if email matches in CSV\n\n";
		} catch (\Exception $e) {
			echo "Warning: Could not backup passwords: " . $e->getMessage() . "\n\n";
		}
	}

	/**
	 * Hash user passwords after import - uses ImportSeederService
	 *
	 * @param bool $silent Silent mode flag
	 * @return void
	 */
	public static function hashUserPasswordsAfterImport(bool $silent = false): void
	{
		ImportSeederService::hashUserPasswords($silent);
	}

	/**
	 * Add default values for missing required fields based on model type
	 * Used by backend interface import
	 *
	 * @param array $rowData Current row data
	 * @param string $modelClass Model class name
	 * @return array Row data with defaults added
	 */
	private function addDefaultRequiredFields(array $rowData, string $modelClass): array
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
	 * Mostra la pagina di importazione CSV
	 *
	 * @param string $crud
	 * @return \Illuminate\View\View
	 */
	public function showImportForm($crud)
	{
		// Costruisce il nome della classe del modello dinamicamente
		$modelClass = "App\\Models\\" . Str::studly($crud);

		// Verifica se la classe del modello esiste
		if (!class_exists($modelClass)) {
			return back()->with("error", "Modello non trovato");
		}

		// Ottiene le colonne della tabella
		$tableColumns = $this->getTableColumns($modelClass);

		return view("vendor.backpack.crud.import_csv", [
			"crud" => $crud,
			"tableColumns" => $tableColumns,
			"modelClass" => $modelClass,
			"crud_route" => config("backpack.base.route_prefix", "admin") . "/" . $crud,
		]);
	}

	/**
	 * Ottiene le colonne della tabella dal modello
	 *
	 * @param string $modelClass
	 * @return array
	 */
	private function getTableColumns($modelClass)
	{
		$model = new $modelClass();
		return Schema::getColumnListing($model->getTable());
	}

	/**
	 * Ottiene le colonne non nullable della tabella dal modello
	 *
	 * @param string $modelClass
	 * @return array
	 */
	private function getRequiredColumns($modelClass)
	{
		$model = new $modelClass();
		$table = $model->getTable();
		$database = config("database.connections.mysql.database");

		$requiredColumns = DB::table("INFORMATION_SCHEMA.COLUMNS")
			->where("TABLE_SCHEMA", $database)
			->where("TABLE_NAME", $table)
			->where("IS_NULLABLE", "NO")
			->where("COLUMN_NAME", "!=", "id") // Escludiamo l'ID che viene generato automaticamente
			->where("COLUMN_DEFAULT", null) // Solo campi senza default
			->pluck("COLUMN_NAME")
			->toArray();

		return $requiredColumns;
	}

	/**
	 * Analizza il file CSV caricato e mostra l'interfaccia di mappatura
	 *
	 * @param Request $request
	 * @param string $crud
	 * @return \Illuminate\View\View
	 */
	public function analyzeUploadedFile(Request $request, $crud)
	{
		// Increase execution time and memory for large imports
		set_time_limit(0); // No timeout
		ini_set("memory_limit", "512M"); // Increase memory limit

		$request->validate([
			"csv_file" => "required|file|mimes:csv,txt", // Removed max:200000 limit
		]);

		$modelClass = "App\\Models\\" . Str::studly($crud);

		if (!class_exists($modelClass)) {
			return back()->with("error", "Modello non trovato");
		}

		$file = $request->file("csv_file");

		// Clean all files in csv-uploads directory before saving new file
		$uploadDir = "csv-uploads";
		$uploadFiles = Storage::disk("backups")->files($uploadDir);
		foreach ($uploadFiles as $uploadFile) {
			Storage::disk("backups")->delete($uploadFile);
		}

		// Capture original file metadata BEFORE saving
		$originalFileName = $file->getClientOriginalName();
		$originalFileLastModified = $request->input("client_last_modified");
		if (!$originalFileLastModified && $file->isValid() && $file->getRealPath()) {
			// Fallback: use temp file mtime (may be upload time, not original)
			$originalFileLastModified = date("c", filemtime($file->getRealPath()));
		}

		// Convert ISO 8601 to readable timestamp format (Y-m-d H:i:s)
		if ($originalFileLastModified) {
			try {
				$dateTime = new \DateTime($originalFileLastModified);
				$originalFileLastModified = $dateTime->format("Y-m-d H:i:s");
			} catch (\Exception $e) {
				// If parsing fails, keep original format
				Log::warning("Failed to parse file_last_modified: " . $originalFileLastModified);
			}
		}

		$fileName = time() . "_" . $originalFileName;
		$filePath = $file->storeAs("csv-uploads", $fileName, "backups");

		// Legge l'intestazione del CSV
		$csvPath = Storage::disk("backups")->path($filePath);

		// Prova a rilevare il delimitatore (virgola, punto e virgola, tab)
		$delimiter = CsvHelper::detectDelimiter($csvPath);

		$handle = fopen($csvPath, "r");
		$csvHeaders = fgetcsv($handle, 0, $delimiter);
		fclose($handle);

		// Se c'è una sola intestazione e contiene più campi concatenati, prova a suddividerla
		if (count($csvHeaders) === 1 && strpos($csvHeaders[0], ":") !== false) {
			$csvHeaders = explode(":", $csvHeaders[0]);
		}

		// Ottiene le colonne della tabella
		$tableColumns = $this->getTableColumns($modelClass);

		// Ottiene le colonne obbligatorie (non nullable)
		$requiredColumns = $this->getRequiredColumns($modelClass);

		// Prova a mappare automaticamente le colonne in base al nome
		$columnMapping = [];
		foreach ($csvHeaders as $index => $header) {
			$normalizedHeader = Str::snake(strtolower(trim($header)));
			if (in_array($normalizedHeader, $tableColumns)) {
				$columnMapping[$index] = $normalizedHeader;
			} else {
				$columnMapping[$index] = "";
			}
		}

		return view("vendor.backpack.crud.import_mapping", [
			"crud" => $crud,
			"csvHeaders" => $csvHeaders,
			"tableColumns" => $tableColumns,
			"requiredColumns" => $requiredColumns,
			"columnMapping" => $columnMapping,
			"filePath" => $filePath,
			"modelClass" => $modelClass,
			"crud_route" => config("backpack.base.route_prefix", "admin") . "/" . $crud,
			"delimiter" => $delimiter,
			"originalFileName" => $originalFileName,
			"originalFileLastModified" => $originalFileLastModified,
		]);
	}

	/**
	 * Esegue l'importazione del CSV nel database
	 *
	 * @param Request $request
	 * @param string $crud
	 * @return \Illuminate\Http\Response
	 */
	public function importCsv(Request $request, $crud)
	{
		// Increase execution time and memory for large imports
		set_time_limit(0); // No timeout
		ini_set("memory_limit", "1G"); // Increase memory limit for better performance

		// Inizializzazione del buffer di output
		if (ob_get_level() == 0) {
			ob_start();
		}

		$request->validate([
			"file_path" => "required|string",
			"column_mapping" => "required|array",
			"unique_field" => "nullable|string",
			"delimiter" => "required|string",
			"import_behavior" => "nullable|string|in:update_insert,update_only",
		]);

		$modelClass = "App\\Models\\" . Str::studly($crud);

		if (!class_exists($modelClass)) {
			return response()->json(["error" => "Modello non trovato"], 404);
		}

		$model = new $modelClass();
		$tableName = $model->getTable();
		$filePath = $request->file_path;
		$columnMapping = $request->column_mapping;
		$uniqueField = $request->unique_field;
		$delimiter = $request->delimiter;
		$importBehavior = $request->import_behavior ?? "update_insert"; // Default to update_insert if not provided

		// Get original file metadata from request (captured at upload time)
		$originalFileName = $request->input("original_file_name", basename($filePath));
		$originalFileLastModified = $request->input("original_file_last_modified");

		// Convert ISO 8601 to readable timestamp format if needed (should already be converted, but double-check)
		if ($originalFileLastModified && strpos($originalFileLastModified, "T") !== false) {
			try {
				$dateTime = new \DateTime($originalFileLastModified);
				$originalFileLastModified = $dateTime->format("Y-m-d H:i:s");
			} catch (\Exception $e) {
				// If parsing fails, keep original format
				Log::warning("Failed to parse file_last_modified in importCsv: " . $originalFileLastModified);
			}
		}

		// Crea un backup della tabella
		$backupFile = CsvBackupManager::createBackup($tableName, "backend", true);

		// Legge il file CSV
		$csvPath = Storage::disk("backups")->path($filePath);
		$handle = fopen($csvPath, "r");

		// Salta l'intestazione
		fgetcsv($handle, 0, $delimiter);

		$totalRows = 0;
		$createdRows = 0;
		$updatedRows = 0;
		$skippedRows = 0;
		$errors = [];

		// Prepare physical log storage (use /storage/logs to avoid permission issues)
		$logBasePath = "logs/imports/{$tableName}/" . date("Y-m-d_H-i-s");
		$logPath = storage_path($logBasePath);

		// Create log directory
		if (!file_exists($logPath)) {
			mkdir($logPath, 0755, true);
		}

		// Initialize log files
		$newRecordsLog = [];
		$updatedRecordsLog = [];
		$skippedRecordsLog = [];

		DB::beginTransaction();

		try {
			// Calcola il numero totale di righe
			$totalRowsCSV = CsvHelper::countCsvRows($csvPath, $delimiter) - 1; // Sottrae 1 per l'intestazione

			// Optimized buffer for batch operations
			// Process operations in larger batches for better performance
			$operationsBuffer = [];
			$maxBufferSize = 200; // Increased buffer size for better performance
			$lastSentTime = microtime(true);
			$sendInterval = 0.5; // Less frequent updates for better performance

			// Teniamo traccia degli ID delle operazioni già inviate per evitare duplicati
			$sentOperations = [];

			// Forza l'aggiornamento iniziale
			$lastProgress = -1;

			while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
				$totalRows++;
				$rowData = [];

				// Mappa i dati in base alla configurazione
				foreach ($columnMapping as $csvIndex => $dbColumn) {
					if (!empty($dbColumn) && isset($data[$csvIndex])) {
						$value = $data[$csvIndex];

						// Convert empty strings to null for nullable fields (especially foreign keys)
						if ($value === "" && in_array($dbColumn, ["page_id"])) {
							$value = null;
						}

						$rowData[$dbColumn] = $value;
					}
				}

				// Add default values for missing required fields (same as in seeder import)
				$rowData = $this->addDefaultRequiredFields($rowData, $modelClass);

				if (empty($rowData)) {
					$skippedRows++;

					// Add to physical log
					$skippedRecordsLog[] = [
						"row" => $totalRows,
						"unique_field" => null,
						"unique_value" => null,
						"reason" => "empty_row_data",
						"data" => [],
						"timestamp" => now()->toDateTimeString(),
					];
					continue;
				}

				// Verifica se esiste un record con lo stesso campo unique
				if ($uniqueField && !empty($rowData[$uniqueField])) {
					$existingRecord = $modelClass::where($uniqueField, $rowData[$uniqueField])->first();

					// Prepara informazioni dettagliate sull'operazione
					$recordInfo = [
						"field" => $uniqueField,
						"value" => $rowData[$uniqueField],
						"row" => $totalRows,
						"operation_id" => uniqid(), // Identificativo univoco per questa operazione
					];

					if ($existingRecord) {
						// Update the existing record
						$primaryKey = $existingRecord->getKeyName();
						$primaryKeyValue = $existingRecord->getKey();
						$existingRecord->update($rowData);
						$updatedRows++;
						$recordInfo["action"] = "update";
						$recordInfo["primary_key"] = $primaryKey;
						$recordInfo["primary_key_value"] = $primaryKeyValue;

						// Add password generation flag to recordInfo for frontend display
						if (
							$modelClass === "App\\Models\\User" &&
							isset($existingRecord->passwordAutoGenerated) &&
							$existingRecord->passwordAutoGenerated
						) {
							$recordInfo["password_auto_generated"] = true;
						}

						// Add to physical log
						$logEntry = [
							"row" => $totalRows,
							"primary_key" => $primaryKey,
							"primary_key_value" => $primaryKeyValue,
							"unique_field" => $uniqueField,
							"unique_value" => $rowData[$uniqueField],
							"data" => $rowData,
							"timestamp" => now()->toDateTimeString(),
						];

						// Add password generation flag if User model and password was auto-generated
						if (
							$modelClass === "App\\Models\\User" &&
							isset($existingRecord->passwordAutoGenerated) &&
							$existingRecord->passwordAutoGenerated
						) {
							$logEntry["password_auto_generated"] = true;
							$logEntry["password_info"] =
								"Password auto-generated from NDG + 'd3s10666' and hashed with bcrypt";
						}

						$updatedRecordsLog[] = $logEntry;
					} else {
						// Check if we should insert new records
						if ($importBehavior === "update_only") {
							// Skip insertion for update_only mode
							$skippedRows++;
							$recordInfo["action"] = "skip";
							$recordInfo["reason"] = "update_only_mode";

							// Add to physical log
							$skippedRecordsLog[] = [
								"row" => $totalRows,
								"unique_field" => $uniqueField,
								"unique_value" => $rowData[$uniqueField],
								"reason" => "update_only_mode",
								"data" => $rowData,
								"timestamp" => now()->toDateTimeString(),
							];
						} else {
							// Create new record in update_insert mode
							$newRecord = $modelClass::create($rowData);
							$createdRows++;
							$recordInfo["action"] = "insert";
							$primaryKey = $newRecord->getKeyName();
							$primaryKeyValue = $newRecord->getKey();
							$recordInfo["primary_key"] = $primaryKey;
							$recordInfo["primary_key_value"] = $primaryKeyValue;

							// Add password generation flag to recordInfo for frontend display
							if (
								$modelClass === "App\\Models\\User" &&
								isset($newRecord->passwordAutoGenerated) &&
								$newRecord->passwordAutoGenerated
							) {
								$recordInfo["password_auto_generated"] = true;
							}

							// Add to physical log
							$logEntry = [
								"row" => $totalRows,
								"primary_key" => $primaryKey,
								"primary_key_value" => $primaryKeyValue,
								"unique_field" => $uniqueField,
								"unique_value" => $rowData[$uniqueField] ?? null,
								"data" => $rowData,
								"timestamp" => now()->toDateTimeString(),
							];

							// Add password generation flag if User model and password was auto-generated
							if (
								$modelClass === "App\\Models\\User" &&
								isset($newRecord->passwordAutoGenerated) &&
								$newRecord->passwordAutoGenerated
							) {
								$logEntry["password_auto_generated"] = true;
								$logEntry["password_info"] =
									"Password auto-generated from NDG + 'd3s10666' and hashed with bcrypt";
							}

							$newRecordsLog[] = $logEntry;
						}
					}
				} else {
					// Unique field is empty or not specified
					// Check import behavior: if update_only and unique field is empty, skip the record
					if ($uniqueField && $importBehavior === "update_only") {
						// Skip insertion for update_only mode when unique field is empty
						$skippedRows++;
						$recordInfo = [
							"action" => "skip",
							"reason" => "update_only_mode_empty_unique_field",
							"row" => $totalRows,
							"operation_id" => uniqid(),
						];

						// Add to physical log
						$skippedRecordsLog[] = [
							"row" => $totalRows,
							"unique_field" => $uniqueField,
							"unique_value" => null,
							"reason" => "update_only_mode_empty_unique_field",
							"data" => $rowData,
							"timestamp" => now()->toDateTimeString(),
						];
					} else {
						// No unique field specified OR update_insert mode: always insert
						$newRecord = $modelClass::create($rowData);
						$createdRows++;

						// Get primary key name and value from the newly created record
						$primaryKey = $newRecord->getKeyName();
						$primaryKeyValue = $newRecord->getKey();

						$recordInfo = [
							"action" => "insert",
							"primary_key" => $primaryKey,
							"primary_key_value" => $primaryKeyValue,
							"row" => $totalRows,
							"operation_id" => uniqid(), // Identificativo univoco per questa operazione
						];

						// Add password generation flag to recordInfo for frontend display
						if (
							$modelClass === "App\\Models\\User" &&
							isset($newRecord->passwordAutoGenerated) &&
							$newRecord->passwordAutoGenerated
						) {
							$recordInfo["password_auto_generated"] = true;
						}

						// Add to physical log
						$logEntry = [
							"row" => $totalRows,
							"primary_key" => $primaryKey,
							"primary_key_value" => $primaryKeyValue,
							"unique_field" => $uniqueField,
							"unique_value" => null,
							"data" => $rowData,
							"timestamp" => now()->toDateTimeString(),
						];

						// Add password generation flag if User model and password was auto-generated
						if (
							$modelClass === "App\\Models\\User" &&
							isset($newRecord->passwordAutoGenerated) &&
							$newRecord->passwordAutoGenerated
						) {
							$logEntry["password_auto_generated"] = true;
							$logEntry["password_info"] =
								"Password auto-generated from NDG + 'd3s10666' and hashed with bcrypt";
						}

						$newRecordsLog[] = $logEntry;
					}
				}

				// Aggiungiamo l'operazione al buffer solo se non è già stata inviata
				$operationKey =
					$recordInfo["action"] . "_" . ($recordInfo["primary_key_value"] ?? "") . "_row" . $recordInfo["row"];
				if (!in_array($operationKey, $sentOperations)) {
					$operationsBuffer[] = $recordInfo;
					$sentOperations[] = $operationKey;
				}

				// Calcola progresso corrente
				$progress = round(($totalRows / $totalRowsCSV) * 100);
				$currentTime = microtime(true);

				// Inviamo un aggiornamento se:
				// 1. Il buffer ha raggiunto la dimensione massima, OPPURE
				// 2. È passato abbastanza tempo dall'ultimo invio, OPPURE
				// 3. La percentuale di progresso è cambiata, OPPURE
				// 4. Siamo all'ultima riga
				if (
					count($operationsBuffer) >= $maxBufferSize ||
					$currentTime - $lastSentTime >= $sendInterval ||
					$progress != $lastProgress ||
					$totalRows == $totalRowsCSV
				) {
					$lastProgress = $progress;
					$lastSentTime = $currentTime;

					// Invia JSON con le operazioni bufferizzate
					echo json_encode([
						"status" => "processing",
						"progress" => $progress,
						"total" => $totalRows,
						"created" => $createdRows,
						"updated" => $updatedRows,
						"skipped" => $skippedRows,
						"current_operation" => end($operationsBuffer), // L'ultima operazione come operazione corrente
						"operations" => $operationsBuffer, // Tutte le operazioni in buffer
						"total_rows" => $totalRowsCSV,
					]);

					// Flush del buffer solo se è attivo
					if (ob_get_level() > 0) {
						ob_flush();
						flush();
					}

					// Reset del buffer dopo l'invio
					$operationsBuffer = [];
				}
			}

			// Send any remaining operations in buffer (final batch)
			if (!empty($operationsBuffer)) {
				echo json_encode([
					"status" => "processing",
					"progress" => 100,
					"total" => $totalRows,
					"created" => $createdRows,
					"updated" => $updatedRows,
					"skipped" => $skippedRows,
					"current_operation" => end($operationsBuffer),
					"operations" => $operationsBuffer,
					"total_rows" => $totalRowsCSV,
				]);

				if (ob_get_level() > 0) {
					ob_flush();
					flush();
				}
			}

			DB::commit();

			// Save physical log files
			$this->saveImportLogs($logPath, $tableName, $newRecordsLog, $updatedRecordsLog, $skippedRecordsLog, [
				"total_rows" => $totalRows,
				"created" => $createdRows,
				"updated" => $updatedRows,
				"skipped" => $skippedRows,
				"unique_field" => $uniqueField,
				"import_behavior" => $importBehavior,
				"timestamp" => now()->toDateTimeString(),
				"file_name" => $originalFileName,
				"file_last_modified" => $originalFileLastModified,
			]);
			$this->makeLogsReadonly($logPath);
			$this->pruneOldLogs(3);

			// Elimina il file CSV caricato dopo l'importazione riuscita
			$this->deleteUploadedFile($filePath);

			// Elimina anche eventuali file temporanei nella stessa directory
			$csvDir = dirname($filePath);
			$csvFilename = basename($filePath);
			$tempFiles = Storage::disk("backups")->files($csvDir);
			foreach ($tempFiles as $tempFile) {
				// Verifica se il file è più vecchio di un'ora per sicurezza
				if (
					$tempFile !== $filePath &&
					Storage::disk("backups")->lastModified($tempFile) < now()->subHour()->timestamp
				) {
					Storage::disk("backups")->delete($tempFile);
				}
			}

			$result = [
				"status" => "success",
				"total" => $totalRows,
				"created" => $createdRows,
				"updated" => $updatedRows,
				"skipped" => $skippedRows,
				"backupFile" => $backupFile,
				"logPath" => "/storage/{$logBasePath}",
			];

			echo json_encode($result);

			// Assicuriamoci che tutto il buffer venga inviato prima di terminare
			if (ob_get_level() > 0) {
				ob_end_flush();
			}

			// Remove uploaded CSV now (also covered in finally as fallback)
			$this->deleteUploadedFile($filePath);

			return;
		} catch (\Exception $e) {
			DB::rollBack();

			// Save partial logs if any data was processed
			if (
				isset($logPath) &&
				($totalRows > 0 || !empty($newRecordsLog) || !empty($updatedRecordsLog) || !empty($skippedRecordsLog))
			) {
				try {
					$this->saveImportLogs($logPath, $tableName, $newRecordsLog, $updatedRecordsLog, $skippedRecordsLog, [
						"total_rows" => $totalRows,
						"created" => $createdRows,
						"updated" => $updatedRows,
						"skipped" => $skippedRows,
						"unique_field" => $uniqueField ?? null,
						"import_behavior" => $importBehavior ?? null,
						"timestamp" => now()->toDateTimeString(),
						"error" => $e->getMessage(),
						"file_name" => $originalFileName ?? basename($filePath),
						"file_last_modified" => $originalFileLastModified,
					]);
					$this->makeLogsReadonly($logPath);
					$this->pruneOldLogs(3);
				} catch (\Exception $logError) {
					// Silently fail if log saving fails
					Log::error("Failed to save import logs: " . $logError->getMessage());
				}
			}

			// In caso di errore, invia una risposta di errore
			$errorResponse = [
				"status" => "error",
				"message" => $e->getMessage(),
				"backupFile" => $backupFile ?? null,
			];

			if (isset($logPath) && isset($logBasePath)) {
				$errorResponse["logPath"] = "/storage/{$logBasePath}";
			}

			echo json_encode($errorResponse);

			// Assicuriamoci che tutto il buffer venga inviato prima di terminare
			if (ob_get_level() > 0) {
				ob_end_flush();
			}

			// Remove uploaded CSV now (also covered in finally as fallback)
			$this->deleteUploadedFile($filePath);

			return;
		} finally {
			fclose($handle);
			// Ensure uploaded CSV is removed even on error
			$this->deleteUploadedFile($filePath);
		}
	}

	/**
	 * Ottiene lo stato attuale dell'importazione
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function getImportStatus(Request $request)
	{
		// Otteniamo l'ID dell'importazione dalla richiesta
		$importId = $request->get("import_id");

		// In una applicazione reale, qui prenderemmo lo stato da una cache/database
		// Per simulare un progresso in questa versione base, restituiamo una percentuale casuale
		$progress = rand(0, 100);

		return response()->json([
			"status" => $progress >= 100 ? "completed" : "processing",
			"progress" => $progress,
			"processed" => rand(10, 100),
			"total" => 100,
			"created" => rand(5, 50),
			"updated" => rand(5, 50),
			"skipped" => rand(0, 10),
		]);
	}

	/**
	 * Save physical log files for import operations
	 *
	 * @param string $logPath Base path for log files
	 * @param string $tableName Table name
	 * @param array $newRecordsLog Array of new records
	 * @param array $updatedRecordsLog Array of updated records
	 * @param array $skippedRecordsLog Array of skipped records
	 * @param array $summary Summary information
	 * @return void
	 */
	private function saveImportLogs(
		string $logPath,
		string $tableName,
		array $newRecordsLog,
		array $updatedRecordsLog,
		array $skippedRecordsLog,
		array $summary
	): void {
		// Check if encryption is enabled via LOG_CHANNEL
		$shouldEncrypt = config('logging.channels.encrypted_daily.enabled');
		$encryptionService = $shouldEncrypt ? app(LogEncryptionService::class) : null;

		// Save summary file (encrypted only if LOG_CHANNEL=encrypted_daily)
		$summaryFile = $logPath . "/summary.json";
		$summaryContent = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		if ($shouldEncrypt && $encryptionService) {
			$encryptionService->encryptAndSave($summaryFile, $summaryContent);
		} else {
			File::put($summaryFile, $summaryContent);
		}

		// Save new records log (only if there are new records)
		if (!empty($newRecordsLog)) {
			$newRecordsContent = json_encode($newRecordsLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			if ($shouldEncrypt && $encryptionService) {
				$encryptionService->encryptAndSave($logPath . "/new_records.json", $newRecordsContent);
			} else {
				File::put($logPath . "/new_records.json", $newRecordsContent);
			}
		}

		// Save updated records log (only if there are updated records)
		if (!empty($updatedRecordsLog)) {
			$updatedRecordsContent = json_encode($updatedRecordsLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			if ($shouldEncrypt && $encryptionService) {
				$encryptionService->encryptAndSave($logPath . "/updated_records.json", $updatedRecordsContent);
			} else {
				File::put($logPath . "/updated_records.json", $updatedRecordsContent);
			}
		}

		// Save skipped records log (only if there are skipped records)
		if (!empty($skippedRecordsLog)) {
			$skippedRecordsContent = json_encode($skippedRecordsLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			if ($shouldEncrypt && $encryptionService) {
				$encryptionService->encryptAndSave($logPath . "/skipped_rows.json", $skippedRecordsContent);
			} else {
				File::put($logPath . "/skipped_rows.json", $skippedRecordsContent);
			}
		}
	}

	/**
	 * Make log files and directories read-only
	 *
	 * @param string $logPath
	 * @return void
	 */
	private function makeLogsReadonly(string $logPath): void
	{
		if (!file_exists($logPath)) {
			return;
		}

		// Set directory to read/execute (no write)
		@chmod($logPath, 0555);

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($logPath, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($iterator as $item) {
			if ($item->isDir()) {
				@chmod($item->getPathname(), 0555);
			} else {
				@chmod($item->getPathname(), 0444);
			}
		}
	}

	/**
	 * Delete the uploaded CSV file safely
	 *
	 * @param string $filePath
	 * @return void
	 */
	private function deleteUploadedFile(string $filePath): void
	{
		try {
			if (Storage::disk("backups")->exists($filePath)) {
				Storage::disk("backups")->delete($filePath);
			}

			// Fallback to absolute path removal
			$absolutePath = Storage::disk("backups")->path($filePath);
			if (file_exists($absolutePath)) {
				@unlink($absolutePath);
			}
		} catch (\Exception $e) {
			Log::warning("Could not delete uploaded file {$filePath}: " . $e->getMessage());
		}
	}

	/**
	 * Keep only the most recent N log directories globally
	 * Also removes logs older than 30 days
	 *
	 * @param int $maxLogs Maximum number of recent logs to keep
	 * @return void
	 */
	private function pruneOldLogs(int $maxLogs = 3): void
	{
		$basePath = storage_path("logs/imports");

		if (!File::exists($basePath)) {
			return;
		}

		// Collect all log directories across all tables
		$dirs = [];
		$tableDirs = File::directories($basePath);
		foreach ($tableDirs as $tableDir) {
			$subDirs = File::directories($tableDir);
			$dirs = array_merge($dirs, $subDirs);
		}

		// Sort directories by last modified descending (newest first)
		usort($dirs, function ($a, $b) {
			return filemtime($b) <=> filemtime($a);
		});

		$now = time();
		$thirtyDaysAgo = $now - 30 * 24 * 60 * 60; // 30 days in seconds
		$toDelete = [];

		foreach ($dirs as $dir) {
			$dirMtime = filemtime($dir);

			// Delete if older than 30 days
			if ($dirMtime < $thirtyDaysAgo) {
				$toDelete[] = $dir;
			}
		}

		// Also delete old logs beyond maxLogs limit (if not already marked for deletion)
		if (count($dirs) > $maxLogs) {
			$oldLogs = array_slice($dirs, $maxLogs);
			foreach ($oldLogs as $oldLog) {
				if (!in_array($oldLog, $toDelete)) {
					$toDelete[] = $oldLog;
				}
			}
		}

		// Delete marked directories
		foreach ($toDelete as $dir) {
			$this->makeDirWritable($dir);
			File::deleteDirectory($dir);
		}
	}

	/**
	 * Recursively make a directory writable (dirs 0755, files 0644)
	 *
	 * @param string $path
	 * @return void
	 */
	private function makeDirWritable(string $path): void
	{
		if (!file_exists($path)) {
			return;
		}

		@chmod($path, 0755);

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($iterator as $item) {
			if ($item->isDir()) {
				@chmod($item->getPathname(), 0755);
			} else {
				@chmod($item->getPathname(), 0644);
			}
		}
	}
}
