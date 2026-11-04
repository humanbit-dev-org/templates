<?php

namespace App\Http\Controllers\Admin\Action;

use App\Support\Backpack\CsvBackupManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Controller for CSV Import Rollback functionality.
 * Allows developers to restore database tables to pre-import state.
 */
class CsvRollbackController extends Controller
{
	/**
	 * Display list of available backups grouped by table.
	 */
	public function index()
	{
		$backups = $this->getAllBackups();

		return view("admin.csv-rollback.index", [
			"backups" => $backups,
		]);
	}

	/**
	 * Preview backup content before restore.
	 */
	public function preview(Request $request)
	{
		$file = $request->get("file");

		if (!$file) {
			return redirect()->route("csv-rollback.index")->with("error", "File non specificato.");
		}

		if (!$this->isValidBackupFile($file)) {
			return redirect()->route("csv-rollback.index")->with("error", "File di backup non valido o non trovato.");
		}

		$fullPath = Storage::disk("backups")->path($file);
		$fileSize = Storage::disk("backups")->size($file);

		$previewData = $this->buildPreviewContent($fullPath, $fileSize);
		$fileInfo = $this->parseBackupInfo($file, $previewData["analysisSample"], $fileSize);
		$highlightedContent = $this->highlightSql($previewData["content"]);

		return view("admin.csv-rollback.preview", [
			"file" => $file,
			"fileInfo" => $fileInfo,
			"highlightedContent" => $highlightedContent,
			"isTruncatedPreview" => $previewData["truncated"],
			"totalSizeBytes" => $fileSize,
		]);
	}

	/**
	 * Execute rollback to restore table from backup.
	 */
	public function restore(Request $request)
	{
		$file = $request->get("file");

		if (!$file) {
			return redirect()->back()->with("error", "File non specificato.");
		}

		try {
			$result = CsvBackupManager::restoreBackup($file);

			if ($result["success"]) {
				return redirect()
					->route("csv-rollback.index")
					->with(
						"success",
						"Tabella '{$result["table"]}' ripristinata con successo. {$result["rows_affected"]} righe interessate."
					);
			} else {
				return redirect()
					->back()
					->with("error", "Errore durante il ripristino: " . $result["message"]);
			}
		} catch (\Exception $e) {
			return redirect()
				->back()
				->with("error", "Errore durante il ripristino: " . $e->getMessage());
		}
	}

	/**
	 * Download a single backup file.
	 */
	public function download(Request $request)
	{
		$file = $request->get("file");
		if (!$file) {
			return redirect()->route("csv-rollback.index")->with("error", "File non specificato.");
		}

		if (!$this->isValidBackupFile($file)) {
			return redirect()->route("csv-rollback.index")->with("error", "File di backup non valido o non trovato.");
		}

		$fullPath = Storage::disk("backups")->path($file);

		return response()->download($fullPath, basename($file));
	}

	/**
	 * Delete a single backup file.
	 */
	public function delete(Request $request)
	{
		$file = $request->get("file");
		if (!$file) {
			return redirect()->route("csv-rollback.index")->with("error", "File non specificato.");
		}

		if (!$this->isValidBackupFile($file)) {
			return redirect()->route("csv-rollback.index")->with("error", "File di backup non valido o non trovato.");
		}

		try {
			Storage::disk("backups")->delete($file);

			return redirect()->route("csv-rollback.index")->with("success", "Backup eliminato con successo.");
		} catch (\Exception $e) {
			return redirect()
				->route("csv-rollback.index")
				->with("error", 'Errore durante l\'eliminazione: ' . $e->getMessage());
		}
	}

	/**
	 * Get all backups grouped by type and table.
	 */
	private function getAllBackups(): array
	{
		$backups = [];
		$basePath = "csv-imports";

		if (!Storage::disk("backups")->exists($basePath)) {
			return $backups;
		}

		// Scan backup types (backend, job, seeder)
		$types = Storage::disk("backups")->directories($basePath);

		foreach ($types as $typePath) {
			$type = basename($typePath);

			// Scan tables within each type
			$tables = Storage::disk("backups")->directories($typePath);

			foreach ($tables as $tablePath) {
				$table = basename($tablePath);
				$files = Storage::disk("backups")->files($tablePath);

				// Filter only .sql files
				$sqlFiles = array_filter($files, fn($f) => str_ends_with($f, ".sql"));

				if (empty($sqlFiles)) {
					continue;
				}

				// Sort by modification time (newest first)
				usort($sqlFiles, function ($a, $b) {
					return Storage::disk("backups")->lastModified($b) <=> Storage::disk("backups")->lastModified($a);
				});

				foreach ($sqlFiles as $file) {
					$backups[] = [
						"file" => $file,
						"type" => $type,
						"table" => $table,
						"filename" => basename($file),
						"size" => Storage::disk("backups")->size($file),
						"modified" => Storage::disk("backups")->lastModified($file),
					];
				}
			}
		}

		// Sort all backups by modification time (newest first)
		usort($backups, fn($a, $b) => $b["modified"] <=> $a["modified"]);

		return $backups;
	}

