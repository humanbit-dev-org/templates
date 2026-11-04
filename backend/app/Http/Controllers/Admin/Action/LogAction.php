<?php

namespace App\Http\Controllers\Admin\Action;

use App\Http\Controllers\Controller;
use App\Services\Logging\LogEncryptionService;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\File;

class LogAction extends Controller implements HasMiddleware
{
	protected $encryptionService;

	/**
	 * With CloudWatch on, the logs are not on this instance: every action
	 * redirects to the log group in the AWS console.
	 */
	public static function middleware(): array
	{
		return [
			static function (Request $request, Closure $next) {
				$group = config("logging.cloudwatch.group");

				return filled($group) ? redirect()->away(self::cloudWatchConsoleUrl($group)) : $next($request);
			},
		];
	}

	public static function cloudWatchConsoleUrl(string $group): string
	{
		$region = (string) config("logging.cloudwatch.region");

		// The console fragment encodes the group twice: "/" becomes "$252F".
		$encoded = str_replace("%", '$25', rawurlencode($group));

		return "https://{$region}.console.aws.amazon.com/cloudwatch/home?region={$region}#logsV2:log-groups/log-group/{$encoded}";
	}

	public function __construct(LogEncryptionService $encryptionService)
	{
		$this->encryptionService = $encryptionService;
	}

	public function export(Request $request)
	{
		$fileName = $request->get("file", "laravel.log");
		$levelFilter = $request->get("level");
		$searchFilter = $request->get("search");

		// Resolve file path
		$filePath = $this->resolveFilePath($fileName);

		// Security check: prevent directory traversal
		$basePath = $this->getBasePathForFile($fileName);
		$realBasePath = realpath($basePath);
		$realFilePath = realpath($filePath);

		if (!File::exists($filePath) || !$realFilePath || !$realBasePath || strpos($realFilePath, $realBasePath) !== 0) {
			abort(404, "Log file not found");
		}

		// Check file size before reading
		$fileSize = File::size($filePath);
		$maxFileSize = 10 * 1024 * 1024; // 10MB
		$absoluteMaxSize = 100 * 1024 * 1024; // 100MB - absolute maximum
		$maxLines = 50000; // Maximum lines to read for large files

		if ($fileSize > $absoluteMaxSize) {
			abort(
				413,
				"File too large (" .
					$this->formatBytes($fileSize) .
					"). Maximum size: " .
					$this->formatBytes($absoluteMaxSize) .
					". Please archive or rotate this log file."
			);
		}

		// For raw export (no filters), always return the FULL decrypted content.
		if (!$levelFilter && !$searchFilter) {
			$fullContent = $this->encryptionService->readAndDecrypt($filePath);

			return response($fullContent, 200, [
				"Content-Type" => "text/plain",
				"Content-Disposition" => 'attachment; filename="' . $fileName . '"',
			]);
		}

		// For filtered export, keep optimized large-file behavior.
		if ($fileSize > $maxFileSize) {
			$logContent = $this->readLastLines($filePath, $maxLines);
		} else {
			$logContent = $this->encryptionService->readAndDecrypt($filePath);
		}

		// Check file type
		$fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
		$isJson = $fileExtension === "json";

		// Parse logs to apply filters
		if ($isJson) {
			$logs = $this->parseJsonFile($logContent, $fileName);
		} else {
			$logs = $this->parseLogFile($logContent);
		}

		// Apply level filter
		if ($levelFilter) {
			$logs = array_filter($logs, fn($log) => $log["level"] === strtoupper($levelFilter));
		}

		// Apply search filter
		if ($searchFilter) {
			$logs = array_filter($logs, function ($log) use ($searchFilter) {
				return stripos($log["message"], $searchFilter) !== false ||
					stripos($log["timestamp"], $searchFilter) !== false;
			});
		}

		// Reconstruct log content
		$exportContent = "";
		foreach ($logs as $log) {
			$exportContent .= "[{$log["timestamp"]}] {$log["environment"]}.{$log["level"]}: {$log["message"]}\n";
		}

		// Create filtered filename
		$filenameParts = [pathinfo($fileName, PATHINFO_FILENAME)];
		if ($levelFilter) {
			$filenameParts[] = strtolower($levelFilter);
		}
		if ($searchFilter) {
			$filenameParts[] = "filtered";
		}
		$exportFileName = implode("_", $filenameParts) . ".log";

		// Return filtered content as download
		return response($exportContent, 200, [
			"Content-Type" => "text/plain",
			"Content-Disposition" => 'attachment; filename="' . $exportFileName . '"',
		]);
	}

