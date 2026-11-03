<?php

namespace App\Logging;

use App\Services\Logging\LogEncryptionService;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\LogRecord;

class EncryptedRotatingFileHandler extends RotatingFileHandler
{
    protected $encryptionService;

    public function __construct(
        ?string $filename = null,
        int|string $maxFiles = 0,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
        ?int $filePermission = null,
        bool $useLocking = false,
        // Support Laravel daily driver parameters for consistency
        ?string $path = null,
        ?int $days = null
    ) {
        // Support both 'filename' (Monolog) and 'path' (Laravel daily) parameter names
        $filePath = $filename ?? $path;
        if (! $filePath) {
            throw new \InvalidArgumentException("Either 'filename' or 'path' must be provided");
        }

        // Support both 'maxFiles' (Monolog) and 'days' (Laravel daily) parameter names
        $maxFilesToUse = $days !== null ? $days : (is_int($maxFiles) ? $maxFiles : 0);

        // Convert string level to Level enum or int (Laravel passes level as string like 'info', 'debug', etc.)
        $levelValue = is_string($level) ? $this->parseLevel($level) : ($level instanceof Level ? $level->value : $level);

        parent::__construct($filePath, $maxFilesToUse, $levelValue, $bubble, $filePermission, $useLocking);
        $this->encryptionService = app(LogEncryptionService::class);
    }

    /**
     * Convert string level name to Monolog level integer
     */
    private function parseLevel(string $level): int
    {
        $level = strtoupper($level);

        return match ($level) {
            'DEBUG' => Level::Debug->value,
            'INFO' => Level::Info->value,
            'NOTICE' => Level::Notice->value,
            'WARNING' => Level::Warning->value,
            'ERROR' => Level::Error->value,
            'CRITICAL' => Level::Critical->value,
            'ALERT' => Level::Alert->value,
            'EMERGENCY' => Level::Emergency->value,
            default => Level::Debug->value,
        };
    }

    /**
     * Append one encrypted log line (constant memory; no full-file re-encryption).
     */
    protected function write(LogRecord|array $record): void
    {
        if ($record instanceof LogRecord) {
            $formatted = $record->formatted;
        } else {
            $formatted = $record['formatted'];
        }

        $this->rotate();

        $filePath = $this->getTimedFilename();
        $encryptedLine = $this->encryptionService->encrypt(rtrim((string) $formatted, "\n"));

        $dir = dirname($filePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($filePath, $encryptedLine."\n", FILE_APPEND | LOCK_EX);
    }
}
