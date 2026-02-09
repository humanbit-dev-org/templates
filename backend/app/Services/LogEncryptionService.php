<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class LogEncryptionService
{
	/**
	 * Encrypt log content
	 *
	 * @param string $content Plain text content
	 * @return string Encrypted content
	 */
	public function encrypt(string $content): string
	{
		try {
			return Crypt::encryptString($content);
		} catch (\Exception $e) {
			Log::error("Failed to encrypt log: " . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Decrypt log content
	 *
	 * @param string $encryptedContent Encrypted content
	 * @return string Decrypted content
	 */
	public function decrypt(string $encryptedContent): string
	{
		try {
			return Crypt::decryptString($encryptedContent);
		} catch (\Exception $e) {
			// If decryption fails, might be plain text (backward compatibility)
			Log::warning("Failed to decrypt log, might be plain text: " . $e->getMessage());
			return $encryptedContent;
		}
	}

	/**
	 * Check if content is encrypted (has encryption marker)
	 *
	 * @param string $content Content to check
	 * @return bool True if encrypted
	 */
	public function isEncrypted(string $content): bool
	{
		// Laravel Crypt adds base64 encoding, check for typical encrypted pattern
		// Encrypted strings are base64 and start with eyJ (JSON encrypted payload)
		$trimmed = trim($content);
		return !empty($trimmed) &&
			base64_decode($trimmed, true) !== false &&
			strlen($trimmed) > 50 &&
			!preg_match("/^\[(\d{4}-\d{2}-\d{2})/", $trimmed); // Not a log line starting with timestamp
	}

	/**
	 * Encrypt file content and save
	 *
	 * @param string $filePath File path
	 * @param string $content Plain text content
	 * @return bool Success
	 */
	public function encryptAndSave(string $filePath, string $content): bool
	{
		try {
			$encrypted = $this->encrypt($content);
			File::put($filePath, $encrypted);
			return true;
		} catch (\Exception $e) {
			Log::error("Failed to encrypt and save file {$filePath}: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Read and decrypt file content
	 *
	 * @param string $filePath File path
	 * @return string Decrypted content
	 */
	public function readAndDecrypt(string $filePath): string
	{
		$content = File::get($filePath);

		// Try to decrypt if encrypted
		if ($this->isEncrypted($content)) {
			return $this->decrypt($content);
		}

		// Return as-is if not encrypted (backward compatibility)
		return $content;
	}
}