	public function index(Request $request)
	{
		$logsPath = storage_path("logs");

		// Get all log files
		$logFiles = $this->getLogFiles($logsPath);

		// Get last global update (most recent log file)
		$lastGlobalUpdate = !empty($logFiles) ? $logFiles[0]["modified"] : null;

		return view("admin.logs.index", [
			"logFiles" => $logFiles,
			"lastGlobalUpdate" => $lastGlobalUpdate,
		]);
	}

	/**
	 * View decrypted log file in a new page
	 */
	public function view(Request $request)
	{
		$fileName = $request->get("file");
		if (!$fileName) {
			abort(404, "File not specified");
		}

		// Resolve file path
		$filePath = $this->resolveFilePath($fileName);

		// Security check: prevent directory traversal
		$basePath = $this->getBasePathForFile($fileName);
		$realBasePath = realpath($basePath);
		$realFilePath = realpath($filePath);

		if (!File::exists($filePath) || !$realFilePath || !$realBasePath || strpos($realFilePath, $realBasePath) !== 0) {
			abort(404, "Log file not found");
		}

		$fileSize = File::size($filePath);
		$previewData = $this->buildLogPreviewContent($filePath, $fileSize);
		$content = $previewData["content"];

		// Return plain text view
		return response($content, 200, [
			"Content-Type" => "text/plain; charset=utf-8",
			"Content-Disposition" => 'inline; filename="' . basename($fileName) . '"',
		]);
	}

	/**
	 * Show formatted log file detail page - elegant raw view
	 */
	public function show(Request $request)
	{
		$fileName = $request->get("file");
		if (!$fileName) {
			return redirect(url("admin/logs"))->with("error", "File non specificato.");
		}

		// Resolve file path
		$filePath = $this->resolveFilePath($fileName);

		// Security check: prevent directory traversal
		$basePath = $this->getBasePathForFile($fileName);
		$realBasePath = realpath($basePath);
		$realFilePath = realpath($filePath);

		if (!File::exists($filePath) || !$realFilePath || !$realBasePath || strpos($realFilePath, $realBasePath) !== 0) {
			return redirect(url("admin/logs"))->with("error", "File di log non trovato.");
		}

		// Get file info
		$fileSize = File::size($filePath);
		$fileModified = Carbon::createFromTimestamp(File::lastModified($filePath))
			->timezone("Europe/Rome")
			->locale("it")
			->isoFormat("D MMMM YYYY, HH:mm");

		$previewData = $this->buildLogPreviewContent($filePath, $fileSize);
		$content = $previewData["content"];

		// Check file extension for formatting
		$fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
		$isJson = $fileExtension === "json";

		// Format content with syntax highlighting
		$formattedContent = $isJson ? $this->formatJsonContent($content) : $this->formatLogContent($content);

		return view("admin.logs.show", [
			"fileName" => $fileName,
			"fileDisplayName" => basename($fileName),
			"fileSize" => $this->formatBytes($fileSize),
			"fileModified" => $fileModified,
			"formattedContent" => $formattedContent,
			"isTruncatedPreview" => $previewData["truncated"],
		]);
	}

	private function buildLogPreviewContent(string $filePath, int $fileSize): array
	{
		$maxFullReadBytes = 2 * 1024 * 1024;
		$maxPreviewLines = 20000;

		if ($fileSize <= $maxFullReadBytes) {
			return [
				"content" => $this->encryptionService->readAndDecrypt($filePath),
				"truncated" => false,
			];
		}

		$preview = $this->readLastLines($filePath, $maxPreviewLines);
		$notice =
			"[WARNING] Preview parziale: file troppo grande, mostrate solo le ultime " .
			number_format($maxPreviewLines) .
			" righe.\n\n";

		return [
			"content" => $notice . $preview,
			"truncated" => true,
		];
	}

