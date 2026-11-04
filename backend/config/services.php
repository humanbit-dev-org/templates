<?php

$secret = static function (?string $pathOrValue): ?string {
	if ($pathOrValue === null || $pathOrValue === "") {
		return null;
	}

	if (str_starts_with($pathOrValue, "/")) {
		if (!is_file($pathOrValue) || !is_readable($pathOrValue)) {
			return null;
		}
		$c = file_get_contents($pathOrValue);

		return $c === false ? null : rtrim($c, "\r\n");
	}

	return $pathOrValue;
};

return [
	/*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

	"postmark" => [
		"token" => env("POSTMARK_TOKEN"),
	],

	"ses" => [
		"key" => env("AWS_ACCESS_KEY_ID"),
		"secret" => env("AWS_SECRET_ACCESS_KEY"),
		"region" => env("AWS_DEFAULT_REGION", "us-east-1"),
	],

	"resend" => [
		"key" => env("RESEND_KEY"),
	],

	"slack" => [
		"notifications" => [
			"bot_user_oauth_token" => env("SLACK_BOT_USER_OAUTH_TOKEN"),
			"channel" => env("SLACK_BOT_USER_DEFAULT_CHANNEL"),
		],
	],

<<<<<<< HEAD
	"oauth_rays" => [
		"client_id" => $secret(env("OAUTH_RAYS_CLIENT_ID")),
		"client_secret" => $secret(env("OAUTH_RAYS_CLIENT_SECRET")),
	],

	"oauth_tenant" => [
		"client_id" => $secret(env("OAUTH_CLIENT_ID")),
		"client_secret" => $secret(env("OAUTH_CLIENT_SECRET")),
	],

	"mtls_tenant" => [
		"cert" => env("MTLS_CLIENT_CERT"),
		"key" => env("MTLS_CLIENT_KEY"),
	],
=======
    // Credentials B2B partners use to obtain a token from this application
    // (client_credentials grant on the inbound OAuth endpoint).
    'oauth_inbound' => [
        'client_id' => $secret(env('OAUTH_INBOUND_CLIENT_ID')),
        'client_secret' => $secret(env('OAUTH_INBOUND_CLIENT_SECRET')),
    ],

    // Credentials this application uses towards the tenant's own API.
    'oauth_tenant' => [
        'client_id' => $secret(env('OAUTH_CLIENT_ID')),
        'client_secret' => $secret(env('OAUTH_CLIENT_SECRET')),
    ],

    // Client certificate for outbound mutual-TLS calls (App\Services\Integrations\MtlsClient).
    'mtls_tenant' => [
        'cert' => env('MTLS_CLIENT_CERT'),
        'key' => env('MTLS_CLIENT_KEY'),
    ],
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

	"pgp" => [
		"public_key" => env("PGP_PUBLIC_KEY"),
		"passphrase" => env("PGP_PASSPHRASE"),
	],

	"tenant_api_base_url" => env("TENANT_API_BASE_URL"),

<<<<<<< HEAD
	// Outbound APIs of the banks inside the tenant, one entry per bank: each
	// states how it authenticates (see App\Services\Integrations\BankApiClient).
	"banche" => [
		"endeka" => [
			"base_url" => env("ENDEKA_API_BASE_URL"),
			"auth" => "api_key",
			"api_key_header" => "x-api-key",
			"api_key" => $secret(env("ENDEKA_API_KEY")),
		],
	],

	"oidc" => [
		"client_id" => $secret(env("OIDC_CLIENT_ID")),
		"client_secret" => $secret(env("OIDC_CLIENT_SECRET")),
		// Nessun redirect_uri: derivato da route('nexi.sso.confirm') nel controller (= APP_URL +
		// path della rotta). Qui non si potrebbe comunque, i config precedono le rotte.
		// Issuer atteso nell'ID_Token; vuoto finché Nexi non lo conferma (allora il check iss si attiva).
		"issuer" => env("OIDC_ISSUER"),
		"base_url" => env("OIDC_BASE_URL"), // host di token + jwks

		// Host dell'authorize: puo' essere DIVERSO da token/jwks (es. staging: authorize su
		// stgnexibusiness.nexi.it, token/jwks su stgb2b-issuing.nexi.it). Se vuoto ricade su
		// base_url (INT: host unico per tutti). Vedi authorizeUrl().
		"authorize_base_url" => env("OIDC_AUTHORIZE_BASE_URL"),

		// Path del provider OIDC, per-ambiente via env.
		// Per nexi-multi: authorize sul portale NNB, token/jwks su cashberry.
		"authorize_path" => env("OIDC_AUTHORIZE_PATH"),
		"token_path" => env("OIDC_TOKEN_PATH"),
		"jwks_path" => env("OIDC_JWKS_PATH"),
=======
    // Outbound APIs of third-party partners, one entry per partner. Each
    // entry states how it authenticates, e.g.:
    //
    //     'acme' => [
    //         'base_url' => env('ACME_API_BASE_URL'),
    //         'auth' => 'api_key',
    //         'api_key_header' => 'x-api-key',
    //         'api_key' => $secret(env('ACME_API_KEY')),
    //     ],
    'partners' => [],

    'oidc' => [
        'client_id' => $secret(env('OIDC_CLIENT_ID')),
        'client_secret' => $secret(env('OIDC_CLIENT_SECRET')),
        // No redirect_uri here: the SSO controller derives it from its own
        // confirm route (APP_URL + route path); config is loaded before routes.
        // Issuer expected in the ID token; while empty the "iss" check is skipped.
        'issuer' => env('OIDC_ISSUER'),
        'base_url' => env('OIDC_BASE_URL'), // host of the token and jwks endpoints

        // Host of the authorize endpoint: it may differ from the token/jwks one.
        // When empty it falls back to base_url.
        'authorize_base_url' => env('OIDC_AUTHORIZE_BASE_URL'),

        // Paths of the OIDC provider endpoints, per environment via env.
        'authorize_path' => env('OIDC_AUTHORIZE_PATH'),
        'token_path' => env('OIDC_TOKEN_PATH'),
        'jwks_path' => env('OIDC_JWKS_PATH'),

        // RSA private key used to decrypt the ID token (JWE) issued by the provider.
        // A path, not the content (like mtls_tenant): JWKFactory::createFromKeyFile() needs a file.
        'jwe_private_key' => env('OIDC_JWE_PRIVATE_KEY'),
    ],
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		// Chiave privata RSA con cui decifriamo l'ID_Token (JWE) emesso dal provider.
		// Path, non contenuto (come mtls_tenant): JWKFactory::createFromKeyFile() vuole un file.
		"jwe_private_key" => env("OIDC_JWE_PRIVATE_KEY"),
	],
];
