<?php

namespace App\Models\Traits;

use App\Services\TwoFactorAuthService;

trait HasTwoFactorAuth
{
    /**
     * Get the two-factor auth service
     */
    public function twoFactorAuthService(): TwoFactorAuthService
    {
        return app(TwoFactorAuthService::class);
    }

    /**
     * Generate a new two-factor authentication token
     */
    public function generateTwoFactorToken(): string
    {
        return $this->twoFactorAuthService()->generateToken($this);
    }

    /**
     * Check if token has expired and needs regeneration
     */
    public function needsNewToken(): bool
    {
        return $this->twoFactorAuthService()->needsNewToken($this);
    }

    /**
     * Check if the two-factor token is valid
     */
    public function isValidTwoFactorToken(string $token): bool
    {
        return $this->twoFactorAuthService()->validateToken($this, $token);
    }

    /**
     * Mark the two-factor token as verified
     */
    public function markTokenAsVerified(): void
    {
        $this->twoFactorAuthService()->markTokenVerified($this);
    }

    /**
     * Send two-factor authentication token via email
     */
    public function sendTwoFactorTokenEmail(): void
    {
        $this->twoFactorAuthService()->sendTokenEmail($this);
    }
} 