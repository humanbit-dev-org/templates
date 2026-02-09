<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogController extends Controller
{
	public function export(Request $request)
	{
		$logsPath = storage_path("logs");
		$fileName = $request->get("file", "laravel.log");
		$levelFilter = $request->get("level");
		$searchFilter = $request->get("search");

		// Security check: prevent directory traversal
		$filePath = $logsPath . "/" . $fileName;
		if (!File::exists($filePath) || dirname($filePath) !== $logsPath) {
			abort(404, "Log file not found");
		}

		// If no filters, return the entire file
		if (!$levelFilter && !$searchFilter) {
			return response()->download($filePath, $fileName, [
				"Content-Type" => "text/plain",
			]);
		}

		// Parse logs to apply filters
		$logContent = File::get($filePath);
		$logs = $this->parseLogFile($logContent);

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

		if (empty($logFiles)) {
			return view("admin.logs.index", [
				"logs" => [],
				"logFiles" => [],
				"selectedFile" => null,
				"fileInfo" => null,
				"lastGlobalUpdate" => null,
				"total" => 0,
				"currentPage" => 1,
				"perPage" => 10,
				"selectedLevel" => null,
				"searchQuery" => null,
				"request" => $request,
				"error" => "No log files found",
			]);
		}

		// Get selected file or default to most recent
		$selectedFile = $request->get("file", $logFiles[0]["name"]);
		$logPath = $logsPath . "/" . $selectedFile;

		// Security check: prevent directory traversal
		if (!File::exists($logPath) || dirname($logPath) !== $logsPath) {
			return view("admin.logs.index", [
				"logs" => [],
				"logFiles" => $logFiles,
				"selectedFile" => null,
				"fileInfo" => null,
				"lastGlobalUpdate" => !empty($logFiles) ? $logFiles[0]["modified"] : null,
				"total" => 0,
				"currentPage" => 1,
				"perPage" => 10,
				"selectedLevel" => null,
				"searchQuery" => null,
				"request" => $request,
				"error" => "Invalid log file selected",
			]);
		}

		// Get file info
		$fileInfo = $this->getFileInfo($logPath);

		// Get last global update (most recent log file)
		$lastGlobalUpdate = !empty($logFiles) ? $logFiles[0]["modified"] : null;

		// Read and parse log file
		$logContent = File::get($logPath);
		$logs = $this->parseLogFile($logContent);

		// Apply filters
		$level = $request->get("level");
		$search = $request->get("search");

		if ($level) {
			$logs = array_filter($logs, fn($log) => $log["level"] === $level);
		}

		if ($search) {
			$logs = array_filter($logs, function ($log) use ($search) {
				return stripos($log["message"], $search) !== false || stripos($log["timestamp"], $search) !== false;
			});
		}

		// Pagination
		$perPage = (int) $request->get("perPage", 10);
		$currentPage = (int) $request->get("page", 1);
		$logs = array_reverse($logs); // Most recent first
		$total = count($logs);

		// Apply pagination slice (unless ALL is selected)
		if ($perPage !== 999999) {
			$offset = ($currentPage - 1) * $perPage;
			$logs = array_slice($logs, $offset, $perPage);
		}
		// If perPage is 999999 (ALL), show all logs without slicing

		return view("admin.logs.index", [
			"logs" => $logs,
			"logFiles" => $logFiles,
			"selectedFile" => $selectedFile,
			"fileInfo" => $fileInfo,
			"lastGlobalUpdate" => $lastGlobalUpdate,
			"total" => $total,
			"currentPage" => $currentPage,
			"perPage" => $perPage,
			"selectedLevel" => $level,
			"searchQuery" => $search,
			"request" => $request,
			"error" => null,
		]);
	}

	/**
	 * Get all log files from logs directory
	 */
	private function getLogFiles($logsPath)
	{
		$files = File::glob($logsPath . "/*.log");
		$logFiles = [];

		foreach ($files as $file) {
			$logFiles[] = [
				"name" => basename($file),
				"path" => $file,
				"size" => File::size($file),
				"modified" => File::lastModified($file),
			];
		}

		// Sort by modification date (most recent first)
		usort($logFiles, fn($a, $b) => $b["modified"] <=> $a["modified"]);

		return $logFiles;
	}

	/**
	 * Get file information
	 */
	private function getFileInfo($filePath)
	{
		return [
			"size" => $this->formatBytes(File::size($filePath)),
			"modified" => date("Y-m-d H:i:s", File::lastModified($filePath)),
			"lines" => count(file($filePath)),
		];
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
	 * Parse Laravel log file into structured array
	 */
	private function parseLogFile($content)
	{
		$logs = [];
		$pattern = '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.*?)(?=\[\d{4}-\d{2}-\d{2}|$)/s';

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
}
