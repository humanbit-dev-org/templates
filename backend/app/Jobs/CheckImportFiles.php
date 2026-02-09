<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Check Import Files Job
 * Checks incoming folder and dispatches ProcessImportFile jobs for valid CSV files
 * Replaces the CheckImportFiles Command
 */
class CheckImportFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Short timeout since this job only checks and dispatches
    public $timeout = 60;

    // Single try is enough for file checking
    public $tries = 1;

    public function handle(): void
    {
        $folder = database_path('import');

        // If folder doesn't exist, skip execution
        if (! is_dir($folder)) {
            return;
        }

        // Quick check: if no valid CSV files found, exit early
        if (! $this->hasValidCsvFiles($folder)) {
            return;
        }

        $PRIORITY_ORDER = [
            'filiale' => 1,
            'anagrafica' => 2,
            'delibera' => 3,
            'tiraggio' => 4,
            'mensile' => 5,
            'trattenuta' => 6,
        ];

        $MODEL_OVERRIDES = [
            'anagrafica' => 'User',
        ];

        $files = [];
        $dirIterator = new \RecursiveDirectoryIterator($folder, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dirIterator);

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'csv') {
                $path = $file->getPath();
                $subfolder = basename($path);

                // Skip if already inside processed, pending or suspended
                $unixPath = str_replace('\\', '/', $path);
                if (preg_match('~/(processed|pending|suspended)(/|$)~', $unixPath)) {
                    continue;
                }

                $processedFolder = "{$path}/processed";
                $pendingFolder = "{$path}/pending";

                // Check if same file already exists in processed or pending
                $filename = $file->getFilename();
                $isDuplicate = file_exists("{$processedFolder}/{$filename}") || file_exists("{$pendingFolder}/{$filename}");

                if ($isDuplicate) {
                    // Duplicate file: move to suspended for security reasons
                    $suspendedFolder = "{$path}/suspended";
                    $suspendedPath = "{$suspendedFolder}/{$filename}";

                    // Create suspended folder if it doesn't exist
                    if (! is_dir($suspendedFolder)) {
                        @mkdir($suspendedFolder, 0755, true);
                    }

                    // Move file to suspended
                    if (@rename($file->getRealPath(), $suspendedPath)) {
                        $msg = "SECURITY: File {$subfolder}/{$filename} moved to suspended - duplicate filename detected";
                        Log::channel('import')->warning($msg);
                        Log::channel($subfolder)->warning($msg);
                    } else {
                        $msg = "ERROR: Cannot move duplicate file {$subfolder}/{$filename} to suspended";
                        Log::channel('import')->error($msg);
                        Log::channel($subfolder)->error($msg);
                    }
                } else {
                    $files[] = [
                        'path' => $file->getRealPath(),
                        'subfolder' => $subfolder,
                        'priority' => $PRIORITY_ORDER[$subfolder] ?? 999,
                    ];
                }
            }
        }

        if (empty($files)) {
            return;
        }

        // Sort by priority
        usort($files, function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });

        foreach ($files as $file) {
            $subfolder = $file['subfolder'];
            $modelName = $MODEL_OVERRIDES[$subfolder] ?? ucfirst($subfolder);
            $dir = dirname($file['path']);
            $filename = basename($file['path']);
            $pendingDir = "{$dir}/pending";
            $pendingPath = "{$pendingDir}/{$filename}";

            if (! is_dir($pendingDir)) {
                @mkdir($pendingDir, 0755, true);
            }

            // Move immediately to pending (so it won't be rescheduled next second)
            if (@rename($file['path'], $pendingPath) === false) {
                $msg = "Cannot move to pending: {$file['path']} -> {$pendingPath}";
                Log::channel('import')->error($msg);

                continue;
            }

            // Dispatch the job (on 'import' queue)
            ProcessImportFile::dispatch($filename, $subfolder, $modelName)->onQueue('import');

            Log::channel('import')->info("Queued {$subfolder}/{$filename}");
        }
    }

    /**
     * Check if there are any valid CSV files to process
     * Skips files in processed/pending/suspended folders
     * Optimized using glob() for faster directory scanning
     */
    private function hasValidCsvFiles(string $root): bool
    {
        // Get all subfolders (skip processed, pending, suspended)
        $subfolders = glob($root.'/*', GLOB_ONLYDIR);
        if (empty($subfolders)) {
            return false;
        }

        foreach ($subfolders as $subfolder) {
            $subfolderName = basename($subfolder);

            // Skip processed, pending, suspended folders
            if (in_array($subfolderName, ['processed', 'pending', 'suspended'], true)) {
                continue;
            }

            // Check for CSV files directly in subfolder (not in processed/pending/suspended)
            $csvFiles = glob($subfolder.'/*.csv');
            if (! empty($csvFiles)) {
                return true; // Found at least one valid CSV
            }
        }

        return false;
    }
}
