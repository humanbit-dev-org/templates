<?php

return [
	/*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default hash driver that will be used to hash
    | passwords for your application. Using Argon2id as default for better
    | security: memory-hard algorithm resistant to GPU attacks.
    |
    | Supported: "bcrypt", "argon", "argon2id"
    |
    */

	"driver" => env("HASH_DRIVER", "argon2id"),

	/*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    |
    | Here you may specify the configuration options that should be used when
    | passwords are hashed using the Bcrypt algorithm. This will allow you
    | to control the amount of time it takes to hash the given password.
    |
    */

	"bcrypt" => [
		"rounds" => env("BCRYPT_ROUNDS", 12),
		"verify" => env("HASH_VERIFY", true),
		"limit" => env("BCRYPT_LIMIT", null),
	],

	/*
    |--------------------------------------------------------------------------
    | Argon Options
    |--------------------------------------------------------------------------
    |
    | Here you may specify the configuration options that should be used when
    | passwords are hashed using the Argon algorithm. These will allow you
    | to control the amount of time it takes to hash the given password.
    |
    */

	"argon" => [
		"memory" => env("ARGON_MEMORY", 65536), // 64MB
		"threads" => env("ARGON_THREADS", 2),
		"time" => env("ARGON_TIME", 4),
		"verify" => env("HASH_VERIFY", true),
	],

	/*
    |--------------------------------------------------------------------------
    | Rehash On Login
    |--------------------------------------------------------------------------
    |
    | Disabled: in banking context, rehash must be a planned and audited
    | operation, not a silent side effect. Use auth:rehash-passwords command.
    |
    */

	"rehash_on_login" => false,
];