	/**
	 * Format log content with syntax highlighting
	 */
	private function formatLogContent(string $content): string
	{
		$lines = explode("\n", $content);
		$formatted = [];
		$lastTime = null;

		foreach ($lines as $line) {
			$line = e($line); // Escape HTML

			// Match Laravel log format: [timestamp] environment.LEVEL: message
			if (preg_match('/^\[(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})\]\s+(\w+)\.(\w+):\s*(.*)$/', $line, $matches)) {
				$time = $matches[2];
				$env = $matches[3];
				$level = strtoupper($matches[4]);
				$message = $matches[5];

				$levelClass = $this->getLevelClass($level);

				// Group entries by time: show time only on first entry of group
				$isNewGroup = $time !== $lastTime;
				$lastTime = $time;

				$groupClass = $isNewGroup ? " log-group-start" : " log-group-cont";
				$timeHtml = $isNewGroup
					? '<span class="log-time">' . $time . "</span>"
					: '<span class="log-time log-time-dim"></span>';

				$formatted[] = sprintf(
					'<div class="log-entry%s">%s <span class="log-env">%s</span>.<span class="%s">%s</span>: %s<button class="log-copy-btn" onclick="copyLogEntry(this)" title="Copia"><i class="la la-copy"></i></button></div>',
					$groupClass,
					$timeHtml,
					$env,
					$levelClass,
					$level,
					$message
				);
			} elseif (preg_match("/^#\d+\s/", $line) || preg_match("/^\s+at\s/", $line)) {
				// Stack trace lines
				$formatted[] = '<div class="log-stacktrace">' . $line . "</div>";
			} else {
				$formatted[] = $line;
			}
		}

		return implode("\n", $formatted);
	}

	/**
	 * Format JSON content with syntax highlighting
	 */
	private function formatJsonContent(string $content): string
	{
		$decoded = json_decode($content);
		if (json_last_error() !== JSON_ERROR_NONE) {
			return e($content);
		}

		$pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$pretty = e($pretty);

		// Highlight JSON syntax
		$pretty = preg_replace("/&quot;([^&]+)&quot;(\s*:)/", '<span class="json-key">"$1"</span>$2', $pretty);
		$pretty = preg_replace("/:\s*&quot;([^&]*)&quot;/", ': <span class="json-string">"$1"</span>', $pretty);
		$pretty = preg_replace("/:\s*(\d+\.?\d*)/", ': <span class="json-number">$1</span>', $pretty);
		$pretty = preg_replace("/:\s*(true|false)/", ': <span class="json-boolean">$1</span>', $pretty);
		$pretty = preg_replace("/:\s*(null)/", ': <span class="json-null">$1</span>', $pretty);

		return $pretty;
	}

	/**
	 * Get CSS class for log level
	 */
	private function getLevelClass(string $level): string
	{
		return match ($level) {
			"EMERGENCY", "ALERT", "CRITICAL" => "log-critical",
			"ERROR" => "log-error",
			"WARNING" => "log-warning",
			"NOTICE" => "log-notice",
			"INFO" => "log-info",
			"DEBUG" => "log-debug",
			default => "log-info",
		};
	}

