<?php

namespace App\Http\Controllers\Admin\Helper\Core;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * CSV Backup Manager - Unified backup management for all import types
 * Handles database backups with PHP-based dump (no mysqldump required)
 */
class CsvBackupManager
{
	/**
	 * Create a table backup before import
	 *
	 * @param string $tableName Table name to backup
	 * @param string $type Backup type: 'backend', 'job', or 'seeder'
	 * @param bool $enabled Whether to create backup (default: true)
	 * @return string|null Backup filename or null if disabled
	 */
	public static function createBackup(string $tableName, string $type = "backend", bool $enabled = true): ?string
	{
		if (!$enabled) {
			return null;
		}

		// Validate backup type
		if (!in_array($type, ["backend", "job", "seeder"])) {
			throw new \InvalidArgumentException("Invalid backup type. Must be: backend, job, or seeder");
		}

		$backupDir = "csv-imports/{$type}/{$tableName}";
		if (!Storage::disk("backups")->exists($backupDir)) {
			Storage::disk("backups")->makeDirectory($backupDir);
		}

		$timestamp = now()->format("Y-m-d_His");
		$filename = "{$tableName}_{$timestamp}.sql";
		$path = Storage::disk("backups")->path($backupDir . "/" . $filename);

		// Create PHP-based backup
		self::createPhpBackup($tableName, $path);

		// Clean old backups (keep only last 2)
		self::cleanOldBackups($backupDir, $tableName, 2);

		return $filename;
	}

	/**
	 * Create PHP-based backup of a specific table (no mysqldump required)
	 *
	 * @param string $tableName Table name
	 * @param string $path Full path for backup file
	 * @return void
	 */
	private static function createPhpBackup(string $tableName, string $path): void
	{
		try {
			// Get database connection details
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
			$sql .= "-- Created by CsvBackupManager\n\n";
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
			// Fallback to simple backup if PDO fails
			self::createSimpleBackup($tableName, $path);
		}
	}

	/**
	 * Simple backup fallback using Eloquent
	 *
	 * @param string $tableName Table name
	 * @param string $path Full path for backup file
	 * @return void
	 */
	private static function createSimpleBackup(string $tableName, string $path): void
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
	 * Clean old backups keeping only specified number of most recent
	 *
	 * @param string $backupDir Backup directory path
	 * @param string $tableName Table name
	 * @param int $keepCount Number of backups to keep (default: 2)
	 * @return void
	 */
	private static function cleanOldBackups(string $backupDir, string $tableName, int $keepCount = 2): void
	{
		$backupFiles = Storage::disk("backups")->files($backupDir);
		$tableBackups = array_filter($backupFiles, function ($file) use ($tableName, $backupDir) {
			return strpos(basename($file), $tableName . "_") === 0;
		});

		if (count($tableBackups) > $keepCount) {
			// Sort by modification time and keep only the most recent
			$sortedBackups = collect($tableBackups)
				->sortBy(function ($file) {
					return Storage::disk("backups")->lastModified($file);
				})
				->reverse();

			// Delete old backups
			$sortedBackups->slice($keepCount)->each(function ($file) {
				Storage::disk("backups")->delete($file);
			});
		}
	}

	/**
	 * Get list of backups for a table
	 *
	 * @param string $tableName Table name
	 * @param string $type Backup type: 'backend', 'job', or 'seeder'
	 * @return array Array of backup files
	 */
	public static function getBackups(string $tableName, string $type = "backend"): array
	{
		$backupDir = "csv-imports/{$type}/{$tableName}";
		if (!Storage::disk("backups")->exists($backupDir)) {
			return [];
		}

		$backupFiles = Storage::disk("backups")->files($backupDir);
		return array_values(
			array_filter($backupFiles, function ($file) use ($tableName) {
				return strpos(basename($file), $tableName . "_") === 0;
			})
		);
	}
}
