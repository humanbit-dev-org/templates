<?php

namespace App\Services\Auth;

/**
 * Token HMAC che lega la scelta su `/api/auth/saml/select-user-id` al risultato di una
 * `authSaml()` già passata dal gate (firma IdP verificata, o operatore in simulazione).
 *
 * Senza questo, quell'endpoint accetterebbe un `idgiuridico` qualsiasi e loggherebbe chiunque:
 * lo emettiamo SOLO dopo authSaml, e lo verifichiamo prima del login. Nessun segreto nuovo —
 * si usa `app.key`, che già firma i cookie e i token dell'app.
 */
class SamlUserAllowlist
{
	public function sign(array $userIds): string
	{
		$userIds = array_values(array_unique(array_map("strval", $userIds)));
		sort($userIds);

		$payload = base64_encode(json_encode($userIds, JSON_THROW_ON_ERROR));
		$sig = hash_hmac("sha256", $payload, (string) config("app.key"));

		return $payload . "." . $sig;
	}

	/** True se il token è integro (HMAC valido) e contiene $userId. */
	public function contains(string $token, string $userId): bool
	{
		$parts = explode(".", $token, 2);
		if (count($parts) !== 2) {
			return false;
		}

		[$payload, $sig] = $parts;
		$expected = hash_hmac("sha256", $payload, (string) config("app.key"));
		if (!hash_equals($expected, $sig)) {
			return false;
		}

		$list = json_decode((string) base64_decode($payload, true), true);

		return is_array($list) && in_array($userId, $list, true);
	}
}
