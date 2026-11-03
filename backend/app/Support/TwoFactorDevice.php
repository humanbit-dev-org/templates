<?php

namespace App\Support;

use Illuminate\Http\Request;

final class TwoFactorDevice
{
    public const COOKIE_NAME = 'backpack_2fa_device';

    public const REQUEST_KEY = 'device';

    public const SESSION_KEY = '2fa_device_id';

    public static function trustDays(): int
    {
        return max(1, (int) config('backpack.base.two_factor_device_trust_days', 30));
    }

    /**
     * @return array<int, string>
     */
    public static function validationRules(): array
    {
        return ['required', 'string', 'uuid', 'max:36'];
    }

    public static function fromRequest(Request $request): ?string
    {
        $deviceId = $request->input(self::REQUEST_KEY);

        if (! is_string($deviceId) || $deviceId === '') {
            return null;
        }

        return $deviceId;
    }

    public static function fromSession(Request $request): ?string
    {
        $deviceId = $request->session()->get(self::SESSION_KEY);

        if (! is_string($deviceId) || $deviceId === '') {
            return null;
        }

        return $deviceId;
    }
}
