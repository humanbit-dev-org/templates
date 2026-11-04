<?php

namespace App\Services\Encryption;

use Illuminate\Support\Facades\Log;

class GpgEncryptionService
{
	/**
	 * Encrypt content using GPG public key.
	 *
	 * @param  string  $content  Plain text content to encrypt
	 * @param  string  $publicKey  GPG public key
	 * @param  string|null  $passphrase  Optional passphrase for the key
	 * @return string Encrypted content
	 *
	 * @throws \Exception
	 */
	public function encrypt(string $content, string $publicKey, ?string $passphrase = null): string
	{
		if (!extension_loaded("gnupg")) {
			throw new \Exception("GPG extension (gnupg) is not loaded. Please install php-gnupg extension.");
		}

		// GNUPGHOME must exist with 0700 permissions and be set BEFORE the
		// gnupg context is created: gpgme captures the home dir at construction,
		// so a later putenv() has no effect and the key import silently fails.
		$homeDir = sys_get_temp_dir() . "/gnupg-" . posix_geteuid();

		if (!is_dir($homeDir) && !mkdir($homeDir, 0700, true) && !is_dir($homeDir)) {
			throw new \Exception("Unable to create GNUPGHOME directory: {$homeDir}");
		}

		putenv("GNUPGHOME=" . $homeDir);

		$gnupg = new \gnupg(["home_dir" => $homeDir]);

		// Set error mode to exception for better error handling
		$gnupg->seterrormode(\gnupg::ERROR_EXCEPTION);

		$publicKey = $this->normalizeArmoredKey($publicKey);

		try {
			// Import the public key
			$importResult = $gnupg->import($publicKey);

			if (!$importResult) {
				// Non-sensitive diagnostics: armored public key structure only.
				$lines = explode("\n", trim($publicKey));
				Log::error("[gpg-encryption] Key import returned no result", [
					"gpgme_error" => $gnupg->geterror() ?: null,
					"key_lines" => count($lines),
					"key_first_line" => $lines[0] ?? null,
					"key_last_line" => $lines[count($lines) - 1] ?? null,
				]);

				throw new \Exception("Failed to import GPG public key: " . ($gnupg->geterror() ?: "unknown gpgme error"));
			}

			// Get the fingerprint of the imported key
			$fingerprint = $importResult["fingerprint"] ?? null;

			if (!$fingerprint) {
				throw new \Exception("Could not retrieve GPG key fingerprint");
			}

			// Encrypt the content using the imported key: the recipient is
			// registered via addencryptkey(); encrypt() only takes plaintext.
			if (!$gnupg->addencryptkey($fingerprint)) {
				throw new \Exception("Failed to add GPG encryption key: " . ($gnupg->geterror() ?: "unknown gpgme error"));
			}

			$encrypted = $gnupg->encrypt($content);

			if (!$encrypted) {
				throw new \Exception("GPG encryption failed");
			}

			Log::info("[gpg-encryption] Content encrypted successfully", [
				"fingerprint" => $fingerprint,
			]);

			return $encrypted;
		} catch (\Exception $e) {
			Log::error("[gpg-encryption] Encryption failed", [
				"error" => $e->getMessage(),
			]);
			throw $e;
		}
	}

	/**
	 * Encrypt content using GPG public key from environment variable.
	 *
	 * @param  string  $content  Plain text content to encrypt
	 * @return string Encrypted content
	 *
	 * @throws \Exception
	 */
	public function encryptWithKeyFromEnv(string $content): string
	{
		$publicKey = $this->resolveSecret(config("services.pgp.public_key"));
		$passphrase = $this->resolveSecret(config("services.pgp.passphrase"));

		if (empty($publicKey)) {
			throw new \Exception("PGP_PUBLIC_KEY environment variable is not set (or the secret file is unreadable)");
		}

		return $this->encrypt($content, $publicKey, $passphrase);
	}

	/**
	 * Normalize an ASCII-armored key that may have been flattened by env vars
	 * or secret files: expand literal "\n" escape sequences, convert CR/CRLF
	 * to LF, and — when every newline was collapsed into spaces (single-line
	 * key) — rebuild the armor structure gpgme expects: BEGIN line, blank
	 * line, base64 body wrapped at 64 columns, CRC24 checksum line, END line.
	 */
	private function normalizeArmoredKey(string $key): string
	{
		$key = str_replace('\\n', "\n", $key);
		$key = str_replace(["\r\n", "\r"], "\n", $key);
		$key = trim($key);

		$matched = preg_match('/-----BEGIN PGP ([A-Z ]+)-----(.*?)-----END PGP \1-----/s', $key, $matches);

		if ($matched === 1 && !str_contains(trim($matches[2]), "\n")) {
			$type = $matches[1];
			$tokens = preg_split("/\s+/", trim($matches[2]), -1, PREG_SPLIT_NO_EMPTY);

			// Keep only base64-alphabet tokens; drops flattened armor headers
			// (e.g. "Version:") that would corrupt the reassembled body.
			$tokens = array_values(
				array_filter($tokens, static fn(string $token): bool => preg_match('/^[A-Za-z0-9+\/=]+$/', $token) === 1)
			);

			// The trailing "=XXXX" token is the CRC24 checksum line.
			$checksum = null;
			if ($tokens !== [] && str_starts_with((string) end($tokens), "=")) {
				$checksum = array_pop($tokens);
			}

			$body = wordwrap(implode("", $tokens), 64, "\n", true);

			$key =
				"-----BEGIN PGP {$type}-----\n\n" .
				$body .
				"\n" .
				($checksum !== null ? $checksum . "\n" : "") .
				"-----END PGP {$type}-----";
		}

		return $key . "\n";
	}

	/**
	 * Resolve a config value that may be either an inline secret or a path to
	 * a Docker secret file (e.g. /run/secrets/pgp_public_key), mirroring the
	 * $secret() helper used in config/database.php.
	 */
	private function resolveSecret(?string $pathOrValue): ?string
	{
		if ($pathOrValue === null || $pathOrValue === "") {
			return null;
		}

		if (str_starts_with($pathOrValue, "/")) {
			if (!is_file($pathOrValue) || !is_readable($pathOrValue)) {
				return null;
			}

			$contents = file_get_contents($pathOrValue);

			return $contents === false ? null : rtrim($contents, "\r\n");
		}

		return $pathOrValue;
	}
}
