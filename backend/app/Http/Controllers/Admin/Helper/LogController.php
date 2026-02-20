<?php

namespace App\Http\Controllers\Admin\Helper;

use App\Http\Controllers\Controller;
use App\Services\LogEncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogController extends Controller
{
    protected $encryptionService;

    public function __construct(LogEncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }

    public function export(Request $request)
    {
        $logsPath = storage_path('logs');
        $fileName = $request->get('file', 'laravel.log');
        $levelFilter = $request->get('level');
        $searchFilter = $request->get('search');

        // Security check: prevent directory traversal
        $filePath = $logsPath.'/'.$fileName;
        $realLogsPath = realpath($logsPath);
        $realFilePath = realpath($filePath);

        if (! File::exists($filePath) || ! $realFilePath || strpos($realFilePath, $realLogsPath) !== 0) {
            abort(404, 'Log file not found');
        }

        // Check file size before reading
        $fileSize = File::size($filePath);
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        $absoluteMaxSize = 100 * 1024 * 1024; // 100MB - absolute maximum
        $maxLines = 50000; // Maximum lines to read for large files

        if ($fileSize > $absoluteMaxSize) {
            abort(413, 'File too large ('.$this->formatBytes($fileSize).'). Maximum size: '.$this->formatBytes($absoluteMaxSize).'. Please archive or rotate this log file.');
        }

        // Read and decrypt file (optimized for large files)
        if ($fileSize > $maxFileSize) {
            $logContent = $this->readLastLines($filePath, $maxLines);
            // Add warning header for truncated exports
            if (! $levelFilter && ! $searchFilter) {
                $logContent = '[WARNING] File truncated to last '.number_format($maxLines).' lines due to size ('.$this->formatBytes($fileSize).")\n\n".$logContent;
            }
        } else {
            $logContent = $this->encryptionService->readAndDecrypt($filePath);
        }

        // Check file type
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $isJson = $fileExtension === 'json';

        // If no filters, return the entire file (decrypted)
        if (! $levelFilter && ! $searchFilter) {
            return response($logContent, 200, [
                'Content-Type' => $isJson ? 'application/json' : 'text/plain',
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            ]);
        }

        // Parse logs to apply filters
        if ($isJson) {
            $logs = $this->parseJsonFile($logContent, $fileName);
        } else {
            $logs = $this->parseLogFile($logContent);
        }

        // Apply level filter
        if ($levelFilter) {
            $logs = array_filter($logs, fn ($log) => $log['level'] === strtoupper($levelFilter));
        }

        // Apply search filter
        if ($searchFilter) {
            $logs = array_filter($logs, function ($log) use ($searchFilter) {
                return stripos($log['message'], $searchFilter) !== false ||
                    stripos($log['timestamp'], $searchFilter) !== false;
            });
        }

        // Reconstruct log content
        $exportContent = '';
        foreach ($logs as $log) {
            $exportContent .= "[{$log['timestamp']}] {$log['environment']}.{$log['level']}: {$log['message']}\n";
        }

        // Create filtered filename
        $filenameParts = [pathinfo($fileName, PATHINFO_FILENAME)];
        if ($levelFilter) {
            $filenameParts[] = strtolower($levelFilter);
        }
        if ($searchFilter) {
            $filenameParts[] = 'filtered';
        }
        $exportFileName = implode('_', $filenameParts).'.log';

        // Return filtered content as download
        return response($exportContent, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="'.$exportFileName.'"',
        ]);
    }

    public function index(Request $request)
    {
        $logsPath = storage_path('logs');

        // Get all log files
        $logFiles = $this->getLogFiles($logsPath);

        if (empty($logFiles)) {
            return view('admin.logs.index', [
                'logs' => [],
                'logFiles' => [],
                'selectedFile' => null,
                'fileInfo' => null,
                'lastGlobalUpdate' => null,
                'total' => 0,
                'currentPage' => 1,
                'perPage' => 10,
                'selectedLevel' => null,
                'searchQuery' => null,
                'dateFrom' => null,
                'dateTo' => null,
                'includeSeconds' => false,
                'request' => $request,
                'error' => 'No log files found',
            ]);
        }

        // Get selected file or default to most recent
        $selectedFile = $request->get('file', $logFiles[0]['name']);
        $logPath = $logsPath.'/'.$selectedFile;

        // Security check: prevent directory traversal
        $realLogsPath = realpath($logsPath);
        $realFilePath = realpath($logPath);
        if (! File::exists($logPath) || ! $realFilePath || strpos($realFilePath, $realLogsPath) !== 0) {
            return view('admin.logs.index', [
                'logs' => [],
                'logFiles' => $logFiles,
                'selectedFile' => null,
                'fileInfo' => null,
                'lastGlobalUpdate' => ! empty($logFiles) ? $logFiles[0]['modified'] : null,
                'total' => 0,
                'currentPage' => 1,
                'perPage' => 10,
                'selectedLevel' => null,
                'searchQuery' => null,
                'dateFrom' => null,
                'dateTo' => null,
                'includeSeconds' => false,
                'request' => $request,
                'error' => 'Invalid log file selected',
            ]);
        }

        // Get file info
        $fileInfo = $this->getFileInfo($logPath);

        // Get last global update (most recent log file)
        $lastGlobalUpdate = ! empty($logFiles) ? $logFiles[0]['modified'] : null;

        // Read and parse log file (decrypt if needed)
        // For large files (>10MB), read only last lines to avoid memory exhaustion
        $fileSize = File::size($logPath);
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        $absoluteMaxSize = 100 * 1024 * 1024; // 100MB - absolute maximum
        $maxLines = 50000; // Maximum lines to read for large files

        if ($fileSize > $absoluteMaxSize) {
            // File too large even with optimizations
            return view('admin.logs.index', [
                'logs' => [],
                'logFiles' => $logFiles,
                'selectedFile' => $selectedFile,
                'fileInfo' => $fileInfo,
                'lastGlobalUpdate' => $lastGlobalUpdate,
                'total' => 0,
                'currentPage' => 1,
                'perPage' => 10,
                'selectedLevel' => null,
                'searchQuery' => null,
                'dateFrom' => null,
                'dateTo' => null,
                'includeSeconds' => false,
                'request' => $request,
                'error' => 'File too large ('.$this->formatBytes($fileSize).'). Maximum size: '.$this->formatBytes($absoluteMaxSize).'. Please archive or rotate this log file.',
            ]);
        }

        if ($fileSize > $maxFileSize) {
            $logContent = $this->readLastLines($logPath, $maxLines);
        } else {
            $logContent = $this->encryptionService->readAndDecrypt($logPath);
        }

        // Check file type
        $fileExtension = pathinfo($selectedFile, PATHINFO_EXTENSION);
        $isJson = $fileExtension === 'json';

        if ($isJson) {
            $logs = $this->parseJsonFile($logContent, $selectedFile);
        } else {
            $logs = $this->parseLogFile($logContent);
        }

        // Apply filters
        $level = $request->get('level');
        $search = $request->get('search');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $includeSeconds = $request->get('include_seconds', false);

        if ($level) {
            $logs = array_filter($logs, fn ($log) => $log['level'] === $level);
        }

        if ($search) {
            $logs = array_filter($logs, function ($log) use ($search) {
                return stripos($log['message'], $search) !== false || stripos($log['timestamp'], $search) !== false;
            });
        }

        // Date range filter
        if ($dateFrom || $dateTo) {
            $logs = array_filter($logs, function ($log) use ($dateFrom, $dateTo, $includeSeconds) {
                $logTimestamp = strtotime($log['timestamp']);

                // Normalize timestamp format based on include_seconds
                if (! $includeSeconds) {
                    // Remove seconds for comparison (set to 00)
                    $logTimestamp = strtotime(date('Y-m-d H:i:00', $logTimestamp));
                }

                // Parse date inputs (datetime-local format: YYYY-MM-DDTHH:mm or YYYY-MM-DDTHH:mm:ss)
                $fromTimestamp = null;
                if ($dateFrom) {
                    // Convert datetime-local format (T separator) to standard format
                    $dateFromNormalized = str_replace('T', ' ', $dateFrom);
                    if (! $includeSeconds && strlen($dateFromNormalized) === 16) {
                        // Add :00 seconds if not included
                        $dateFromNormalized .= ':00';
                    }
                    $fromTimestamp = strtotime($dateFromNormalized);
                }

                $toTimestamp = null;
                if ($dateTo) {
                    // Convert datetime-local format (T separator) to standard format
                    $dateToNormalized = str_replace('T', ' ', $dateTo);
                    if (! $includeSeconds && strlen($dateToNormalized) === 16) {
                        // Add :59:00 for end of minute
                        $dateToNormalized .= ':59:00';
                    } elseif ($includeSeconds && strlen($dateToNormalized) === 19) {
                        // Ensure seconds are set to 59 if include_seconds is true
                        $dateToNormalized = substr($dateToNormalized, 0, 17).'59';
                    } elseif (! $includeSeconds && strlen($dateToNormalized) === 19) {
                        // Set to end of minute
                        $dateToNormalized = substr($dateToNormalized, 0, 16).':59:00';
                    }
                    $toTimestamp = strtotime($dateToNormalized);
                }

                $matches = true;
                if ($fromTimestamp !== null && $logTimestamp < $fromTimestamp) {
                    $matches = false;
                }
                if ($toTimestamp !== null && $logTimestamp > $toTimestamp) {
                    $matches = false;
                }

                return $matches;
            });
        }

        // Pagination
        $perPage = (int) $request->get('perPage', 10);
        $currentPage = (int) $request->get('page', 1);
        $logs = array_reverse($logs); // Most recent first
        $total = count($logs);

        // Apply pagination slice (unless ALL is selected)
        if ($perPage !== 999999) {
            $offset = ($currentPage - 1) * $perPage;
            $logs = array_slice($logs, $offset, $perPage);
        }
        // If perPage is 999999 (ALL), show all logs without slicing

        return view('admin.logs.index', [
            'logs' => $logs,
            'logFiles' => $logFiles,
            'selectedFile' => $selectedFile,
            'fileInfo' => $fileInfo,
            'lastGlobalUpdate' => $lastGlobalUpdate,
            'total' => $total,
            'currentPage' => $currentPage,
            'perPage' => $perPage,
            'selectedLevel' => $level,
            'searchQuery' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'includeSeconds' => $includeSeconds,
            'request' => $request,
            'error' => null,
            'isJson' => $isJson ?? false,
        ]);
    }

    /**
     * Get all log files from logs directory (recursive, includes .log and .json)
     */
    private function getLogFiles($logsPath)
    {
        $logFiles = [];

        // Recursive scan of all files under storage/logs
        $allFiles = File::allFiles($logsPath);

        foreach ($allFiles as $file) {
            /** @var \SplFileInfo $file */
            $ext = strtolower($file->getExtension());
            if (! in_array($ext, ['log', 'json'])) {
                continue;
            }

            $fullPath = $file->getRealPath();
            $relativePath = ltrim(str_replace($logsPath, '', $fullPath), DIRECTORY_SEPARATOR);

            $logFiles[] = [
                'name' => $relativePath, // Include subdirectory path
                'path' => $fullPath,
                'size' => $file->getSize(),
                'modified' => $file->getMTime(),
                'type' => $ext,
            ];
        }

        // Sort by modification date (most recent first)
        usort($logFiles, fn ($a, $b) => $b['modified'] <=> $a['modified']);

        return $logFiles;
    }

    /**
     * Get file information
     */
    private function getFileInfo($filePath)
    {
        $fileSize = File::size($filePath);
        $maxFileSize = 10 * 1024 * 1024; // 10MB

        // For large files, estimate lines instead of counting all
        $lines = 0;
        if ($fileSize > $maxFileSize) {
            // Estimate: average log line is ~200 bytes
            $lines = (int) ($fileSize / 200);
        } else {
            $lines = $this->countLines($filePath);
        }

        return [
            'size' => $this->formatBytes($fileSize),
            'modified' => date('Y-m-d H:i:s', File::lastModified($filePath)),
            'lines' => $lines,
        ];
    }

    /**
     * Count lines in file efficiently
     */
    private function countLines($filePath): int
    {
        $count = 0;
        $handle = fopen($filePath, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $count++;
            }
            fclose($handle);
        }

        return $count;
    }

    /**
     * Read last N lines from a log file (handles encrypted files)
     */
    private function readLastLines($filePath, $maxLines = 50000): string
    {
        $fileSize = File::size($filePath);
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        $readChunkSize = 50 * 1024 * 1024; // Read last 50MB max

        // Read a sample to check if encrypted
        $sampleSize = min(1024, $fileSize); // Read first 1KB
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return '[ERROR] Could not open file';
        }

        $sample = fread($handle, $sampleSize);
        fclose($handle);

        // Check if file is encrypted
        if ($this->encryptionService->isEncrypted($sample)) {
            // For encrypted files, we need to decrypt the entire file
            // But this is memory-intensive for large files
            // Increase memory limit temporarily for this operation
            $originalMemoryLimit = ini_get('memory_limit');
            $newMemoryLimit = max($originalMemoryLimit, '256M');
            ini_set('memory_limit', $newMemoryLimit);

            try {
                $decryptedContent = $this->encryptionService->readAndDecrypt($filePath);
                // Extract last N lines
                $lines = explode("\n", $decryptedContent);
                $lastLines = array_slice($lines, -$maxLines);
                ini_set('memory_limit', $originalMemoryLimit);

                return implode("\n", $lastLines);
            } catch (\Exception $e) {
                ini_set('memory_limit', $originalMemoryLimit);

                return '[ERROR] Could not decrypt large file: '.$e->getMessage().
                    "\n[INFO] File size: ".$this->formatBytes($fileSize).
                    "\n[INFO] Consider rotating or archiving this log file.";
            }
        }

        // For non-encrypted files, read only last portion efficiently
        $chunkSize = min($readChunkSize, $fileSize);
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return '[ERROR] Could not open file';
        }

        // Seek to position before last chunk
        fseek($handle, -$chunkSize, SEEK_END);
        $content = fread($handle, $chunkSize);
        fclose($handle);

        // Extract last N lines
        $lines = explode("\n", $content);
        // Remove first line as it might be incomplete
        if (count($lines) > 1) {
            array_shift($lines);
        }
        $lastLines = array_slice($lines, -$maxLines);

        return implode("\n", $lastLines);
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision).' '.$units[$pow];
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
                'timestamp' => $match[1] ?? '',
                'environment' => $match[2] ?? '',
                'level' => strtoupper($match[3] ?? ''),
                'message' => trim($match[4] ?? ''),
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
            $startsNewGroup = stripos($log['message'], 'JWT:') === 0;

            // If JWT marker found, finalize previous group and start new one
            if ($startsNewGroup) {
                // Save previous group if exists
                if ($currentGroup !== null) {
                    $grouped[] = $currentGroup;
                }

                // Start new JWT group
                $currentGroup = $log;
                $currentGroup['grouped'] = false;
                $currentGroup['jwt_base_timestamp'] = $log['timestamp'];
                $isInJwtGroup = true;
            } elseif ($isInJwtGroup && $currentGroup !== null) {
                // We're in a JWT group - check if log is within time window (60 seconds)
                $baseTime = strtotime($currentGroup['jwt_base_timestamp']);
                $logTime = strtotime($log['timestamp']);
                $timeDiff = abs($logTime - $baseTime);

                // Group if within 60 seconds and same level/environment
                if (
                    $timeDiff <= 60 &&
                    $currentGroup['level'] === $log['level'] &&
                    $currentGroup['environment'] === $log['environment']
                ) {
                    // Append message to current JWT group
                    $currentGroup['message'] .= "\n".$log['message'];
                    $currentGroup['grouped'] = true;
                } else {
                    // Outside time window or different level - close JWT group and start new
                    $grouped[] = $currentGroup;
                    $currentGroup = $log;
                    $currentGroup['grouped'] = false;
                    $isInJwtGroup = false;
                }
            } elseif (
                $currentGroup !== null &&
                $currentGroup['timestamp'] === $log['timestamp'] &&
                $currentGroup['level'] === $log['level'] &&
                $currentGroup['environment'] === $log['environment']
            ) {
                // Non-JWT group: same timestamp/level/environment
                $currentGroup['message'] .= "\n".$log['message'];
                $currentGroup['grouped'] = true;
            } else {
                // Different attributes - save previous and start new
                if ($currentGroup !== null) {
                    $grouped[] = $currentGroup;
                }

                // Start new group (non-JWT)
                $currentGroup = $log;
                $currentGroup['grouped'] = false;
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
                return [
                    [
                        'timestamp' => date('Y-m-d H:i:s', File::lastModified(storage_path('logs/'.$fileName))),
                        'environment' => 'local',
                        'level' => 'INFO',
                        'message' => $content,
                        'is_json' => false,
                    ],
                ];
            }

            // Handle different JSON structures
            if (isset($data['timestamp'])) {
                // Single JSON object
                $logs[] = [
                    'timestamp' => $data['timestamp'] ?? date('Y-m-d H:i:s'),
                    'environment' => $data['environment'] ?? 'local',
                    'level' => strtoupper($data['level'] ?? 'INFO'),
                    'message' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    'is_json' => true,
                    'json_data' => $data,
                ];
            } elseif (is_array($data) && isset($data[0])) {
                // Array of JSON objects
                foreach ($data as $index => $item) {
                    if (is_array($item)) {
                        $logs[] = [
                            'timestamp' => $item['timestamp'] ?? ($item['created_at'] ?? date('Y-m-d H:i:s')),
                            'environment' => $item['environment'] ?? 'local',
                            'level' => strtoupper($item['level'] ?? 'INFO'),
                            'message' => json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                            'is_json' => true,
                            'json_data' => $item,
                        ];
                    }
                }
            } else {
                // Generic JSON structure
                $logs[] = [
                    'timestamp' => date('Y-m-d H:i:s', File::lastModified(storage_path('logs/'.$fileName))),
                    'environment' => 'local',
                    'level' => 'INFO',
                    'message' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    'is_json' => true,
                    'json_data' => $data,
                ];
            }
        } catch (\Exception $e) {
            // If parsing fails, return as single entry
            $logs[] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'environment' => 'local',
                'level' => 'ERROR',
                'message' => 'Failed to parse JSON: '.$e->getMessage()."\n\n".$content,
                'is_json' => false,
            ];
        }

        return $logs;
    }
}
