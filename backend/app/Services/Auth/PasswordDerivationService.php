<?php

namespace App\Services\Auth;

/**
 * Password Derivation Service using HKDF
 *
 * Derives passwords using HKDF (HMAC-based Key Derivation Function, RFC 5869)
 * to transform structured input (user identifier + secret) into
 * cryptographically secure, uniformly distributed output.
 *
 * Security layers:
 * 1. Input composition: user identifier + inner pepper (auth.user_pepper_inner)
 * 2. HKDF derivation with the outer pepper (auth.user_pepper_outer) as salt
 * 3. Argon2id hashing (handled by caller via Hash::make)
 */
class PasswordDerivationService
{
	private string $masterSecret;

	private string $basePassword;

	public function __construct()
	{
		$this->basePassword = config("auth.user_pepper_inner") ?? "";
		$this->masterSecret = config("auth.user_pepper_outer") ?? "";

		if (empty($this->masterSecret) || empty($this->basePassword)) {
			if (app()->runningInConsole()) {
				$this->masterSecret = $this->masterSecret ?: "";
				$this->basePassword = $this->basePassword ?: "";

				return;
			}

<<<<<<< HEAD
			throw new \RuntimeException(
				"MASTER_RAYS_PASSWORD or USER_RAYS_PASSWORD not configured in config/auth.php. " .
					"Generate with: openssl rand -hex 32"
			);
		}
	}

	/**
	 * Derive password using HKDF
	 *
	 * @param  string  $userId  User identifier
	 * @param  string  $bancaAbi  Banca ABI code
	 * @return string Derived key (32 bytes, hex encoded for readability)
	 */
	public function derive(string $userId): string
	{
		$inputKeyMaterial = $userId . $this->basePassword;
=======
            throw new \RuntimeException(
                'auth.user_pepper_inner or auth.user_pepper_outer not configured in config/auth.php. '.
                'Generate with: openssl rand -hex 32'
            );
        }
    }

    /**
     * Derive password using HKDF
     *
     * @param  string  $userId  User identifier (users primary key)
     * @return string Derived key (32 bytes, hex encoded for readability)
     */
    public function derive(string $userId): string
    {
        $inputKeyMaterial = $userId.$this->basePassword;
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		$derived = hash_hkdf(
			algo: "sha256",
			key: $inputKeyMaterial,
			length: 32,
			info: "app-login-v1",
			salt: $this->masterSecret
		);

		return bin2hex($derived);
	}
}
