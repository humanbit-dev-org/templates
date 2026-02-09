<?php

namespace App\Logging;

use Monolog\Handler\RotatingFileHandler;
use Monolog\LogRecord;
use Monolog\Level;
use App\Services\LogEncryptionService;

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
		if (!$filePath) {
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
			"DEBUG" => Level::Debug->value,
			"INFO" => Level::Info->value,
			"NOTICE" => Level::Notice->value,
			"WARNING" => Level::Warning->value,
			"ERROR" => Level::Error->value,
			"CRITICAL" => Level::Critical->value,
			"ALERT" => Level::Alert->value,
			"EMERGENCY" => Level::Emergency->value,
			default => Level::Debug->value,
		};
	}

	/**
	 * Write encrypted log entry with rotation support
	 */
	protected function write(LogRecord|array $record): void
	{
		// Handle both old array format and new LogRecord format
		if ($record instanceof LogRecord) {
			$formatted = $record->formatted;
		} else {
			$formatted = $record["formatted"];
		}

		// Rotate if needed (parent handles rotation logic)
		$this->rotate();

		// Get current file path after rotation using parent's method
		// RotatingFileHandler creates files like: filename-YYYY-MM-DD.log
		$filePath = $this->getTimedFilename();

		// Read existing content and decrypt if needed
		$existingContent = "";
		if (file_exists($filePath) && filesize($filePath) > 0) {
			$existingContent = file_get_contents($filePath);
			if ($this->encryptionService->isEncrypted($existingContent)) {
				try {
					$existingContent = $this->encryptionService->decrypt($existingContent);
				} catch (\Exception $e) {
					$existingContent = "";
				}
			}
		}

		// Append new log line
		$newContent = $existingContent . $formatted;

		// Encrypt and save
		$this->encryptionService->encryptAndSave($filePath, $newContent);
	}
}