	/**
	 * Parse backup file to extract metadata.
	 */
	private function parseBackupInfo(string $file, string $contentSample, int $fileSize): array
	{
		$info = [
			"table" => null,
			"generated" => null,
			"row_count" => null,
			"has_structure" => false,
			"has_data" => false,
			"is_large_file" => $fileSize > 2 * 1024 * 1024,
		];

		// Extract table name
		if (preg_match("/Table Backup:\s*(\w+)/", $contentSample, $matches)) {
			$info["table"] = $matches[1];
		}

		// Extract generation date
		if (preg_match("/Generated:\s*(.+)/", $contentSample, $matches)) {
			$info["generated"] = trim($matches[1]);
		}

		// Check for structure
		$info["has_structure"] = str_contains($contentSample, "CREATE TABLE");

		// Check for data and estimate rows only on manageable samples
		$info["has_data"] = str_contains($contentSample, "INSERT INTO");

		if ($info["has_data"]) {
			$estimatedRows = substr_count($contentSample, "),(") + substr_count($contentSample, "),\n(");
			if ($estimatedRows > 0 && $fileSize <= 2 * 1024 * 1024) {
				$info["row_count"] = $estimatedRows + 1;
			}
		}

		if (!$info["table"]) {
			$info["table"] = basename(dirname($file));
		}

		return $info;
	}

	/**
	 * Apply SQL syntax highlighting to content.
	 */
	private function highlightSql(string $sql): string
	{
		$escaped = htmlspecialchars($sql, ENT_QUOTES, "UTF-8");

		// SQL Keywords
		$keywords = [
			"SELECT",
			"FROM",
			"WHERE",
			"INSERT",
			"INTO",
			"VALUES",
			"UPDATE",
			"SET",
			"DELETE",
			"CREATE",
			"TABLE",
			"DROP",
			"ALTER",
			"INDEX",
			"PRIMARY",
			"KEY",
			"FOREIGN",
			"REFERENCES",
			"CONSTRAINT",
			"NOT",
			"NULL",
			"DEFAULT",
			"AUTO_INCREMENT",
			"ENGINE",
			"CHARSET",
			"COLLATE",
			"IF",
			"EXISTS",
			"LOCK",
			"TABLES",
			"WRITE",
			"UNLOCK",
			"AND",
			"OR",
			"IN",
			"LIKE",
			"BETWEEN",
			"ORDER",
			"BY",
			"ASC",
			"DESC",
			"LIMIT",
			"OFFSET",
			"JOIN",
			"LEFT",
			"RIGHT",
			"INNER",
			"OUTER",
			"ON",
			"AS",
			"UNIQUE",
			"UNSIGNED",
			"BIGINT",
			"INT",
			"VARCHAR",
			"TEXT",
			"TIMESTAMP",
			"DATE",
			"DATETIME",
			"DECIMAL",
			"TINYINT",
			"BOOLEAN",
			"CHARACTER",
		];

		// Highlight comments (-- ...)
		$escaped = preg_replace('/^(--.*?)$/m', '<span class="sql-comment">$1</span>', $escaped);

		// Highlight strings ('...')
		$escaped = preg_replace("/&#039;([^&]*?)&#039;/", '<span class="sql-string">&#039;$1&#039;</span>', $escaped);

		// Highlight numbers
		$escaped = preg_replace("/\b(\d+(?:\.\d+)?)\b/", '<span class="sql-number">$1</span>', $escaped);

		// Highlight keywords (case-insensitive)
		foreach ($keywords as $keyword) {
			$escaped = preg_replace(
				"/\b(" . preg_quote($keyword, "/") . ")\b/i",
				'<span class="sql-keyword">$1</span>',
				$escaped
			);
		}

		// Highlight table names after backticks
		$escaped = preg_replace("/`([^`]+)`/", '<span class="sql-table">`$1`</span>', $escaped);

		return $escaped;
	}

	/**
	 * Validate backup file path and scope.
	 */
	private function isValidBackupFile(string $file): bool
	{
		if (!str_starts_with($file, "csv-imports/") || !str_ends_with($file, ".sql")) {
			return false;
		}

		$fullPath = Storage::disk("backups")->path($file);
		$basePath = Storage::disk("backups")->path("");
		$realBasePath = realpath($basePath);
		$realFilePath = realpath($fullPath);

		if ($realBasePath === false || $realFilePath === false) {
			return false;
		}

		return str_starts_with($realFilePath, $realBasePath) && file_exists($realFilePath);
	}

	/**
	 * Build a memory-safe preview payload for SQL backups.
	 */
	private function buildPreviewContent(string $fullPath, int $fileSize): array
	{
		$maxHighlightBytes = 2 * 1024 * 1024;
		$maxPreviewBytes = 512 * 1024;
		$maxAnalysisBytes = 256 * 1024;

		if ($fileSize <= $maxHighlightBytes) {
			$content = file_get_contents($fullPath);

			return [
				"content" => $content ?: "",
				"analysisSample" => $content ?: "",
				"truncated" => false,
			];
		}

		$preview = $this->readFileChunk($fullPath, $maxPreviewBytes);
		$analysis = $this->readFileChunk($fullPath, $maxAnalysisBytes);
		$notice =
			"-- Preview parziale: file troppo grande per il rendering completo. " .
			"Mostrati i primi " .
			number_format($maxPreviewBytes / 1024) .
			" KB.\n\n";

		return [
			"content" => $notice . $preview,
			"analysisSample" => $analysis,
			"truncated" => true,
		];
	}

	/**
	 * Read only the first bytes from a file.
	 */
	private function readFileChunk(string $fullPath, int $maxBytes): string
	{
		$handle = fopen($fullPath, "rb");
		if (!$handle) {
			return "";
		}

		$content = "";
		$chunkSize = 8192;
		$bytesRead = 0;

		while (!feof($handle) && $bytesRead < $maxBytes) {
			$remaining = $maxBytes - $bytesRead;
			$buffer = fread($handle, min($chunkSize, $remaining));
			if ($buffer === false) {
				break;
			}

			$content .= $buffer;
			$bytesRead += strlen($buffer);
		}

		fclose($handle);

		return $content;
	}
}
