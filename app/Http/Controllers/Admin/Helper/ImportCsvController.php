<?php

namespace App\Http\Controllers\Admin\Helper;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;

class ImportCsvController extends Controller
{
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
		ini_set('memory_limit', '512M'); // Increase memory limit
		
		$request->validate([
			"csv_file" => "required|file|mimes:csv,txt", // Removed max:200000 limit
		]);

		$modelClass = "App\\Models\\" . Str::studly($crud);

		if (!class_exists($modelClass)) {
			return back()->with("error", "Modello non trovato");
		}

		$file = $request->file("csv_file");
		$fileName = time() . "_" . $file->getClientOriginalName();
		$filePath = $file->storeAs("csv-preview", $fileName, "backups");

		// Legge l'intestazione del CSV
		$csvPath = Storage::disk("backups")->path($filePath);

		// Prova a rilevare il delimitatore (virgola, punto e virgola, tab)
		$delimiter = $this->detectDelimiter($csvPath);

		$handle = fopen($csvPath, "r");
		$csvHeaders = fgetcsv($handle, 0, $delimiter);

		// Legge fino a 100 righe per l'anteprima
		$csvPreview = [];
		$previewLimit = 100;
		$previewCount = 0;

		while (($row = fgetcsv($handle, 0, $delimiter)) !== false && $previewCount < $previewLimit) {
			$csvPreview[] = $row;
			$previewCount++;
		}

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
			"csvPreview" => $csvPreview,
		]);
	}

	/**
	 * Rileva il delimitatore di un file CSV
	 *
	 * @param string $filePath
	 * @return string
	 */
	private function detectDelimiter($filePath)
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
			return ","; // Default a virgola se il file è vuoto
		}

		foreach ($delimiters as $delimiter) {
			$counts[$delimiter] = count(str_getcsv($firstLine, $delimiter));
		}

		// Scegli il delimitatore che dà più colonne
		$maxCount = max($counts);
		$bestDelimiter = array_search($maxCount, $counts);

		return $bestDelimiter;
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
		ini_set('memory_limit', '512M'); // Increase memory limit
		
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

		// Crea un backup della tabella
		$backupFile = $this->createTableBackup($tableName);

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

		DB::beginTransaction();

		try {
			// Calcola il numero totale di righe
			$totalRowsCSV = $this->countCsvRows($csvPath, $delimiter) - 1; // Sottrae 1 per l'intestazione

			// Impostiamo un buffer per raccogliere un gruppo di operazioni
			// Invieremo più operazioni in blocco per migliorare le prestazioni
			$operationsBuffer = [];
			$maxBufferSize = 5; // Inviamo 5 operazioni alla volta al massimo
			$lastSentTime = microtime(true);
			$sendInterval = 0.3; // Invio ogni 300ms al massimo

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

				if (empty($rowData)) {
					$skippedRows++;
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
						$existingRecord->update($rowData);
						$updatedRows++;
						$recordInfo["action"] = "update";
						$recordInfo["id"] = $existingRecord->id;
					} else {
						// Check if we should insert new records
						if ($importBehavior === "update_only") {
							// Skip insertion for update_only mode
							$skippedRows++;
							$recordInfo["action"] = "skip";
							$recordInfo["reason"] = "update_only_mode";
						} else {
							// Create new record in update_insert mode
							$newRecord = $modelClass::create($rowData);
							$createdRows++;
							$recordInfo["action"] = "insert";
							$recordInfo["id"] = $newRecord->id;
						}
					}
				} else {
					// No unique field specified, always insert
					$newRecord = $modelClass::create($rowData);
					$createdRows++;
					$recordInfo = [
						"action" => "insert",
						"id" => $newRecord->id,
						"row" => $totalRows,
						"operation_id" => uniqid(), // Identificativo univoco per questa operazione
					];
				}

				// Aggiungiamo l'operazione al buffer solo se non è già stata inviata
				$operationKey = $recordInfo["action"] . "_" . ($recordInfo["id"] ?? "") . "_row" . $recordInfo["row"];
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

			DB::commit();

			// Elimina il file CSV dopo l'importazione riuscita
			Storage::disk("backups")->delete($filePath);

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
			];

			echo json_encode($result);

			// Assicuriamoci che tutto il buffer venga inviato prima di terminare
			if (ob_get_level() > 0) {
				ob_end_flush();
			}

			exit(); // Necessario per evitare che Laravel aggiunga altre risposte
		} catch (\Exception $e) {
			DB::rollBack();

			// In caso di errore, invia una risposta di errore
			echo json_encode([
				"status" => "error",
				"message" => $e->getMessage(),
				"backupFile" => $backupFile,
			]);

			// Assicuriamoci che tutto il buffer venga inviato prima di terminare
			if (ob_get_level() > 0) {
				ob_end_flush();
			}

			exit(); // Necessario per evitare che Laravel aggiunga altre risposte
		} finally {
			fclose($handle);
		}
	}

	/**
	 * Crea un backup della tabella prima dell'importazione
	 *
	 * @param string $tableName
	 * @return string
	 */
	private function createTableBackup($tableName)
	{
		$backupDir = "csv-imports";
		if (!Storage::disk("backups")->exists($backupDir)) {
			Storage::disk("backups")->makeDirectory($backupDir);
		}

		$timestamp = now()->format("Y-m-d_His");
		$filename = $tableName . "_" . $timestamp . ".sql";
		$path = Storage::disk("backups")->path($backupDir . "/" . $filename);

		// Use PHP-based backup directly (no mysqldump dependency)
		$this->createPhpBackup($tableName, $path);

		// Clean old backups (keep only last 3)
		$backupFiles = Storage::disk("backups")->files($backupDir);
		$tableBackups = array_filter($backupFiles, function($file) use ($tableName) {
			return strpos($file, $tableName . '_') === 0;
		});
		
		if (count($tableBackups) > 3) {
			// Sort by modification time and keep only the 3 most recent
			$sortedBackups = collect($tableBackups)->sortBy(function($file) {
				return Storage::disk("backups")->lastModified($file);
			})->reverse();
			
			// Delete old backups
			$sortedBackups->slice(3)->each(function($file) {
				Storage::disk("backups")->delete($file);
			});
		}

		return $filename;
	}

	/**
	 * Create a PHP-based backup of a specific table
	 */
	private function createPhpBackup($tableName, $path)
	{
		try {
			// Get database connection
			$connection = config("database.default");
			$host = config("database.connections.{$connection}.host");
			$port = config("database.connections.{$connection}.port");
			$database = config("database.connections.{$connection}.database");
			$username = config("database.connections.{$connection}.username");
			$password = config("database.connections.{$connection}.password");

			$pdo = new \PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, [
				\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
			]);

			// Get table structure
			$stmt = $pdo->query("SHOW CREATE TABLE `{$tableName}`");
			$createRow = $stmt->fetch(\PDO::FETCH_NUM);

			// Start SQL backup content
			$sql = "-- Table Backup: {$tableName}\n";
			$sql .= "-- Generated: " . now()->toDateTimeString() . "\n";
			$sql .= "-- Created by ImportCsvController\n\n";
			$sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
			$sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n\n";

			// Add table structure
			$sql .= "-- Table structure for table `{$tableName}`\n";
			$sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
			$sql .= $createRow[1] . ";\n\n";

			// Get data count
			$countStmt = $pdo->query("SELECT COUNT(*) FROM `{$tableName}`");
			$rowCount = $countStmt->fetchColumn();

			if ($rowCount > 0) {
				$sql .= "-- Dumping data for table `{$tableName}`\n";
				$sql .= "LOCK TABLES `{$tableName}` WRITE;\n";

				// Get data in chunks to avoid memory issues
				$limit = 1000;
				$offset = 0;

				while ($offset < $rowCount) {
					$result = $pdo->query("SELECT * FROM `{$tableName}` LIMIT {$limit} OFFSET {$offset}");
					$rows = $result->fetchAll(\PDO::FETCH_NUM);

					if (!empty($rows)) {
						$sql .= "INSERT INTO `{$tableName}` VALUES ";
						$rowValues = [];

						foreach ($rows as $row) {
							$values = [];
							foreach ($row as $value) {
								if (is_null($value)) {
									$values[] = "NULL";
								} elseif (is_numeric($value)) {
									$values[] = $value;
								} else {
									$values[] = "'" . addslashes($value) . "'";
								}
							}
							$rowValues[] = "(" . implode(",", $values) . ")";
						}

						$sql .= implode(",\n", $rowValues) . ";\n";
					}

					$offset += $limit;
				}

				$sql .= "UNLOCK TABLES;\n";
			}

			$sql .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";
			$sql .= "-- Backup completed successfully\n";

			// Write backup file
			file_put_contents($path, $sql);

		} catch (\Exception $e) {
			// Fallback to the simple method if PDO fails
			$this->createSimpleBackup($tableName, $path);
		}
	}

	/**
	 * Simple backup fallback using Eloquent
	 */
	private function createSimpleBackup($tableName, $path)
	{
		// Get table data using Eloquent
		$rows = DB::table($tableName)->get();
		$columns = Schema::getColumnListing($tableName);

		// Prepare SQL for backup
		$sql = "-- Simple Backup: {$tableName}\n";
		$sql .= "-- Generated: " . now()->toDateTimeString() . "\n\n";
		$sql .= "DELETE FROM `{$tableName}`;\n\n";

		// Create INSERT statements
		if (count($rows) > 0) {
			$chunks = array_chunk($rows->toArray(), 100);

			foreach ($chunks as $chunk) {
				$sql .= "INSERT INTO `{$tableName}` (`" . implode("`, `", $columns) . "`) VALUES\n";

				$rowStatements = [];
				foreach ($chunk as $row) {
					$rowData = [];
					foreach ($columns as $column) {
						$value = $row->$column ?? null;
						if (is_null($value)) {
							$rowData[] = "NULL";
						} elseif (is_numeric($value)) {
							$rowData[] = $value;
						} else {
							$rowData[] = "'" . str_replace("'", "''", $value) . "'";
						}
					}
					$rowStatements[] = "(" . implode(", ", $rowData) . ")";
				}

				$sql .= implode(",\n", $rowStatements) . ";\n";
			}
		}

		// Write SQL file
		file_put_contents($path, $sql);
	}

	/**
	 * Conta il numero di righe in un file CSV
	 *
	 * @param string $filePath
	 * @param string $delimiter
	 * @return int
	 */
	private function countCsvRows($filePath, $delimiter = ",")
	{
		$rowCount = 0;
		$handle = fopen($filePath, "r");

		while (fgetcsv($handle, 0, $delimiter) !== false) {
			$rowCount++;
		}

		fclose($handle);
		return $rowCount;
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
}
