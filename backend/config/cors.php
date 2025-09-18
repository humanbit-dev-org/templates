<?php

return [
	/*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

	/*
	|--------------------------------------------------------------------------
	| Sanctum Authentication
	|--------------------------------------------------------------------------
	|
	| Sanctum provide two different authentication methods:
	| - Session Cookie
	| - Personal Access Token
	|
	| The Session Cookie is the default authentication method and is used when
	| the user is logged in.
	|
	| The Personal Access Token is used when the user is not logged in.
	|
	| - When Session Cookie is used, uncomment all paths and put supports_credentials to true
	| - When Personal Access Token is used, comment all paths except api/* and put supports_credentials to false
	*/

	"paths" => [
		"api/*",
		// "login",
		// "logout",
		// "register",
		// "sanctum/csrf-cookie",
		// "/email/verification-notification",
		// "forgot-password",
		// "reset-password",
	],

	"allowed_methods" => ["*"],

	"allowed_origins" => [env("FRONTEND_URL", "http://localhost:3000")],

	"allowed_origins_patterns" => [],

	"allowed_headers" => ["Content-Type", "Accept", "Authorization", "X-Locale"],

	"exposed_headers" => [],

	"max_age" => 0,

	"supports_credentials" => false, // true for Session Cookie, false for Personal Access Token
];
