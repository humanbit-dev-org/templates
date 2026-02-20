<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TwoFactorAuthService
{
	/**
	 * Generate a new two-factor authentication token for user
	 */
	public function generateToken(User $user): string
	{
		$token = str_pad(random_int(0, 999999), 6, "0", STR_PAD_LEFT);

		$user->update([
			"token" => $token,
			"token_expire" => Carbon::now()->addDays(30),
			"token_verified" => false,
		]);

		return $token;
	}

	/**
	 * Check if user needs a new token
	 */
	public function needsNewToken(User $user): bool
	{
		return !$user->token || !$user->token_expire || $user->token_expire->isPast() || !$user->token_verified;
	}

	/**
	 * Validate the provided token
	 */
	public function validateToken(User $user, string $token): bool
	{
		return $user->token === $token && $user->token_expire && $user->token_expire->isFuture();
	}

	/**
	 * Mark token as verified
	 */
	public function markTokenVerified(User $user): void
	{
		$user->update(["token_verified" => true]);
	}

	/**
	 * Send token via email
	 */
	public function sendTokenEmail(User $user): void
	{
		if (!$user->email) {
			return;
		}

		$token = $this->generateToken($user);

		Mail::send(
			"emails.two-factor-token",
			[
				"token" => $token,
				"user" => $user,
			],
			function ($message) use ($user) {
				$message->to($user->email)->subject("Codice di accesso - Autenticazione a due fattori");
			}
		);
	}

	/**
	 * Check if user can send token (rate limiting)
	 */
	public function canSendToken(User $user): bool
	{
		$throttleKey = $this->getThrottleKey($user);
		[$maxAttempts, $decayMinutes] = $this->getThrottleConfig();

		return !RateLimiter::tooManyAttempts($throttleKey, $maxAttempts);
	}

	/**
	 * Record a token send attempt
	 */
	public function recordTokenAttempt(User $user): void
	{
		$throttleKey = $this->getThrottleKey($user);
		[$maxAttempts, $decayMinutes] = $this->getThrottleConfig();

		RateLimiter::hit($throttleKey, $decayMinutes * 60);
	}

	/**
	 * Reset rate limiting for user
	 */
	public function resetRateLimit(User $user): void
	{
		RateLimiter::clear($this->getThrottleKey($user));
	}

	/**
	 * Get remaining attempts for user
	 */
	public function getRemainingAttempts(User $user): int
	{
		$throttleKey = $this->getThrottleKey($user);
		[$maxAttempts, $decayMinutes] = $this->getThrottleConfig();
		$attempts = RateLimiter::attempts($throttleKey);

		return max(0, $maxAttempts - $attempts);
	}

	/**
	 * Get time until next attempt is allowed
	 */
	public function getTimeUntilNextAttempt(User $user): int
	{
		$throttleKey = $this->getThrottleKey($user);
		return RateLimiter::availableIn($throttleKey);
	}

	/**
	 * Get throttle key for user
	 */
	private function getThrottleKey(User $user): string
	{
		return "two-factor-resend:" . Str::lower($user->email);
	}

	/**
	 * Get throttle configuration
	 */
	private function getThrottleConfig(): array
	{
		$config = config("backpack.base.two_factor_throttle_access", "5,30");
		return explode(",", $config);
	}
}
