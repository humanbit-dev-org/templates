<?php

namespace App\Services\Auth;

use Illuminate\Hashing\Argon2IdHasher;
use Illuminate\Support\Facades\App;

/**
 * Argon2id hasher with HKDF pepper applied to every value.
 *
 * All password verification and hashing in the application flows through the
 * Hash facade (Auth::attempt, Backpack login, the current_password validation
 * rule, the 'hashed' model cast, password reset broker). Extending the argon2id
 * driver makes every one of those call sites derivation-aware without changing
 * them: the typed/plain value is transformed with the server-side HKDF secret
 * before Argon2id runs, so stored hashes are unverifiable without the pepper
 * even if the database leaks.
 *
 * The derivation service is resolved lazily so the hasher can be constructed
 * in contexts where the secret config is not loaded (e.g. early boot).
 */
class DerivingArgon2IdHasher extends Argon2IdHasher
{
	/**
	 * Hash the given value after HKDF derivation.
	 *
	 * @param  string  $value
	 */
	public function make(#[\SensitiveParameter] $value, array $options = []): string
	{
		return parent::make($this->derive($value), $options);
	}

	/**
	 * Check the given plain value against a hash, deriving it first.
	 *
	 * @param  string  $value
	 * @param  string|null  $hashedValue
	 */
	public function check(#[\SensitiveParameter] $value, $hashedValue, array $options = []): bool
	{
		if ($value === null) {
			return false;
		}

		return parent::check($this->derive($value), $hashedValue, $options);
	}

	private function derive(string $value): string
	{
		return App::make(PasswordDerivationService::class)->derive($value);
	}
}