	/**
	 * Get all log files from logs directory (recursive, includes .log and .json)
	 * Now includes files from both storage/logs and database/import
	 * Uses RecursiveDirectoryIterator for memory efficiency
	 */
	private function getLogFiles($logsPath)
	{
		$logFiles = [];

		// Get files from storage/logs using RecursiveDirectoryIterator (memory efficient)
		if (File::exists($logsPath)) {
			try {
				$dirIterator = new \RecursiveDirectoryIterator($logsPath, \RecursiveDirectoryIterator::SKIP_DOTS);
				$iterator = new \RecursiveIteratorIterator($dirIterator);

				foreach ($iterator as $file) {
					if (!$file->isFile()) {
						continue;
					}

					$ext = strtolower($file->getExtension());
					if (!in_array($ext, ["log", "json"])) {
						continue;
					}

					$fullPath = $file->getRealPath();
					if ($fullPath === false) {
						continue;
					}

					$relativePath = ltrim(str_replace($logsPath, "", $fullPath), DIRECTORY_SEPARATOR);

					$logFiles[] = [
						"name" => "storage/logs/" . $relativePath, // Prefix to identify source
						"path" => $fullPath,
						"size" => $file->getSize(),
						"modified" => $file->getMTime(),
						"type" => $ext,
					];
				}
			} catch (\Exception $e) {
				// If directory doesn't exist or can't be read, skip silently
			}
		}

		// Get files from database/import using RecursiveDirectoryIterator (memory efficient)
		$importPath = database_path("import");
		if (File::exists($importPath)) {
			try {
				$dirIterator = new \RecursiveDirectoryIterator($importPath, \RecursiveDirectoryIterator::SKIP_DOTS);
				$iterator = new \RecursiveIteratorIterator($dirIterator);

				foreach ($iterator as $file) {
					if (!$file->isFile()) {
						continue;
					}

					$fullPath = $file->getRealPath();
					if ($fullPath === false) {
						continue;
					}

					// Skip files in 'processed' directories (not logs, just metadata)
					if (str_contains($fullPath, DIRECTORY_SEPARATOR . "processed" . DIRECTORY_SEPARATOR)) {
						continue;
					}

					$ext = strtolower($file->getExtension());
					if (!in_array($ext, ["log", "json"])) {
						continue;
					}

					// Skip .meta.json files (import metadata, not logs)
					if (str_ends_with(strtolower($file->getFilename()), ".meta.json")) {
						continue;
					}

					$relativePath = ltrim(str_replace($importPath, "", $fullPath), DIRECTORY_SEPARATOR);

					$logFiles[] = [
						"name" => "database/import/" . $relativePath, // Prefix to identify source
						"path" => $fullPath,
						"size" => $file->getSize(),
						"modified" => $file->getMTime(),
						"type" => $ext,
					];
				}
			} catch (\Exception $e) {
				// If directory doesn't exist or can't be read, skip silently
			}
		}

		// Sort by modification date (most recent first)
		usort($logFiles, fn($a, $b) => $b["modified"] <=> $a["modified"]);

		return $logFiles;
	}

	/**
	 * Resolve the full file path from a file name (which may include source prefix)
	 */
	private function resolveFilePath($fileName)
	{
		if (strpos($fileName, "storage/logs/") === 0) {
			$relativePath = substr($fileName, strlen("storage/logs/"));

			return storage_path("logs") . "/" . $relativePath;
		} elseif (strpos($fileName, "database/import/") === 0) {
			$relativePath = substr($fileName, strlen("database/import/"));

			return database_path("import") . "/" . $relativePath;
		} else {
			// Legacy support: assume storage/logs if no prefix
			return storage_path("logs") . "/" . $fileName;
		}
	}

	/**
	 * Get the base path for security checks based on file name
	 */
	private function getBasePathForFile($fileName)
	{
		if (strpos($fileName, "storage/logs/") === 0) {
			return storage_path("logs");
		} elseif (strpos($fileName, "database/import/") === 0) {
			return database_path("import");
		} else {
			// Legacy support: assume storage/logs if no prefix
			return storage_path("logs");
		}
	}

