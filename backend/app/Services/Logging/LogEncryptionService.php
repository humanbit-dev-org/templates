<?php

namespace App\Services\Logging;

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
		$trimmed = trim($content);
		if ($trimmed === "" || preg_match("/^\[(\d{4}-\d{2}-\d{2})/", $trimmed)) {
			return false; // Plaintext log format
		}

		// Laravel encrypted payloads usually start with a base64-encoded JSON:
		// {"iv":"...","value":"...","mac":"..."}
		// Prefix in base64 is typically "eyJpdiI6".
		if (str_starts_with($trimmed, "eyJpdiI6")) {
			return true;
		}

		// Fallback for full payloads: decode base64 and ensure decoded JSON shape.
		$decoded = base64_decode($trimmed, true);
		if ($decoded === false) {
			return false;
		}

		$json = json_decode($decoded, true);
		return is_array($json) && isset($json["iv"], $json["value"], $json["mac"]);
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
	 * Read and decrypt file content.
	 *
	 * Supports legacy single-blob files and line-delimited encrypted entries.
	 */
	public function readAndDecrypt(string $filePath): string
	{
		$content = File::get($filePath);

		if ($content === "") {
			return "";
		}

		// Legacy format: entire file is one encrypted payload.
		if ($this->isEncrypted($content) && !str_contains($content, "\n")) {
			return $this->decrypt($content);
		}

		$plaintext = "";

		foreach (preg_split("/\r\n|\n|\r/", $content) as $line) {
			$line = trim($line);
			if ($line === "") {
				continue;
			}

			if ($this->isEncrypted($line)) {
				$plaintext .= $this->decrypt($line) . "\n";

				continue;
			}

			$plaintext .= $line . "\n";
		}

		return $plaintext;
	}
}
