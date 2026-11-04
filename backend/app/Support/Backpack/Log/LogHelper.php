<?php

namespace App\Support\Backpack\Log;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * Log Helper
 * Manages log directory creation and rotation for import logs
 */
class LogHelper
{
    /**
     * Ensure log directory exists for a specific import type
     * Creates directory if it doesn't exist
     *
     * @param  string  $subfolder  Subfolder name (one per imported model, e.g. user, article)
     */
    public static function ensureLogDirectory(string $subfolder): void
    {
        $logDir = database_path("import/{$subfolder}");

        if (! file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * Rotate log files keeping only the last N files
     * Deletes older log files based on modification time
     *
     * @param  string  $subfolder  Subfolder name (one per imported model, e.g. user, article)
     * @param  int  $keepLast  Number of log files to keep (default: 3)
     * @return int Number of files deleted
     */
    public static function rotateLogs(string $subfolder, int $keepLast = 3): int
    {
        $logDir = database_path("import/{$subfolder}");

        if (! is_dir($logDir)) {
            return 0;
        }

        // Get all .log files in the directory
        $logFiles = glob("{$logDir}/import_*.log");

        if (empty($logFiles)) {
            return 0;
        }

        // Sort by modification time (newest first)
        usort($logFiles, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Keep only the last N files, delete the rest
        $filesToDelete = array_slice($logFiles, $keepLast);
        $deletedCount = 0;

        foreach ($filesToDelete as $file) {
            if (unlink($file)) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * Prepare log for import: ensure directory exists and rotate old logs
     * Combines ensureLogDirectory and rotateLogs
     *
     * @param  string  $subfolder  Subfolder name (one per imported model, e.g. user, article)
     * @param  int  $keepLast  Number of log files to keep (default: 3)
     */
    public static function prepareImportLog(string $subfolder, int $keepLast = 3): void
    {
        self::ensureLogDirectory($subfolder);
        self::rotateLogs($subfolder, $keepLast);
    }

    /**
     * Logger for an import folder.
     *
     * A folder gets its own file when config/logging.php declares a channel
     * named after it (e.g. 'user' writing to database/import/user/import.log);
     * every other folder writes to the shared 'import' channel.
     *
     * @param  string  $subfolder  Subfolder name (one per imported model, e.g. user, article)
     */
    public static function channel(string $subfolder): LoggerInterface
    {
        return Log::channel(config("logging.channels.{$subfolder}") !== null ? $subfolder : 'import');
    }
}
