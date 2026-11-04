<?php

namespace App\Http\Controllers\Admin\Action;

use App\Support\Backpack\SchemaCache;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Observers\CacheInvalidationContext;
use App\Services\Cache\CacheFlush;
use App\Services\Logging\LogEncryptionService;
use App\Support\Backpack\CsvBackupManager;
use App\Support\Backpack\CsvHelper;
use App\Support\Backpack\ImportPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ImportCsvAction extends Controller
{
	/**
	 * Backup existing hashed passwords BEFORE seeding starts
	 * CRITICAL: Called at the START of DatabaseSeeder, BEFORE any truncate
	 * This preserves passwords across migrate:fresh operations
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
						->where("password", "like", '$2y$%')
						->orWhere("password", "like", '$2a$%')
						->orWhere("password", "like", '$2b$%')
						->orWhere("password", "like", '$2x$%');
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
	 * Hash user passwords after import
	 * Uses bulk operations for maximum speed (200+ users/sec)
	 *
	 * @param  bool  $silent  Silent mode flag
	 */
	public static function hashUserPasswordsAfterImport(bool $silent = false): void
	{
		if (!$silent) {
			echo "Starting password hashing for imported users...\n";
		}

<<<<<<< HEAD
		// Get all users with plain text passwords using raw query for speed
		$users = DB::table("users")->where("password", "not like", '$2y$%')->select("user_id", "password")->get();
=======
        // Users whose password is still plain text. password_get_info() recognises
        // every hash format (bcrypt, argon2id, ...), so a row already hashed by the
        // application is never hashed a second time.
        $users = DB::table('users')
            ->whereNotNull('password')
            ->where('password', '!=', '')
            ->select('username', 'password')
            ->get()
            ->filter(fn ($user) => password_get_info($user->password)['algo'] === null)
            ->values();
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

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

<<<<<<< HEAD
		// Process in large batches with bulk SQL operations
		$users->chunk($batchSize)->each(function ($userBatch) use (&$processed, $total, $startTime, $silent) {
			$updates = [];
			$user_ids = [];

			// Pre-hash all passwords in the batch
			foreach ($userBatch as $user) {
				$hashedPassword = password_hash($user->password, PASSWORD_BCRYPT);
				$updates[] = $hashedPassword;
				$user_ids[] = $user->user_id;
			}

			// Build optimized bulk UPDATE query
			$caseStatements = [];
			foreach ($updates as $index => $hashedPassword) {
				$user_id = addslashes($user_ids[$index]);
				$caseStatements[] = "WHEN '{$user_id}' THEN '" . addslashes($hashedPassword) . "'";
			}

			// Execute single bulk UPDATE
			$sql =
				"UPDATE users SET password = CASE user_id " .
				implode(" ", $caseStatements) .
				" END WHERE user_id IN ('" .
				implode("','", array_map("addslashes", $user_ids)) .
				"')";

			DB::statement($sql);
			$processed += count($updates);
=======
        // Process in large batches with bulk SQL operations
        $users->chunk($batchSize)->each(function ($userBatch) use (&$processed, $total, $startTime, $silent) {
            $updates = [];
            $usernames = [];

            // Pre-hash all passwords in the batch through the application hasher,
            // so the configured driver (and its pepper derivation) is applied.
            foreach ($userBatch as $user) {
                $updates[] = Hash::make($user->password);
                $usernames[] = $user->username;
            }

            // Build one bulk UPDATE with bound parameters (CASE username WHEN ? THEN ?).
            $caseStatements = [];
            $bindings = [];
            foreach ($updates as $index => $hashedPassword) {
                $caseStatements[] = 'WHEN ? THEN ?';
                $bindings[] = $usernames[$index];
                $bindings[] = $hashedPassword;
            }

            $sql =
                'UPDATE users SET password = CASE username '.
                implode(' ', $caseStatements).
                ' END WHERE username IN ('.
                implode(',', array_fill(0, count($usernames), '?')).
                ')';

            DB::update($sql, [...$bindings, ...$usernames]);
            $processed += count($updates);
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

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

		// Delete password cache file after import is complete
		self::deletePasswordCache($silent);
	}

	/**
	 * Delete password cache file after import is complete
	 * Written by backupPasswordsBeforeSeeding() before a reseed
	 *
	 * @param  bool  $silent  Silent mode flag
	 */
	private static function deletePasswordCache(bool $silent = false): void
	{
		$cacheFile = storage_path("app/cache/user_passwords_backup.json");

		try {
			if (file_exists($cacheFile)) {
				unlink($cacheFile);

				if (!$silent) {
					echo "Deleted password cache file for security\n";
					echo "File will be recreated on the next reseed\n\n";
				}
			}
		} catch (\Exception $e) {
			if (!$silent) {
				echo "Warning: Could not delete password cache: " . $e->getMessage() . "\n\n";
			}
		}
	}

	/**
	 * Add default values for missing required fields based on model type
	 * Used by backend interface import
	 *
	 * @param  array  $rowData  Current row data
	 * @param  string  $modelClass  Model class name
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
	 * @param  string  $crud
	 * @return View
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
	 * @param  string  $modelClass
	 * @return array
	 */
	private function getTableColumns($modelClass)
	{
		$model = new $modelClass();

		return SchemaCache::getColumnListing($model->getTable());
	}

	public function getTableColumnsPublic($modelClass)
	{
		return $this->getTableColumns($modelClass);
	}

<<<<<<< HEAD
	/**
	 * Fields that are auto-generated in model boot() and should not block import
	 * even if they are NOT NULL in the database schema.
	 * These appear in the "Developer Section" with confirmation required.
	 */
	private const AUTO_GENERATED_FIELDS = [
		"App\Models\User" => [
			"email" => "auto_generated_hint_email",
			"password" => "auto_generated_hint_password",
			"role_id" => "auto_generated_hint_role_id",
			"backpack_role_id" => "auto_generated_hint_backpack_role_id",
		],
		"App\Models\Tiraggio" => [
			"tiraggio_id" => "auto_generated_hint_pk",
		],
		"App\Models\Delibera" => [
			"delibera_id" => "auto_generated_hint_pk",
		],
		"App\Models\Trattenuta" => [
			"trattenuta_id" => "auto_generated_hint_pk",
		],
		"App\Models\Proiezione" => [
			"proiezione_id" => "auto_generated_hint_pk",
		],
		"App\Models\Storico" => [
			"storico_id" => "auto_generated_hint_pk",
		],
		"App\Models\Previsionale" => [
			"previsionale_id" => "auto_generated_hint_pk",
		],
		"App\Models\Banca" => [
			"banca_abi" => "auto_generated_hint_pk_manual",
		],
		"App\Models\Filiale" => [
			"filiale_id" => "auto_generated_hint_pk_manual",
		],
		"App\Models\Interfaccia" => [
			"interfaccia_id" => "auto_generated_hint_pk",
		],
		"App\Models\Prodotto" => [
			"prodotto_id" => "auto_generated_hint_pk",
		],
		"App\Models\Esclusione" => [
			"esclusione_id" => "auto_generated_hint_pk",
		],
	];
=======
    /**
     * Fields that are auto-generated in model boot() and should not block import
     * even if they are NOT NULL in the database schema.
     * These appear in the "Developer Section" with confirmation required.
     */
    private const AUTO_GENERATED_FIELDS = [
        // Add one entry per model, e.g. 'App\Models\Article' => ['slug' => 'auto_generated_hint_pk'].
        'App\Models\User' => [
            'password' => 'auto_generated_hint_password',
            'role_id' => 'auto_generated_hint_role_id',
            'backpack_role_id' => 'auto_generated_hint_backpack_role_id',
        ],
    ];
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

	/**
	 * Fields that should be completely hidden from import UI.
	 * These are managed automatically by Eloquent or model boot().
	 */
	private const HIDDEN_IMPORT_FIELDS = [
		"created_at",
		"updated_at",
		"token",
		"token_expire",
		"token_verified",
		"email_verified_at",
		"remember_token",
	];

	/**
	 * Ottiene le colonne non nullable della tabella dal modello
	 *
	 * @param  string  $modelClass
	 * @return array
	 */
	public function getRequiredColumnsPublic($modelClass)
	{
		return $this->getRequiredColumns($modelClass);
	}

	private function getRequiredColumns($modelClass)
	{
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

		// Exclude auto-generated fields that are handled in model boot()
		$autoGenerated = array_keys(self::AUTO_GENERATED_FIELDS[$modelClass] ?? []);
		$requiredColumns = array_diff($requiredColumns, $autoGenerated);

		// Exclude hidden fields
		$requiredColumns = array_diff($requiredColumns, self::HIDDEN_IMPORT_FIELDS);

		return array_values($requiredColumns);
	}

	/**
	 * Get auto-generated fields with their translation hint keys
	 *
	 * @param  string  $modelClass
	 * @return array ['field_name' => 'translation_key', ...]
	 */
	public function getAutoGeneratedFieldsPublic($modelClass)
	{
		return $this->getAutoGeneratedFields($modelClass);
	}

	private function getAutoGeneratedFields($modelClass)
	{
		return self::AUTO_GENERATED_FIELDS[$modelClass] ?? [];
	}

	/**
	 * Get fields that should be hidden from import UI
	 *
	 * @return array
	 */
	private function getHiddenImportFields()
	{
		return self::HIDDEN_IMPORT_FIELDS;
	}

	/**
	 * Get database constraints for a model (primary key, foreign keys, unique constraints)
	 *
	 * @param  string  $modelClass
	 * @return array ['primaryKey' => string, 'foreignKeys' => [...], 'uniqueConstraints' => [...]]
	 */
	public function getDatabaseConstraintsPublic($modelClass)
	{
		return $this->getDatabaseConstraints($modelClass);
	}

	private function getDatabaseConstraints($modelClass)
	{
		$model = new $modelClass();
		$table = $model->getTable();
		$database = config("database.connections.mysql.database");

		$constraints = [
			"primaryKey" => null,
			"foreignKeys" => [],
			"uniqueConstraints" => [],
		];

		// Get primary key
		$primaryKey = $model->getKeyName();
		if ($primaryKey) {
			$constraints["primaryKey"] = $primaryKey;
		}

		// Get foreign keys from INFORMATION_SCHEMA
		$foreignKeys = DB::select(
			'
            SELECT
                COLUMN_NAME as column_name,
                REFERENCED_TABLE_NAME as referenced_table
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ',
			[$database, $table]
		);

		foreach ($foreignKeys as $fk) {
			$constraints["foreignKeys"][$fk->column_name] = $fk->referenced_table;
		}

		// Get unique constraints (composite and single)
		$uniqueIndexes = DB::select(
			"
            SELECT
                INDEX_NAME,
                GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as columns
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND NON_UNIQUE = 0
            AND INDEX_NAME != 'PRIMARY'
            GROUP BY INDEX_NAME
            HAVING COUNT(*) >= 1
<<<<<<< HEAD
        ",
			[$database, $table]
		);

		foreach ($uniqueIndexes as $idx) {
			$columns = explode(",", $idx->columns);
			if (count($columns) > 1) {
				// Composite unique constraint
				foreach ($columns as $col) {
					$otherCols = array_filter($columns, fn($c) => $c !== $col);
					$constraints["uniqueConstraints"][$col] = array_values($otherCols);
				}
			} else {
				// Single column unique (store as empty array to indicate it's unique but not composite)
				$constraints["uniqueConstraints"][$columns[0]] = [];
			}
		}

		return $constraints;
	}

	/**
	 * Analizza il file CSV caricato e mostra l'interfaccia di mappatura
	 *
	 * @param  string  $crud
	 * @return View
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
		$csvHeaders = fgetcsv($handle, 0, $delimiter, escape: "\\");
		fclose($handle);

		// Se c'è una sola intestazione e contiene più campi concatenati, prova a suddividerla
		if (count($csvHeaders) === 1 && strpos($csvHeaders[0], ":") !== false) {
			$csvHeaders = explode(":", $csvHeaders[0]);
		}

		// Ottiene le colonne della tabella
		$allTableColumns = $this->getTableColumns($modelClass);

		// Filter out hidden fields from table columns
		$hiddenFields = $this->getHiddenImportFields();
		$autoGeneratedFields = $this->getAutoGeneratedFields($modelClass);
		$autoGeneratedColumnNames = array_keys($autoGeneratedFields);

		// Regular columns (excluding hidden and auto-generated)
		$tableColumns = array_values(array_diff($allTableColumns, $hiddenFields, $autoGeneratedColumnNames));

		// Ottiene le colonne obbligatorie (non nullable)
		$requiredColumns = $this->getRequiredColumns($modelClass);

		// Get database constraints (PK, FK, unique)
		$databaseConstraints = $this->getDatabaseConstraints($modelClass);

		// Prova a mappare automaticamente le colonne in base al nome
		$columnMapping = [];
		foreach ($csvHeaders as $index => $header) {
			$normalizedHeader = Str::snake(strtolower(trim($header)));
			if (in_array($normalizedHeader, $allTableColumns)) {
				$columnMapping[$index] = $normalizedHeader;
			} else {
				$columnMapping[$index] = "";
			}
		}

		// All columns available for matching (includes auto-generated, excludes only hidden)
		$matchableColumns = array_values(array_diff($allTableColumns, $hiddenFields));

		return view("vendor.backpack.crud.import_mapping", [
			"crud" => $crud,
			"csvHeaders" => $csvHeaders,
			"tableColumns" => $tableColumns,
			"matchableColumns" => $matchableColumns,
			"requiredColumns" => $requiredColumns,
			"autoGeneratedFields" => $autoGeneratedFields,
			"databaseConstraints" => $databaseConstraints,
			"columnMapping" => $columnMapping,
			"filePath" => $filePath,
			"modelClass" => $modelClass,
			"isUserModel" => $modelClass === "App\\Models\\User",
			"crud_route" => config("backpack.base.route_prefix", "admin") . "/" . $crud,
			"delimiter" => $delimiter,
			"originalFileName" => $originalFileName,
			"originalFileLastModified" => $originalFileLastModified,
		]);
	}

	/**
	 * Esegue l'importazione del CSV nel database
	 *
	 * @param  string  $crud
	 * @return Response
	 */
	/**
	 * Preview import: analyze CSV without writing to DB.
	 * Returns JSON with summary (insert/update/skip counts) and warnings.
	 *
	 * @param  string  $crud
	 */
	public function previewImport(Request $request, $crud): JsonResponse
	{
		$request->validate([
			"file_path" => "required|string",
			"column_mapping" => "required|array",
			"matching_fields" => "nullable|array",
			"matching_fields.*" => "string",
			"delimiter" => "required|string",
			"import_behavior" => "nullable|string|in:update_insert,update_only",
		]);

		$modelClass = "App\\Models\\" . Str::studly($crud);

		if (!class_exists($modelClass)) {
			return response()->json(["error" => "Modello non trovato"], 404);
		}

		$filePath = $request->file_path;
		$csvPath = Storage::disk("backups")->path($filePath);

		if (!file_exists($csvPath)) {
			return response()->json(["error" => "File non trovato"], 404);
		}

		$columnMapping = $request->column_mapping;
		$matchingFields = array_filter($request->matching_fields ?? []);
		$delimiter = $request->delimiter;
		$importBehavior = $request->import_behavior ?? "update_insert";

		$previewService = app(ImportPreviewService::class);
		$result = $previewService->analyze(
			$csvPath,
			$modelClass,
			$columnMapping,
			$matchingFields,
			$delimiter,
			$importBehavior
		);

		return response()->json($result);
	}

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
			"matching_fields" => "nullable|array",
			"matching_fields.*" => "string",
			"delimiter" => "required|string",
			"import_behavior" => "nullable|string|in:update_insert,update_only",
			"generate_passwords" => "nullable|boolean",
		]);

		$modelClass = "App\\Models\\" . Str::studly($crud);

		if (!class_exists($modelClass)) {
			return response()->json(["error" => "Modello non trovato"], 404);
		}

		$model = new $modelClass();
		$tableName = $model->getTable();
		$filePath = $request->file_path;
		$columnMapping = $request->column_mapping;
		$matchingFields = array_filter($request->matching_fields ?? []); // Filter empty values
		$delimiter = $request->delimiter;
		$importBehavior = $request->import_behavior ?? "update_insert";
		$generatePasswords = $request->boolean("generate_passwords");

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
		fgetcsv($handle, 0, $delimiter, escape: "\\");

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

		if ($modelClass === "App\\Models\\User") {
			if ($generatePasswords) {
				User::$generateMissingPasswords = true;
			} else {
				User::$skipPasswordGeneration = true;
			}
		}

		CacheInvalidationContext::suppress();

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

			while (($data = fgetcsv($handle, 0, $delimiter, escape: "\\")) !== false) {
				$totalRows++;
				$rowData = [];

				// Map CSV columns to DB columns (one CSV column can map to multiple DB columns)
				foreach ($columnMapping as $csvIndex => $dbColumnOrColumns) {
					$dbColumns = is_array($dbColumnOrColumns) ? $dbColumnOrColumns : [$dbColumnOrColumns];
					if (!isset($data[$csvIndex])) {
						continue;
					}
					$value = $data[$csvIndex];
					foreach ($dbColumns as $dbColumn) {
						if (empty($dbColumn)) {
							continue;
						}
						$cellValue = $value;
						// Convert empty strings to null for nullable fields (especially foreign keys)
						if ($cellValue === "" && in_array($dbColumn, ["page_id"])) {
							$cellValue = null;
						}
						$rowData[$dbColumn] = $cellValue;
					}
				}

				// Add default values for missing required fields (same as in seeder import)
				$rowData = $this->addDefaultRequiredFields($rowData, $modelClass);

				if (empty($rowData)) {
					$skippedRows++;

					// Add to physical log
					$skippedRecordsLog[] = [
						"row" => $totalRows,
						"matching_fields" => $matchingFields,
						"matching_values" => [],
						"reason" => "empty_row_data",
						"data" => [],
						"timestamp" => now()->toDateTimeString(),
					];

					continue;
				}

				// Build matching criteria from multiple fields
				$matchingCriteria = [];
				$matchingValues = [];
				$hasAllMatchingFields = true;

				foreach ($matchingFields as $field) {
					if (!empty($rowData[$field])) {
						$matchingCriteria[$field] = $rowData[$field];
						$matchingValues[$field] = $rowData[$field];
					} else {
						$hasAllMatchingFields = false;
					}
				}

				// Check if record exists using all matching fields (composite key)
				if (!empty($matchingFields) && $hasAllMatchingFields && !empty($matchingCriteria)) {
					$query = $modelClass::query();
					foreach ($matchingCriteria as $field => $value) {
						$query->where($field, $value);
					}
					$existingRecord = $query->first();

					// Prepare detailed operation info
					$recordInfo = [
						"matching_fields" => $matchingFields,
						"matching_values" => $matchingValues,
						"row" => $totalRows,
						"operation_id" => uniqid(),
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
							"matching_fields" => $matchingFields,
							"matching_values" => $matchingValues,
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
								"Password auto-generated from User ID + 'd3s10666' and hashed with bcrypt";
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
								"matching_fields" => $matchingFields,
								"matching_values" => $matchingValues,
								"reason" => "update_only_mode",
								"data" => $rowData,
								"timestamp" => now()->toDateTimeString(),
							];
						} else {
							// Create new record in update_insert mode
							$newRecord = $modelClass::create($rowData);
							if ($newRecord->getKey() === null && isset($newRecord->importSkippedReason)) {
								$skippedRows++;
								$recordInfo["action"] = "skip";
								$recordInfo["reason"] = $newRecord->importSkippedReason;
								$recordInfo["row"] = $totalRows;
								$skippedRecordsLog[] = [
									"row" => $totalRows,
									"matching_fields" => $matchingFields,
									"matching_values" => $matchingValues ?? [],
									"reason" => $newRecord->importSkippedReason,
									"data" => $rowData,
									"timestamp" => now()->toDateTimeString(),
								];
							} else {
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
									"matching_fields" => $matchingFields,
									"matching_values" => $matchingValues ?? null,
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
										"Password auto-generated from User ID + 'd3s10666' and hashed with bcrypt";
								}

								$newRecordsLog[] = $logEntry;
							}
						}
					}
				} else {
					// Matching fields are empty or not all present
					// Check import behavior: if update_only and matching fields incomplete, skip the record
					if (!empty($matchingFields) && $importBehavior === "update_only") {
						// Skip insertion for update_only mode when matching fields are incomplete
						$skippedRows++;
						$recordInfo = [
							"action" => "skip",
							"reason" => "update_only_mode_incomplete_matching_fields",
							"row" => $totalRows,
							"operation_id" => uniqid(),
						];

						// Add to physical log
						$skippedRecordsLog[] = [
							"row" => $totalRows,
							"matching_fields" => $matchingFields,
							"matching_values" => $matchingValues,
							"reason" => "update_only_mode_incomplete_matching_fields",
							"data" => $rowData,
							"timestamp" => now()->toDateTimeString(),
						];
					} else {
						// No unique field specified OR update_insert mode: always insert
						$newRecord = $modelClass::create($rowData);
						if ($newRecord->getKey() === null && isset($newRecord->importSkippedReason)) {
							$skippedRows++;
							$recordInfo = [
								"action" => "skip",
								"reason" => $newRecord->importSkippedReason,
								"row" => $totalRows,
								"operation_id" => uniqid(),
							];
							$skippedRecordsLog[] = [
								"row" => $totalRows,
								"matching_fields" => $matchingFields,
								"matching_values" => $matchingValues,
								"reason" => $newRecord->importSkippedReason,
								"data" => $rowData,
								"timestamp" => now()->toDateTimeString(),
							];
						} else {
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
								"matching_fields" => $matchingFields,
								"matching_values" => $matchingValues,
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
									"Password auto-generated from User ID + 'd3s10666' and hashed with bcrypt";
							}

							$newRecordsLog[] = $logEntry;
						}
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

			// After the commit, so the jobs see the new rows.
			if ($modelClass === "App\\Models\\User" && !$generatePasswords) {
				$this->dispatchMissingPasswords();
			}

			if (!empty($bancaAbi)) {
				CacheFlush::forBanca($bancaAbi, $filialeId ?? null);
			}

			// Save physical log files
			$this->saveImportLogs($logPath, $tableName, $newRecordsLog, $updatedRecordsLog, $skippedRecordsLog, [
				"total_rows" => $totalRows,
				"created" => $createdRows,
				"updated" => $updatedRows,
				"skipped" => $skippedRows,
				"matching_fields" => $matchingFields,
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
						"matching_fields" => $matchingFields ?? [],
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
			if ($modelClass === "App\\Models\\User") {
				User::$skipPasswordGeneration = false;
				User::$generateMissingPasswords = false;
			}
			fclose($handle);
			// Ensure uploaded CSV is removed even on error
			$this->deleteUploadedFile($filePath);
			CacheInvalidationContext::restore(); // Always restore, even on exception
		}
	}

	/**
	 * Ottiene lo stato attuale dell'importazione
	 *
	 * @return JsonResponse
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
	 * @param  string  $logPath  Base path for log files
	 * @param  string  $tableName  Table name
	 * @param  array  $newRecordsLog  Array of new records
	 * @param  array  $updatedRecordsLog  Array of updated records
	 * @param  array  $skippedRecordsLog  Array of skipped records
	 * @param  array  $summary  Summary information
	 */
	private function saveImportLogs(
		string $logPath,
		string $tableName,
		array $newRecordsLog,
		array $updatedRecordsLog,
		array $skippedRecordsLog,
		array $summary
	): void {
		// Check if encryption is enabled via LOG_CHANNEL=encrypted_daily
		$shouldEncrypt = config("logging.default") === "encrypted_daily";
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
	 * Queue password generation on Horizon; failures are left to the daily run.
	 */
	private function dispatchMissingPasswords(): void
	{
		try {
			Artisan::call("auth:rehash-passwords", ["--only-empty" => true, "--no-wait" => true]);
		} catch (\Throwable $e) {
			Log::channel("import")->error("Could not dispatch password generation after import", [
				"error" => $e->getMessage(),
			]);
		}
	}

	/**
	 * Delete the uploaded CSV file safely
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
	 * @param  int  $maxLogs  Maximum number of recent logs to keep
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
=======
        ", [$database, $table]);

        foreach ($uniqueIndexes as $idx) {
            $columns = explode(',', $idx->columns);
            if (count($columns) > 1) {
                // Composite unique constraint
                foreach ($columns as $col) {
                    $otherCols = array_filter($columns, fn ($c) => $c !== $col);
                    $constraints['uniqueConstraints'][$col] = array_values($otherCols);
                }
            } else {
                // Single column unique (store as empty array to indicate it's unique but not composite)
                $constraints['uniqueConstraints'][$columns[0]] = [];
            }
        }

        return $constraints;
    }

    /**
     * Analizza il file CSV caricato e mostra la schermata di mappatura
     *
     * @param  string  $crud
     * @return View
     */
    public function analyzeUploadedFile(Request $request, $crud)
    {
        // Increase execution time and memory for large imports
        set_time_limit(0); // No timeout
        ini_set('memory_limit', '512M'); // Increase memory limit

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt', // Removed max:200000 limit
        ]);

        $modelClass = 'App\\Models\\'.Str::studly($crud);

        if (! class_exists($modelClass)) {
            return back()->with('error', 'Modello non trovato');
        }

        $file = $request->file('csv_file');

        // Clean all files in csv-uploads directory before saving new file
        $uploadDir = 'csv-uploads';
        $uploadFiles = Storage::disk('backups')->files($uploadDir);
        foreach ($uploadFiles as $uploadFile) {
            Storage::disk('backups')->delete($uploadFile);
        }

        // Capture original file metadata BEFORE saving
        $originalFileName = $file->getClientOriginalName();
        $originalFileLastModified = $request->input('client_last_modified');
        if (! $originalFileLastModified && $file->isValid() && $file->getRealPath()) {
            // Fallback: use temp file mtime (may be upload time, not original)
            $originalFileLastModified = date('c', filemtime($file->getRealPath()));
        }

        // Convert ISO 8601 to readable timestamp format (Y-m-d H:i:s)
        if ($originalFileLastModified) {
            try {
                $dateTime = new \DateTime($originalFileLastModified);
                $originalFileLastModified = $dateTime->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                // If parsing fails, keep original format
                Log::warning('Failed to parse file_last_modified: '.$originalFileLastModified);
            }
        }

        $fileName = time().'_'.$originalFileName;
        $filePath = $file->storeAs('csv-uploads', $fileName, 'backups');

        // Legge l'intestazione del CSV
        $csvPath = Storage::disk('backups')->path($filePath);

        // Prova a rilevare il delimitatore (virgola, punto e virgola, tab)
        $delimiter = CsvHelper::detectDelimiter($csvPath);

        $handle = fopen($csvPath, 'r');
        $csvHeaders = fgetcsv($handle, 0, $delimiter, escape: '\\');
        fclose($handle);

        // Se c'è una sola intestazione e contiene più campi concatenati, prova a suddividerla
        if (count($csvHeaders) === 1 && strpos($csvHeaders[0], ':') !== false) {
            $csvHeaders = explode(':', $csvHeaders[0]);
        }

        // Ottiene le colonne della tabella
        $allTableColumns = $this->getTableColumns($modelClass);

        // Filter out hidden fields from table columns
        $hiddenFields = $this->getHiddenImportFields();
        $autoGeneratedFields = $this->getAutoGeneratedFields($modelClass);
        $autoGeneratedColumnNames = array_keys($autoGeneratedFields);

        // Regular columns (excluding hidden and auto-generated)
        $tableColumns = array_values(array_diff($allTableColumns, $hiddenFields, $autoGeneratedColumnNames));

        // Ottiene le colonne obbligatorie (non nullable)
        $requiredColumns = $this->getRequiredColumns($modelClass);

        // Get database constraints (PK, FK, unique)
        $databaseConstraints = $this->getDatabaseConstraints($modelClass);

        // Prova a mappare automaticamente le colonne in base al nome
        $columnMapping = [];
        foreach ($csvHeaders as $index => $header) {
            $normalizedHeader = Str::snake(strtolower(trim($header)));
            if (in_array($normalizedHeader, $allTableColumns)) {
                $columnMapping[$index] = $normalizedHeader;
            } else {
                $columnMapping[$index] = '';
            }
        }

        // All columns available for matching (includes auto-generated, excludes only hidden)
        $matchableColumns = array_values(array_diff($allTableColumns, $hiddenFields));

        return view('vendor.backpack.crud.import_mapping', [
            'crud' => $crud,
            'csvHeaders' => $csvHeaders,
            'tableColumns' => $tableColumns,
            'matchableColumns' => $matchableColumns,
            'requiredColumns' => $requiredColumns,
            'autoGeneratedFields' => $autoGeneratedFields,
            'databaseConstraints' => $databaseConstraints,
            'columnMapping' => $columnMapping,
            'filePath' => $filePath,
            'modelClass' => $modelClass,
            'isUserModel' => $modelClass === 'App\\Models\\User',
            'crud_route' => config('backpack.base.route_prefix', 'admin').'/'.$crud,
            'delimiter' => $delimiter,
            'originalFileName' => $originalFileName,
            'originalFileLastModified' => $originalFileLastModified,
        ]);
    }

    /**
     * Esegue l'importazione del CSV nel database
     *
     * @param  string  $crud
     * @return Response
     */
    /**
     * Preview import: analyze CSV without writing to DB.
     * Returns JSON with summary (insert/update/skip counts) and warnings.
     *
     * @param  string  $crud
     */
    public function previewImport(Request $request, $crud): JsonResponse
    {
        $request->validate([
            'file_path' => 'required|string',
            'column_mapping' => 'required|array',
            'matching_fields' => 'nullable|array',
            'matching_fields.*' => 'string',
            'delimiter' => 'required|string',
            'import_behavior' => 'nullable|string|in:update_insert,update_only',
        ]);

        $modelClass = 'App\\Models\\'.Str::studly($crud);

        if (! class_exists($modelClass)) {
            return response()->json(['error' => 'Modello non trovato'], 404);
        }

        $filePath = $request->file_path;
        $csvPath = Storage::disk('backups')->path($filePath);

        if (! file_exists($csvPath)) {
            return response()->json(['error' => 'File non trovato'], 404);
        }

        $columnMapping = $request->column_mapping;
        $matchingFields = array_filter($request->matching_fields ?? []);
        $delimiter = $request->delimiter;
        $importBehavior = $request->import_behavior ?? 'update_insert';

        $previewService = app(ImportPreviewService::class);
        $result = $previewService->analyze(
            $csvPath,
            $modelClass,
            $columnMapping,
            $matchingFields,
            $delimiter,
            $importBehavior
        );

        return response()->json($result);
    }

    public function importCsv(Request $request, $crud)
    {
        // Increase execution time and memory for large imports
        set_time_limit(0); // No timeout
        ini_set('memory_limit', '1G'); // Increase memory limit for better performance

        // Inizializzazione del buffer di output
        if (ob_get_level() == 0) {
            ob_start();
        }

        $request->validate([
            'file_path' => 'required|string',
            'column_mapping' => 'required|array',
            'matching_fields' => 'nullable|array',
            'matching_fields.*' => 'string',
            'delimiter' => 'required|string',
            'import_behavior' => 'nullable|string|in:update_insert,update_only',
            'generate_passwords' => 'nullable|boolean',
        ]);

        $modelClass = 'App\\Models\\'.Str::studly($crud);

        if (! class_exists($modelClass)) {
            return response()->json(['error' => 'Modello non trovato'], 404);
        }

        $model = new $modelClass;
        $tableName = $model->getTable();
        $filePath = $request->file_path;
        $columnMapping = $request->column_mapping;
        $matchingFields = array_filter($request->matching_fields ?? []); // Filter empty values
        $delimiter = $request->delimiter;
        $importBehavior = $request->import_behavior ?? 'update_insert';
        $generatePasswords = $request->boolean('generate_passwords');

        // Get original file metadata from request (captured at upload time)
        $originalFileName = $request->input('original_file_name', basename($filePath));
        $originalFileLastModified = $request->input('original_file_last_modified');

        // Convert ISO 8601 to readable timestamp format if needed (should already be converted, but double-check)
        if ($originalFileLastModified && strpos($originalFileLastModified, 'T') !== false) {
            try {
                $dateTime = new \DateTime($originalFileLastModified);
                $originalFileLastModified = $dateTime->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                // If parsing fails, keep original format
                Log::warning('Failed to parse file_last_modified in importCsv: '.$originalFileLastModified);
            }
        }

        // Crea un backup della tabella
        $backupFile = CsvBackupManager::createBackup($tableName, 'backend', true);

        // Legge il file CSV
        $csvPath = Storage::disk('backups')->path($filePath);
        $handle = fopen($csvPath, 'r');

        // Salta l'intestazione
        fgetcsv($handle, 0, $delimiter, escape: '\\');

        $totalRows = 0;
        $createdRows = 0;
        $updatedRows = 0;
        $skippedRows = 0;
        $errors = [];

        // Prepare physical log storage (use /storage/logs to avoid permission issues)
        $logBasePath = "logs/imports/{$tableName}/".date('Y-m-d_H-i-s');
        $logPath = storage_path($logBasePath);

        // Create log directory
        if (! file_exists($logPath)) {
            mkdir($logPath, 0755, true);
        }

        // Initialize log files
        $newRecordsLog = [];
        $updatedRecordsLog = [];
        $skippedRecordsLog = [];

        DB::beginTransaction();

        if ($modelClass === 'App\\Models\\User') {
            if ($generatePasswords) {
                User::$generateMissingPasswords = true;
            } else {
                User::$skipPasswordGeneration = true;
            }
        }

        CacheInvalidationContext::suppress();

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

            while (($data = fgetcsv($handle, 0, $delimiter, escape: '\\')) !== false) {
                $totalRows++;
                $rowData = [];

                // Map CSV columns to DB columns (one CSV column can map to multiple DB columns)
                foreach ($columnMapping as $csvIndex => $dbColumnOrColumns) {
                    $dbColumns = is_array($dbColumnOrColumns) ? $dbColumnOrColumns : [$dbColumnOrColumns];
                    if (! isset($data[$csvIndex])) {
                        continue;
                    }
                    $value = $data[$csvIndex];
                    foreach ($dbColumns as $dbColumn) {
                        if (empty($dbColumn)) {
                            continue;
                        }
                        $cellValue = $value;
                        // Convert empty strings to null for nullable fields (especially foreign keys)
                        if ($cellValue === '' && in_array($dbColumn, ['page_id'])) {
                            $cellValue = null;
                        }
                        $rowData[$dbColumn] = $cellValue;
                    }
                }

                // Add default values for missing required fields (same as in seeder import)
                $rowData = $this->addDefaultRequiredFields($rowData, $modelClass);

                if (empty($rowData)) {
                    $skippedRows++;

                    // Add to physical log
                    $skippedRecordsLog[] = [
                        'row' => $totalRows,
                        'matching_fields' => $matchingFields,
                        'matching_values' => [],
                        'reason' => 'empty_row_data',
                        'data' => [],
                        'timestamp' => now()->toDateTimeString(),
                    ];

                    continue;
                }

                // Build matching criteria from multiple fields
                $matchingCriteria = [];
                $matchingValues = [];
                $hasAllMatchingFields = true;

                foreach ($matchingFields as $field) {
                    if (! empty($rowData[$field])) {
                        $matchingCriteria[$field] = $rowData[$field];
                        $matchingValues[$field] = $rowData[$field];
                    } else {
                        $hasAllMatchingFields = false;
                    }
                }

                // Check if record exists using all matching fields (composite key)
                if (! empty($matchingFields) && $hasAllMatchingFields && ! empty($matchingCriteria)) {
                    $query = $modelClass::query();
                    foreach ($matchingCriteria as $field => $value) {
                        $query->where($field, $value);
                    }
                    $existingRecord = $query->first();

                    // Prepare detailed operation info
                    $recordInfo = [
                        'matching_fields' => $matchingFields,
                        'matching_values' => $matchingValues,
                        'row' => $totalRows,
                        'operation_id' => uniqid(),
                    ];

                    if ($existingRecord) {
                        // Update the existing record
                        $primaryKey = $existingRecord->getKeyName();
                        $primaryKeyValue = $existingRecord->getKey();
                        $existingRecord->update($rowData);
                        $updatedRows++;
                        $recordInfo['action'] = 'update';
                        $recordInfo['primary_key'] = $primaryKey;
                        $recordInfo['primary_key_value'] = $primaryKeyValue;

                        // Add password generation flag to recordInfo for frontend display
                        if (
                            $modelClass === 'App\\Models\\User' &&
                            isset($existingRecord->passwordAutoGenerated) &&
                            $existingRecord->passwordAutoGenerated
                        ) {
                            $recordInfo['password_auto_generated'] = true;
                        }

                        // Add to physical log
                        $logEntry = [
                            'row' => $totalRows,
                            'primary_key' => $primaryKey,
                            'primary_key_value' => $primaryKeyValue,
                            'matching_fields' => $matchingFields,
                            'matching_values' => $matchingValues,
                            'data' => $rowData,
                            'timestamp' => now()->toDateTimeString(),
                        ];

                        // Add password generation flag if User model and password was auto-generated
                        if (
                            $modelClass === 'App\\Models\\User' &&
                            isset($existingRecord->passwordAutoGenerated) &&
                            $existingRecord->passwordAutoGenerated
                        ) {
                            $logEntry['password_auto_generated'] = true;
                            $logEntry['password_info'] =
                                "Password auto-generated from User ID + 'd3s10666' and hashed with bcrypt";
                        }

                        $updatedRecordsLog[] = $logEntry;
                    } else {
                        // Check if we should insert new records
                        if ($importBehavior === 'update_only') {
                            // Skip insertion for update_only mode
                            $skippedRows++;
                            $recordInfo['action'] = 'skip';
                            $recordInfo['reason'] = 'update_only_mode';

                            // Add to physical log
                            $skippedRecordsLog[] = [
                                'row' => $totalRows,
                                'matching_fields' => $matchingFields,
                                'matching_values' => $matchingValues,
                                'reason' => 'update_only_mode',
                                'data' => $rowData,
                                'timestamp' => now()->toDateTimeString(),
                            ];
                        } else {
                            // Create new record in update_insert mode
                            $newRecord = $modelClass::create($rowData);
                            if ($newRecord->getKey() === null && isset($newRecord->importSkippedReason)) {
                                $skippedRows++;
                                $recordInfo['action'] = 'skip';
                                $recordInfo['reason'] = $newRecord->importSkippedReason;
                                $recordInfo['row'] = $totalRows;
                                $skippedRecordsLog[] = [
                                    'row' => $totalRows,
                                    'matching_fields' => $matchingFields,
                                    'matching_values' => $matchingValues ?? [],
                                    'reason' => $newRecord->importSkippedReason,
                                    'data' => $rowData,
                                    'timestamp' => now()->toDateTimeString(),
                                ];
                            } else {
                                $createdRows++;
                                $recordInfo['action'] = 'insert';
                                $primaryKey = $newRecord->getKeyName();
                                $primaryKeyValue = $newRecord->getKey();
                                $recordInfo['primary_key'] = $primaryKey;
                                $recordInfo['primary_key_value'] = $primaryKeyValue;

                                // Add password generation flag to recordInfo for frontend display
                                if (
                                    $modelClass === 'App\\Models\\User' &&
                                    isset($newRecord->passwordAutoGenerated) &&
                                    $newRecord->passwordAutoGenerated
                                ) {
                                    $recordInfo['password_auto_generated'] = true;
                                }

                                // Add to physical log
                                $logEntry = [
                                    'row' => $totalRows,
                                    'primary_key' => $primaryKey,
                                    'primary_key_value' => $primaryKeyValue,
                                    'matching_fields' => $matchingFields,
                                    'matching_values' => $matchingValues ?? null,
                                    'data' => $rowData,
                                    'timestamp' => now()->toDateTimeString(),
                                ];

                                // Add password generation flag if User model and password was auto-generated
                                if (
                                    $modelClass === 'App\\Models\\User' &&
                                    isset($newRecord->passwordAutoGenerated) &&
                                    $newRecord->passwordAutoGenerated
                                ) {
                                    $logEntry['password_auto_generated'] = true;
                                    $logEntry['password_info'] =
                                        "Password auto-generated from User ID + 'd3s10666' and hashed with bcrypt";
                                }

                                $newRecordsLog[] = $logEntry;
                            }
                        }
                    }
                } else {
                    // Matching fields are empty or not all present
                    // Check import behavior: if update_only and matching fields incomplete, skip the record
                    if (! empty($matchingFields) && $importBehavior === 'update_only') {
                        // Skip insertion for update_only mode when matching fields are incomplete
                        $skippedRows++;
                        $recordInfo = [
                            'action' => 'skip',
                            'reason' => 'update_only_mode_incomplete_matching_fields',
                            'row' => $totalRows,
                            'operation_id' => uniqid(),
                        ];

                        // Add to physical log
                        $skippedRecordsLog[] = [
                            'row' => $totalRows,
                            'matching_fields' => $matchingFields,
                            'matching_values' => $matchingValues,
                            'reason' => 'update_only_mode_incomplete_matching_fields',
                            'data' => $rowData,
                            'timestamp' => now()->toDateTimeString(),
                        ];
                    } else {
                        // No unique field specified OR update_insert mode: always insert
                        $newRecord = $modelClass::create($rowData);
                        if ($newRecord->getKey() === null && isset($newRecord->importSkippedReason)) {
                            $skippedRows++;
                            $recordInfo = [
                                'action' => 'skip',
                                'reason' => $newRecord->importSkippedReason,
                                'row' => $totalRows,
                                'operation_id' => uniqid(),
                            ];
                            $skippedRecordsLog[] = [
                                'row' => $totalRows,
                                'matching_fields' => $matchingFields,
                                'matching_values' => $matchingValues,
                                'reason' => $newRecord->importSkippedReason,
                                'data' => $rowData,
                                'timestamp' => now()->toDateTimeString(),
                            ];
                        } else {
                            $createdRows++;

                            // Get primary key name and value from the newly created record
                            $primaryKey = $newRecord->getKeyName();
                            $primaryKeyValue = $newRecord->getKey();

                            $recordInfo = [
                                'action' => 'insert',
                                'primary_key' => $primaryKey,
                                'primary_key_value' => $primaryKeyValue,
                                'row' => $totalRows,
                                'operation_id' => uniqid(), // Identificativo univoco per questa operazione
                            ];

                            // Add password generation flag to recordInfo for frontend display
                            if (
                                $modelClass === 'App\\Models\\User' &&
                                isset($newRecord->passwordAutoGenerated) &&
                                $newRecord->passwordAutoGenerated
                            ) {
                                $recordInfo['password_auto_generated'] = true;
                            }

                            // Add to physical log
                            $logEntry = [
                                'row' => $totalRows,
                                'primary_key' => $primaryKey,
                                'primary_key_value' => $primaryKeyValue,
                                'matching_fields' => $matchingFields,
                                'matching_values' => $matchingValues,
                                'data' => $rowData,
                                'timestamp' => now()->toDateTimeString(),
                            ];

                            // Add password generation flag if User model and password was auto-generated
                            if (
                                $modelClass === 'App\\Models\\User' &&
                                isset($newRecord->passwordAutoGenerated) &&
                                $newRecord->passwordAutoGenerated
                            ) {
                                $logEntry['password_auto_generated'] = true;
                                $logEntry['password_info'] =
                                        "Password auto-generated from User ID + 'd3s10666' and hashed with bcrypt";
                            }

                            $newRecordsLog[] = $logEntry;
                        }
                    }
                }

                // Aggiungiamo l'operazione al buffer solo se non è già stata inviata
                $operationKey =
                    $recordInfo['action'].'_'.($recordInfo['primary_key_value'] ?? '').'_row'.$recordInfo['row'];
                if (! in_array($operationKey, $sentOperations)) {
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
                        'status' => 'processing',
                        'progress' => $progress,
                        'total' => $totalRows,
                        'created' => $createdRows,
                        'updated' => $updatedRows,
                        'skipped' => $skippedRows,
                        'current_operation' => end($operationsBuffer), // L'ultima operazione come operazione corrente
                        'operations' => $operationsBuffer, // Tutte le operazioni in buffer
                        'total_rows' => $totalRowsCSV,
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
            if (! empty($operationsBuffer)) {
                echo json_encode([
                    'status' => 'processing',
                    'progress' => 100,
                    'total' => $totalRows,
                    'created' => $createdRows,
                    'updated' => $updatedRows,
                    'skipped' => $skippedRows,
                    'current_operation' => end($operationsBuffer),
                    'operations' => $operationsBuffer,
                    'total_rows' => $totalRowsCSV,
                ]);

                if (ob_get_level() > 0) {
                    ob_flush();
                    flush();
                }
            }

            DB::commit();

            // After the commit, so the jobs see the new rows.
            if ($modelClass === 'App\\Models\\User' && ! $generatePasswords) {
                $this->dispatchMissingPasswords();
            }

            // Rows written with Observers suppressed: drop the dependent cache once.
            CacheFlush::afterImport($modelClass);

            // Save physical log files
            $this->saveImportLogs($logPath, $tableName, $newRecordsLog, $updatedRecordsLog, $skippedRecordsLog, [
                'total_rows' => $totalRows,
                'created' => $createdRows,
                'updated' => $updatedRows,
                'skipped' => $skippedRows,
                'matching_fields' => $matchingFields,
                'import_behavior' => $importBehavior,
                'timestamp' => now()->toDateTimeString(),
                'file_name' => $originalFileName,
                'file_last_modified' => $originalFileLastModified,
            ]);
            $this->makeLogsReadonly($logPath);
            $this->pruneOldLogs(3);

            // Elimina il file CSV caricato dopo l'importazione riuscita
            $this->deleteUploadedFile($filePath);

            // Elimina anche eventuali file temporanei nella stessa directory
            $csvDir = dirname($filePath);
            $csvFilename = basename($filePath);
            $tempFiles = Storage::disk('backups')->files($csvDir);
            foreach ($tempFiles as $tempFile) {
                // Verifica se il file è più vecchio di un'ora per sicurezza
                if (
                    $tempFile !== $filePath &&
                    Storage::disk('backups')->lastModified($tempFile) < now()->subHour()->timestamp
                ) {
                    Storage::disk('backups')->delete($tempFile);
                }
            }

            $result = [
                'status' => 'success',
                'total' => $totalRows,
                'created' => $createdRows,
                'updated' => $updatedRows,
                'skipped' => $skippedRows,
                'backupFile' => $backupFile,
                'logPath' => "/storage/{$logBasePath}",
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
                ($totalRows > 0 || ! empty($newRecordsLog) || ! empty($updatedRecordsLog) || ! empty($skippedRecordsLog))
            ) {
                try {
                    $this->saveImportLogs($logPath, $tableName, $newRecordsLog, $updatedRecordsLog, $skippedRecordsLog, [
                        'total_rows' => $totalRows,
                        'created' => $createdRows,
                        'updated' => $updatedRows,
                        'skipped' => $skippedRows,
                        'matching_fields' => $matchingFields ?? [],
                        'import_behavior' => $importBehavior ?? null,
                        'timestamp' => now()->toDateTimeString(),
                        'error' => $e->getMessage(),
                        'file_name' => $originalFileName ?? basename($filePath),
                        'file_last_modified' => $originalFileLastModified,
                    ]);
                    $this->makeLogsReadonly($logPath);
                    $this->pruneOldLogs(3);
                } catch (\Exception $logError) {
                    // Silently fail if log saving fails
                    Log::error('Failed to save import logs: '.$logError->getMessage());
                }
            }

            // In caso di errore, invia una risposta di errore
            $errorResponse = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'backupFile' => $backupFile ?? null,
            ];

            if (isset($logPath) && isset($logBasePath)) {
                $errorResponse['logPath'] = "/storage/{$logBasePath}";
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
            if ($modelClass === 'App\\Models\\User') {
                User::$skipPasswordGeneration = false;
                User::$generateMissingPasswords = false;
            }
            fclose($handle);
            // Ensure uploaded CSV is removed even on error
            $this->deleteUploadedFile($filePath);
            CacheInvalidationContext::restore(); // Always restore, even on exception
        }
    }

    /**
     * Ottiene lo stato attuale dell'importazione
     *
     * @return JsonResponse
     */
    public function getImportStatus(Request $request)
    {
        // Otteniamo l'ID dell'importazione dalla richiesta
        $importId = $request->get('import_id');

        // In una applicazione reale, qui prenderemmo lo stato da una cache/database
        // Per simulare un progresso in questa versione base, restituiamo una percentuale casuale
        $progress = rand(0, 100);

        return response()->json([
            'status' => $progress >= 100 ? 'completed' : 'processing',
            'progress' => $progress,
            'processed' => rand(10, 100),
            'total' => 100,
            'created' => rand(5, 50),
            'updated' => rand(5, 50),
            'skipped' => rand(0, 10),
        ]);
    }

    /**
     * Save physical log files for import operations
     *
     * @param  string  $logPath  Base path for log files
     * @param  string  $tableName  Table name
     * @param  array  $newRecordsLog  Array of new records
     * @param  array  $updatedRecordsLog  Array of updated records
     * @param  array  $skippedRecordsLog  Array of skipped records
     * @param  array  $summary  Summary information
     */
    private function saveImportLogs(
        string $logPath,
        string $tableName,
        array $newRecordsLog,
        array $updatedRecordsLog,
        array $skippedRecordsLog,
        array $summary
    ): void {
        // Check if encryption is enabled via LOG_CHANNEL=encrypted_daily
        $shouldEncrypt = config('logging.default') === 'encrypted_daily';
        $encryptionService = $shouldEncrypt ? app(LogEncryptionService::class) : null;

        // Save summary file (encrypted only if LOG_CHANNEL=encrypted_daily)
        $summaryFile = $logPath.'/summary.json';
        $summaryContent = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($shouldEncrypt && $encryptionService) {
            $encryptionService->encryptAndSave($summaryFile, $summaryContent);
        } else {
            File::put($summaryFile, $summaryContent);
        }

        // Save new records log (only if there are new records)
        if (! empty($newRecordsLog)) {
            $newRecordsContent = json_encode($newRecordsLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($shouldEncrypt && $encryptionService) {
                $encryptionService->encryptAndSave($logPath.'/new_records.json', $newRecordsContent);
            } else {
                File::put($logPath.'/new_records.json', $newRecordsContent);
            }
        }

        // Save updated records log (only if there are updated records)
        if (! empty($updatedRecordsLog)) {
            $updatedRecordsContent = json_encode($updatedRecordsLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($shouldEncrypt && $encryptionService) {
                $encryptionService->encryptAndSave($logPath.'/updated_records.json', $updatedRecordsContent);
            } else {
                File::put($logPath.'/updated_records.json', $updatedRecordsContent);
            }
        }

        // Save skipped records log (only if there are skipped records)
        if (! empty($skippedRecordsLog)) {
            $skippedRecordsContent = json_encode($skippedRecordsLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($shouldEncrypt && $encryptionService) {
                $encryptionService->encryptAndSave($logPath.'/skipped_rows.json', $skippedRecordsContent);
            } else {
                File::put($logPath.'/skipped_rows.json', $skippedRecordsContent);
            }
        }
    }

    /**
     * Make log files and directories read-only
     */
    private function makeLogsReadonly(string $logPath): void
    {
        if (! file_exists($logPath)) {
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
     * Queue password generation on Horizon; failures are left to the daily run.
     */
    private function dispatchMissingPasswords(): void
    {
        try {
            Artisan::call('auth:rehash-passwords', ['--only-empty' => true, '--no-wait' => true]);
        } catch (\Throwable $e) {
            Log::channel('import')->error('Could not dispatch password generation after import', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete the uploaded CSV file safely
     */
    private function deleteUploadedFile(string $filePath): void
    {
        try {
            if (Storage::disk('backups')->exists($filePath)) {
                Storage::disk('backups')->delete($filePath);
            }

            // Fallback to absolute path removal
            $absolutePath = Storage::disk('backups')->path($filePath);
            if (file_exists($absolutePath)) {
                @unlink($absolutePath);
            }
        } catch (\Exception $e) {
            Log::warning("Could not delete uploaded file {$filePath}: ".$e->getMessage());
        }
    }

    /**
     * Keep only the most recent N log directories globally
     * Also removes logs older than 30 days
     *
     * @param  int  $maxLogs  Maximum number of recent logs to keep
     */
    private function pruneOldLogs(int $maxLogs = 3): void
    {
        $basePath = storage_path('logs/imports');

        if (! File::exists($basePath)) {
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
                if (! in_array($oldLog, $toDelete)) {
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
     */
    private function makeDirWritable(string $path): void
    {
        if (! file_exists($path)) {
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
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
}
