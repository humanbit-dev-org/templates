<?php

namespace App\Services\Auth;

/**
 * HMAC token binding a later account choice to a SAML login that already
 * passed the gate (IdP signature verified, or operator in simulation).
 *
 * Meant for SSO flows where one assertion carries several identifiers and the
 * user picks one on a follow-up request: without the token that request would
 * accept any identifier and log anybody in. Issue it only after authSaml(),
 * verify it before the login. No new secret — `app.key`, which already signs
 * the application cookies and tokens, is used.
 *
 * Not wired to any route in the template: SamlController logs in the single
 * username carried by the assertion.
 */
class SamlUserAllowlist
{
    public function sign(array $userIds): string
    {
        $userIds = array_values(array_unique(array_map('strval', $userIds)));
        sort($userIds);

        $payload = base64_encode(json_encode($userIds, JSON_THROW_ON_ERROR));
        $sig = hash_hmac('sha256', $payload, (string) config('app.key'));

        return $payload.'.'.$sig;
    }

    /** True se il token è integro (HMAC valido) e contiene $userId. */
    public function contains(string $token, string $userId): bool
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$payload, $sig] = $parts;
        $expected = hash_hmac('sha256', $payload, (string) config('app.key'));
        if (! hash_equals($expected, $sig)) {
            return false;
        }

        $list = json_decode((string) base64_decode($payload, true), true);

        return is_array($list) && in_array($userId, $list, true);
    }
}