	/**
	 * Read last N lines from a log file (handles encrypted files)
	 * Memory-efficient implementation that avoids loading entire file
	 */
	private function readLastLines($filePath, $maxLines = 10000): string
	{
		$fileSize = File::size($filePath);
		$readChunkSize = 10 * 1024 * 1024; // Read last 10MB max - reduced for memory efficiency

		// For very large files, limit chunk size further
		if ($fileSize > 50 * 1024 * 1024) {
			$readChunkSize = 5 * 1024 * 1024; // Only 5MB for very large files
		}

		// Read a sample to check if encrypted
		$sampleSize = min(1024, $fileSize); // Read first 1KB
		$handle = fopen($filePath, "r");
		if (!$handle) {
			return "[ERROR] Could not open file";
		}

		$sample = fread($handle, $sampleSize);
		fclose($handle);

		// Check if file is encrypted
		if ($this->encryptionService->isEncrypted($sample)) {
			try {
				// Decrypt full content, then return only last N lines as preview.
				$decryptedContent = $this->encryptionService->readAndDecrypt($filePath);
				// Extract last N lines efficiently
				$lines = explode("\n", $decryptedContent);
				$lineCount = count($lines);
				if ($lineCount > $maxLines) {
					$lastLines = array_slice($lines, -$maxLines);
				} else {
					$lastLines = $lines;
				}

				return implode("\n", $lastLines);
			} catch (\Exception $e) {
				return "[ERROR] Could not decrypt file: " .
					$e->getMessage() .
					"\n[INFO] File size: " .
					$this->formatBytes($fileSize) .
					"\n[INFO] Consider rotating or archiving this log file.";
			}
		}

		// For non-encrypted files, read only last portion efficiently
		$chunkSize = min($readChunkSize, $fileSize);
		$handle = fopen($filePath, "r");
		if (!$handle) {
			return "[ERROR] Could not open file";
		}

		// Seek to position before last chunk
		fseek($handle, -$chunkSize, SEEK_END);
		$content = fread($handle, $chunkSize);
		fclose($handle);

		// Extract last N lines efficiently
		$lines = explode("\n", $content);
		// Remove first line as it might be incomplete
		if (count($lines) > 1) {
			array_shift($lines);
		}

		$lineCount = count($lines);
		if ($lineCount > $maxLines) {
			$lastLines = array_slice($lines, -$maxLines);
		} else {
			$lastLines = $lines;
		}

		return implode("\n", $lastLines);
	}

	/**
	 * Format bytes to human readable
	 */
	private function formatBytes($bytes, $precision = 2)
	{
		$units = ["B", "KB", "MB", "GB"];
		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);
		$bytes /= pow(1024, $pow);

