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
		$request->validate([
			"csv_file" => "required|file|mimes:csv,txt|max:10240",
		]);

		$modelClass = "App\\Models\\" . Str::studly($crud);

		if (!class_exists($modelClass)) {
			return back()->with("error", "Modello non trovato");
		}

		$file = $request->file("csv_file");
		$fileName = time() . "_" . $file->getClientOriginalName();
		$filePath = $file->storeAs("csv-imports", $fileName);

		// Legge l'intestazione del CSV
		$csvPath = storage_path("app/" . $filePath);

		// Prova a rilevare il delimitatore (virgola, punto e virgola, tab)
		$delimiter = $this->detectDelimiter($csvPath);

		$handle = fopen($csvPath, "r");
		$csvHeaders = fgetcsv($handle, 0, $delimiter);

		// Legge le prime 5 righe per l'anteprima
		$csvPreview = [];
		$previewLimit = 5;
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
		$request->validate([
			"file_path" => "required|string",
			"column_mapping" => "required|array",
			"unique_field" => "nullable|string",
			"delimiter" => "required|string",
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

		// Crea un backup della tabella
		$backupFile = $this->createTableBackup($tableName);

		// Legge il file CSV
		$csvPath = storage_path("app/" . $filePath);
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

			while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
				$totalRows++;
				$rowData = [];

				// Mappa i dati in base alla configurazione
				foreach ($columnMapping as $csvIndex => $dbColumn) {
					if (!empty($dbColumn) && isset($data[$csvIndex])) {
						$rowData[$dbColumn] = $data[$csvIndex];
					}
				}

				if (empty($rowData)) {
					$skippedRows++;
					continue;
				}

				// Verifica se esiste un record con lo stesso campo unique
				if ($uniqueField && !empty($rowData[$uniqueField])) {
					$existingRecord = $modelClass::where($uniqueField, $rowData[$uniqueField])->first();

					if ($existingRecord) {
						$existingRecord->update($rowData);
						$updatedRows++;
					} else {
						$modelClass::create($rowData);
						$createdRows++;
					}
				} else {
					$modelClass::create($rowData);
					$createdRows++;
				}

				// Invia un aggiornamento di stato ogni 50 righe
				if ($totalRows % 50 == 0) {
					$progress = round(($totalRows / $totalRowsCSV) * 100);

					echo json_encode([
						"status" => "processing",
						"progress" => $progress,
						"total" => $totalRows,
						"created" => $createdRows,
						"updated" => $updatedRows,
						"skipped" => $skippedRows,
					]);

					ob_flush();
					flush();
				}
			}

			DB::commit();

			// Elimina il file CSV dopo l'importazione riuscita
			Storage::delete($filePath);

			// Elimina anche eventuali file temporanei nella stessa directory
			$csvDir = dirname($filePath);
			$csvFilename = basename($filePath);
			$tempFiles = Storage::files($csvDir);
			foreach ($tempFiles as $tempFile) {
				// Verifica se il file è più vecchio di un'ora per sicurezza
				if ($tempFile !== $filePath && Storage::lastModified($tempFile) < now()->subHour()->timestamp) {
					Storage::delete($tempFile);
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

			return response()->json($result);
		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json(
				[
					"status" => "error",
					"message" => $e->getMessage(),
					"backupFile" => $backupFile,
				],
				500
			);
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
		$backupDir = "import-backups";
		if (!Storage::exists($backupDir)) {
			Storage::makeDirectory($backupDir);
		}

		$timestamp = now()->format("Y-m-d_His");
		$filename = $tableName . "_" . $timestamp . ".sql";
		$path = storage_path("app/" . $backupDir . "/" . $filename);

		// Ottiene i dati di connessione al database
		$dbName = config("database.connections.mysql.database");
		$dbUser = config("database.connections.mysql.username");
		$dbPass = config("database.connections.mysql.password");
		$dbHost = config("database.connections.mysql.host");
		$dbPort = config("database.connections.mysql.port");

		// Utilizza Process per eseguire mysqldump con maggiore controllo
		$command = sprintf(
			"mysqldump -h%s -P%s -u%s -p%s %s %s --no-tablespaces --skip-comments",
			$dbHost,
			$dbPort ?: "3306",
			$dbUser,
			$dbPass,
			$dbName,
			$tableName
		);

		// Esegue mysqldump e salva direttamente nel file
		$process = new \Symfony\Component\Process\Process(explode(" ", $command));
		$process->setWorkingDirectory(base_path());
		$process->setTimeout(300); // 5 minuti di timeout
		$process->run();

		// Verifica se il comando è stato eseguito con successo
		if ($process->isSuccessful()) {
			$output = $process->getOutput();
			// Scrive l'output nel file
			if (!empty($output)) {
				file_put_contents($path, $output);
				return $filename;
			}
		}

		// Se il comando mysqldump fallisce, creiamo un backup alternativo utilizzando query SQL
		$this->createSqlBackupAlternative($tableName, $path);

		return $filename;
	}

	/**
	 * Crea un backup alternativo usando query SQL se mysqldump fallisce
	 *
	 * @param string $tableName
	 * @param string $path
	 * @return void
	 */
	private function createSqlBackupAlternative($tableName, $path)
	{
		// Prende i dati della tabella
		$rows = DB::table($tableName)->get();

		// Ottiene la struttura della tabella
		$columns = Schema::getColumnListing($tableName);

		// Prepara l'SQL per il backup
		$sql = "-- Backup della tabella {$tableName} creato il " . now() . "\n";
		$sql .= "-- Backup alternativo creato automaticamente\n\n";

		// Aggiunge DELETE per svuotare la tabella
		$sql .= "DELETE FROM `{$tableName}`;\n\n";

		// Crea le istruzioni INSERT
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

		// Scrive il file SQL
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
		// In questo metodo verrà utilizzata sessione o cache per memorizzare lo stato dell'importazione
		// Per ora, restituiamo un oggetto vuoto che verrà aggiornato durante l'importazione
		return response()->json([
			"status" => "processing",
			"progress" => 0,
			"total" => 0,
			"created" => 0,
			"updated" => 0,
			"skipped" => 0,
		]);
	}
}
