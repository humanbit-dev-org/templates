<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserTwoFactor;
use App\Support\TwoFactorDevice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class TwoFactorAuthService
{
<<<<<<< HEAD
	/**
	 * Resolve or create the 2FA record for a specific user device.
	 */
	public function resolveDevice(User $user, string $deviceId): UserTwoFactor
	{
		return UserTwoFactor::firstOrCreate(
			[
				"user_id" => $user->getKey(),
				"device_id" => $deviceId,
			],
			[
				"token_verified" => false,
			]
		);
	}
=======
    /**
     * Resolve or create the 2FA record for a specific user device.
     */
    public function resolveDevice(User $user, string $deviceId): UserTwoFactor
    {
        return UserTwoFactor::firstOrCreate(
            [
                'username' => $user->getKey(),
                'device_id' => $deviceId,
            ],
            [
                'token_verified' => false,
            ]
        );
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

	/**
	 * Generate a new two-factor authentication token for the given device.
	 */
	public function generateToken(User $user, string $deviceId): string
	{
		$token = str_pad((string) random_int(0, 999999), 6, "0", STR_PAD_LEFT);

		$this->resolveDevice($user, $deviceId)->update([
			"token" => $token,
			"token_expire" => Carbon::now()->addDays(TwoFactorDevice::trustDays()),
			"token_verified" => false,
		]);

		return $token;
	}

<<<<<<< HEAD
	/**
	 * Check if this device needs a new token.
	 */
	public function needsNewToken(User $user, string $deviceId): bool
	{
		$device = UserTwoFactor::query()->where("user_id", $user->getKey())->where("device_id", $deviceId)->first();
=======
    /**
     * Check if this device needs a new token.
     */
    public function needsNewToken(User $user, string $deviceId): bool
    {
        $device = UserTwoFactor::query()
            ->where('username', $user->getKey())
            ->where('device_id', $deviceId)
            ->first();
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		if ($device === null) {
			return true;
		}

		return !$device->token || !$device->token_expire || $device->token_expire->isPast() || !$device->token_verified;
	}

<<<<<<< HEAD
	/**
	 * Validate the provided token for the given device.
	 */
	public function validateToken(User $user, string $deviceId, string $token): bool
	{
		$device = UserTwoFactor::query()->where("user_id", $user->getKey())->where("device_id", $deviceId)->first();
=======
    /**
     * Validate the provided token for the given device.
     */
    public function validateToken(User $user, string $deviceId, string $token): bool
    {
        $device = UserTwoFactor::query()
            ->where('username', $user->getKey())
            ->where('device_id', $deviceId)
            ->first();
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		if ($device === null) {
			return false;
		}

		return $device->token === $token && $device->token_expire && $device->token_expire->isFuture();
	}

	/**
	 * Mark the device token as verified.
	 */
	public function markTokenVerified(User $user, string $deviceId): void
	{
		$this->resolveDevice($user, $deviceId)->update(["token_verified" => true]);
	}

	/**
	 * Send token via email for the given device.
	 */
	public function sendTokenEmail(User $user, string $deviceId): void
	{
		if (!$user->email) {
			return;
		}

		$token = $this->generateToken($user, $deviceId);

<<<<<<< HEAD
		$user->loadMissing("banca.interfaccia");

		Mail::send(
			"emails.two-factor-token",
			[
				"token" => $token,
				"user" => $user,
				"trustDays" => TwoFactorDevice::trustDays(),
				"bankLogoUrl" => TenantAssetUrl::bankLogoFromBanca($user->banca),
				"bankName" => $user->banca?->nome,
			],
			function ($message) use ($user) {
				$message->to($user->email)->subject("Codice di accesso - Autenticazione a due fattori");
			}
		);
	}
=======
        Mail::send(
            'emails.two-factor-token',
            [
                'token' => $token,
                'user' => $user,
                'trustDays' => TwoFactorDevice::trustDays(),
            ],
            function ($message) use ($user) {
                $message->to($user->email)->subject('Codice di accesso - Autenticazione a due fattori');
            }
        );
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

	/**
	 * Check if user can send token (rate limiting).
	 */
	public function canSendToken(User $user): bool
	{
		$throttleKey = $this->getThrottleKey($user);
		[$maxAttempts] = $this->getThrottleConfig();

		return !RateLimiter::tooManyAttempts($throttleKey, $maxAttempts);
	}

	/**
	 * Record a token send attempt.
	 */
	public function recordTokenAttempt(User $user): void
	{
		$throttleKey = $this->getThrottleKey($user);
		[, $decayMinutes] = $this->getThrottleConfig();

		RateLimiter::hit($throttleKey, (int) $decayMinutes * 60);
	}

	/**
	 * Reset rate limiting for user.
	 */
	public function resetRateLimit(User $user): void
	{
		RateLimiter::clear($this->getThrottleKey($user));
	}

	/**
	 * Get remaining attempts for user.
	 */
	public function getRemainingAttempts(User $user): int
	{
		$throttleKey = $this->getThrottleKey($user);
		[$maxAttempts] = $this->getThrottleConfig();
		$attempts = RateLimiter::attempts($throttleKey);

		return max(0, $maxAttempts - $attempts);
	}

	/**
	 * Get time until next attempt is allowed.
	 */
	public function getTimeUntilNextAttempt(User $user): int
	{
		return RateLimiter::availableIn($this->getThrottleKey($user));
	}

	private function getThrottleKey(User $user): string
	{
		return "two-factor-resend:" . Str::lower($user->email);
	}

	/**
	 * @return array{0: int, 1: int}
	 */
	private function getThrottleConfig(): array
	{
		$config = config("backpack.base.two_factor_throttle_access", "5,30");
		$parts = explode(",", $config);

		return [(int) ($parts[0] ?? 5), (int) ($parts[1] ?? 30)];
	}
}