		return round($bytes, $precision) . " " . $units[$pow];
	}

	/**
	 * Parse Laravel log file into structured array (legacy method, kept for JSON)
	 * Memory-efficient parsing with size limits
	 */
	private function parseLogFile($content)
	{
		$logs = [];

		// Limit content size to avoid memory exhaustion
		$maxContentSize = 10 * 1024 * 1024; // 10MB max
		if (strlen($content) > $maxContentSize) {
			// Truncate content and add warning
			$content = substr($content, -$maxContentSize);
			$logs[] = [
				"timestamp" => date("Y-m-d H:i:s"),
				"environment" => "system",
				"level" => "WARNING",
				"message" => "[WARNING] Log content truncated due to size limits. Showing last portion only.",
			];
		}

		$pattern = '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.*?)(?=\[\d{4}-\d{2}-\d{2}|$)/s';

		// Use preg_match_all with limit to avoid processing too much
		preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

		foreach ($matches as $match) {
			$logs[] = [
				"timestamp" => $match[1] ?? "",
				"environment" => $match[2] ?? "",
				"level" => strtoupper($match[3] ?? ""),
				"message" => trim($match[4] ?? ""),
			];
		}

		// Group consecutive logs with same timestamp and level
		return $this->groupConsecutiveLogs($logs);
	}

	/**
	 * Group consecutive log entries - JWT pattern triggers new group
	 */
	private function groupConsecutiveLogs($logs)
	{
		if (empty($logs)) {
			return $logs;
		}

		$grouped = [];
		$currentGroup = null;
		$isInJwtGroup = false;

		foreach ($logs as $log) {
			// Check if this message starts a new JWT flow (marker for new group)
			$startsNewGroup = stripos($log["message"], "JWT:") === 0;

			// If JWT marker found, finalize previous group and start new one
			if ($startsNewGroup) {
				// Save previous group if exists
				if ($currentGroup !== null) {
					$grouped[] = $currentGroup;
				}

				// Start new JWT group
				$currentGroup = $log;
				$currentGroup["grouped"] = false;
				$currentGroup["jwt_base_timestamp"] = $log["timestamp"];
				$isInJwtGroup = true;
			} elseif ($isInJwtGroup && $currentGroup !== null) {
				// We're in a JWT group - check if log is within time window (60 seconds)
				$baseTime = strtotime($currentGroup["jwt_base_timestamp"]);
				$logTime = strtotime($log["timestamp"]);
				$timeDiff = abs($logTime - $baseTime);

				// Group if within 60 seconds and same level/environment
				if (
					$timeDiff <= 60 &&
					$currentGroup["level"] === $log["level"] &&
					$currentGroup["environment"] === $log["environment"]
				) {
					// Append message to current JWT group
					$currentGroup["message"] .= "\n" . $log["message"];
					$currentGroup["grouped"] = true;
				} else {
					// Outside time window or different level - close JWT group and start new
					$grouped[] = $currentGroup;
					$currentGroup = $log;
					$currentGroup["grouped"] = false;
					$isInJwtGroup = false;
				}
			} elseif (
				$currentGroup !== null &&
				$currentGroup["timestamp"] === $log["timestamp"] &&
				$currentGroup["level"] === $log["level"] &&
				$currentGroup["environment"] === $log["environment"]
			) {
				// Non-JWT group: same timestamp/level/environment
				$currentGroup["message"] .= "\n" . $log["message"];
				$currentGroup["grouped"] = true;
			} else {
				// Different attributes - save previous and start new
				if ($currentGroup !== null) {
					$grouped[] = $currentGroup;
				}

				// Start new group (non-JWT)
				$currentGroup = $log;
				$currentGroup["grouped"] = false;
				$isInJwtGroup = false;
			}
		}

		// Add the last group
		if ($currentGroup !== null) {
			$grouped[] = $currentGroup;
		}

		return $grouped;
	}

	/**
	 * Parse JSON file into structured array for display
	 *
	 * @param  string  $content  JSON content
	 * @param  string  $fileName  File name for context
	 * @return array Structured log entries
	 */
	private function parseJsonFile(string $content, string $fileName): array
	{
		$logs = [];

		try {
			$data = json_decode($content, true);

			if (json_last_error() !== JSON_ERROR_NONE) {
				// If not valid JSON, treat as single entry
				$filePath = $this->resolveFilePath($fileName);
				$lastModified = File::exists($filePath) ? File::lastModified($filePath) : time();

				return [
					[
						"timestamp" => date("Y-m-d H:i:s", $lastModified),
						"environment" => "local",
						"level" => "INFO",
						"message" => $content,
						"is_json" => false,
					],
				];
			}

			// Handle different JSON structures
			if (isset($data["timestamp"])) {
				// Single JSON object
				$logs[] = [
					"timestamp" => $data["timestamp"] ?? date("Y-m-d H:i:s"),
					"environment" => $data["environment"] ?? "local",
					"level" => strtoupper($data["level"] ?? "INFO"),
					"message" => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
					"is_json" => true,
					"json_data" => $data,
				];
			} elseif (is_array($data) && isset($data[0])) {
				// Array of JSON objects
				foreach ($data as $index => $item) {
					if (is_array($item)) {
						$logs[] = [
							"timestamp" => $item["timestamp"] ?? ($item["created_at"] ?? date("Y-m-d H:i:s")),
							"environment" => $item["environment"] ?? "local",
							"level" => strtoupper($item["level"] ?? "INFO"),
							"message" => json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
							"is_json" => true,
							"json_data" => $item,
						];
					}
				}
			} else {
				// Generic JSON structure
				$filePath = $this->resolveFilePath($fileName);
				$lastModified = File::exists($filePath) ? File::lastModified($filePath) : time();
				$logs[] = [
					"timestamp" => date("Y-m-d H:i:s", $lastModified),
					"environment" => "local",
					"level" => "INFO",
					"message" => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
					"is_json" => true,
					"json_data" => $data,
				];
			}
		} catch (\Exception $e) {
			// If parsing fails, return as single entry
			$logs[] = [
				"timestamp" => date("Y-m-d H:i:s"),
				"environment" => "local",
				"level" => "ERROR",
				"message" => "Failed to parse JSON: " . $e->getMessage() . "\n\n" . $content,
				"is_json" => false,
			];
		}

		return $logs;
	}

	public function delete(Request $request)
	{
		$fileName = $request->get("file");
		if (!$fileName) {
			return redirect(url("admin/logs"))->with("error", "File non specificato.");
		}

		// Resolve file path
		$filePath = $this->resolveFilePath($fileName);

		// Security check: prevent directory traversal
		$basePath = $this->getBasePathForFile($fileName);
		$realBasePath = realpath($basePath);
		$realFilePath = realpath($filePath);

		if (!File::exists($filePath) || !$realFilePath || !$realBasePath || strpos($realFilePath, $realBasePath) !== 0) {
			return redirect(url("admin/logs"))->with("error", "File di log non trovato.");
		}

		try {
			File::delete($filePath);

			return redirect(url("admin/logs"))->with("success", "File eliminato con successo.");
		} catch (\Exception $e) {
			return redirect(url("admin/logs"))->with("error", 'Errore durante l\'eliminazione: ' . $e->getMessage());
		}
	}

	public function deleteGroup(Request $request)
	{
		$groupKey = $request->string("group")->toString();
		if ($groupKey === "") {
			return redirect(url("admin/logs"))->with("error", "Gruppo non specificato.");
		}

		$filesInGroup = $this->getFilesByGroup($groupKey);
		if (empty($filesInGroup)) {
			return redirect(url("admin/logs"))->with("error", "Nessun file trovato nel gruppo selezionato.");
		}

		$deletedFiles = 0;
		foreach ($filesInGroup as $fileName) {
			$filePath = $this->resolveFilePath($fileName);
			$basePath = $this->getBasePathForFile($fileName);
			$realBasePath = realpath($basePath);
			$realFilePath = realpath($filePath);

			if (!File::exists($filePath) || !$realFilePath || !$realBasePath || strpos($realFilePath, $realBasePath) !== 0) {
				continue;
			}

			if (File::delete($filePath)) {
				$deletedFiles++;
			}
		}

		if ($deletedFiles === 0) {
			return redirect(url("admin/logs"))->with("error", "Nessun file eliminato dal gruppo selezionato.");
		}

		return redirect(url("admin/logs"))->with("success", "Eliminati {$deletedFiles} file dal gruppo.");
	}

	public function deleteAll()
	{
		$logsPath = storage_path("logs");
		$allFiles = $this->getLogFiles($logsPath);
		if (empty($allFiles)) {
			return redirect(url("admin/logs"))->with("error", "Nessun file di log da eliminare.");
		}

		$deletedFiles = 0;
		foreach ($allFiles as $fileData) {
			$fileName = $fileData["name"] ?? "";
			if ($fileName === "") {
				continue;
			}

			$filePath = $this->resolveFilePath($fileName);
			$basePath = $this->getBasePathForFile($fileName);
			$realBasePath = realpath($basePath);
			$realFilePath = realpath($filePath);

			if (!File::exists($filePath) || !$realFilePath || !$realBasePath || strpos($realFilePath, $realBasePath) !== 0) {
				continue;
			}

			if (File::delete($filePath)) {
				$deletedFiles++;
			}
		}

		if ($deletedFiles === 0) {
			return redirect(url("admin/logs"))->with("error", "Nessun file eliminato.");
		}

		return redirect(url("admin/logs"))->with("success", "Eliminati {$deletedFiles} file di log.");
	}

	private function getFilesByGroup(string $groupKey): array
	{
		$logsPath = storage_path("logs");
		$allFiles = $this->getLogFiles($logsPath);
		$filesInGroup = [];

		foreach ($allFiles as $file) {
			$relativePath = $file["name"] ?? "";
			if ($relativePath === "") {
				continue;
			}

			if (strpos($relativePath, "storage/logs/imports/") === 0) {
				$afterBase = substr($relativePath, strlen("storage/logs/imports/"));
				$firstFolder = explode("/", $afterBase)[0] ?? "";
				$currentGroup = "storage/logs/imports" . ($firstFolder ? "/" . $firstFolder : "");
			} elseif (strpos($relativePath, "storage/logs/") === 0) {
				$currentGroup = "storage/logs";
			} elseif (strpos($relativePath, "database/import/") === 0) {
				$afterBase = substr($relativePath, strlen("database/import/"));
				$firstFolder = explode("/", $afterBase)[0] ?? "";
				$currentGroup = "database/import" . ($firstFolder ? "/" . $firstFolder : "");
			} else {
				$currentGroup = "storage/logs";
			}

			if ($currentGroup === $groupKey) {
				$filesInGroup[] = $relativePath;
			}
		}

		return $filesInGroup;
	}
}
