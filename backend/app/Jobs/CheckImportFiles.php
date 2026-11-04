<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Support\Backpack\Log\LogHelper;
use Illuminate\Support\Facades\Log;

/**
 * Scans the import folder for new CSV files and dispatches ProcessImportFile jobs.
 *
 * ShouldBeUnique via cache('redis') ensures only one scan runs at a time.
 * The 'scan' supervisor in Horizon handles this queue with a single worker.
 */
class CheckImportFiles implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;

    public $uniqueFor = 50;

    public $tries = 1;

    public function __construct()
    {
        // Hash tag required for Redis Cluster slot consistency.
        // Must match the queue configured in supervisor-scan in config/horizon.php.
        $this->onQueue('{scan}');
    }

    public function uniqueId(): string
    {
        return 'import-scan';
    }

    public function uniqueVia()
    {
        // Uses the standalone 'cache' Redis connection — not the cluster 'queues'
        // connection — so ShouldBeUnique lock keys never cause CROSSSLOT errors.
        return cache()->store('redis');
    }

    public function handle(): void
    {
        $folder = database_path('import');

        if (! is_dir($folder)) {
            return;
        }

        if (! $this->hasValidCsvFiles($folder)) {
            return;
        }

        // Folder order and folder → model mapping: see config/import.php.
        $PRIORITY_ORDER = config('import.priority', []);
        $MODEL_OVERRIDES = config('import.model_overrides', []);

        $files = [];
        $dirIterator = new \RecursiveDirectoryIterator($folder, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dirIterator);

        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'csv') {
                continue;
            }

            $path = $file->getPath();
            $subfolder = basename($path);
            $unixPath = str_replace('\\', '/', $path);

            if (preg_match('~/(processed|pending|suspended)(/|$)~', $unixPath)) {
                continue;
            }

            $processedFolder = "{$path}/processed";
            $pendingFolder = "{$path}/pending";
            $filename = $file->getFilename();
            $isDuplicate = file_exists("{$processedFolder}/{$filename}")
                || file_exists("{$pendingFolder}/{$filename}");

            if ($isDuplicate) {
                $suspendedFolder = "{$path}/suspended";
                $suspendedPath = "{$suspendedFolder}/{$filename}";

                if (! is_dir($suspendedFolder)) {
                    @mkdir($suspendedFolder, 0755, true);
                }

                if (@rename($file->getRealPath(), $suspendedPath)) {
                    $reason = 'Nome file duplicato (già presente in processed o in coda)';
                    $reasonFile = $suspendedFolder.'/'.pathinfo($filename, PATHINFO_FILENAME).'.reason.txt';
                    @file_put_contents($reasonFile, $reason."\n".now()->toIso8601String());

                    $msg = "SECURITY: File {$subfolder}/{$filename} moved to suspended - duplicate filename detected";
                    Log::channel('import')->warning($msg);
                    LogHelper::channel($subfolder)->warning($msg);
                } else {
                    $msg = "ERROR: Cannot move duplicate file {$subfolder}/{$filename} to suspended";
                    Log::channel('import')->error($msg);
                    LogHelper::channel($subfolder)->error($msg);
                }
            } else {
                $files[] = [
                    'path' => $file->getRealPath(),
                    'subfolder' => $subfolder,
                    'priority' => $PRIORITY_ORDER[$subfolder] ?? 999,
                ];
            }
        }

        if (empty($files)) {
            return;
        }

        usort($files, fn ($a, $b) => $a['priority'] <=> $b['priority']);

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

            if (@rename($file['path'], $pendingPath) === false) {
                Log::channel('import')->error("Cannot move to pending: {$file['path']} -> {$pendingPath}");

                continue;
            }

            // Dispatch on the import queue — hash tag for cluster consistency.
            ProcessImportFile::dispatch($filename, $subfolder, $modelName)->onQueue('{import}');

            Log::channel('import')->info("Queued {$subfolder}/{$filename}");
        }
    }

    private function hasValidCsvFiles(string $root): bool
    {
        $subfolders = glob($root.'/*', GLOB_ONLYDIR);

        if (empty($subfolders)) {
            return false;
        }

        foreach ($subfolders as $subfolder) {
            if (in_array(basename($subfolder), ['processed', 'pending', 'suspended'], true)) {
                continue;
            }

            if (! empty(glob($subfolder.'/*.csv'))) {
                return true;
            }
        }

        return false;
    }
}
