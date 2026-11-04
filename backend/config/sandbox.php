<?php

return [
	// Staging and local only — never integration, never production.
	"enabled" => (bool) env("SANDBOX_ENABLED", false) && !in_array(env("APP_ENV"), ["production", "integration"], true),

<<<<<<< HEAD
	// The bank token minting defaults to when none is requested.
	"default_bank" => "33333",

	// The sandbox dataset is a bank per product shape, not one kitchen-sink
	// bank: the docs tabs scope every demo call to one of these. The ABIs sit
	// outside the real ABI space (Italian ABIs always start with 0), so the
	// fake banks can never collide with a real one — nor with the internal
	// 99999, which the seeders treat as Backpack operators.
	//
	// - 33333: traditional single tiraggio — no durata parametrica, no
	//   conguaglio, no multi-tiraggio.
	// - 66666: durata parametrica single tiraggio, conguaglio plafond — the
	//   flag the ?sandbox_conguaglio= toggle flips per request.
	"banks" => [
		"33333" => [
			"nome" => "Banca RAYS",
			"label" => "Tiraggio tradizionale",
			"po" => "SANDBOX_PO",
			"durata_parametrica" => false,
		],
		"66666" => [
			"nome" => "Banca RAYS Durata Parametrica",
			"label" => "Durata parametrica",
			"po" => "SANDBOX_DP_PO",
			"durata_parametrica" => true,
		],
	],

	// 9h, same as the portal access token: sandbox tokens only unlock fake,
	// rolled-back data, so a long TTL is safe and saves re-minting mid-session.
	"token_minutes" => 540,
	"limits" => ["tokens_per_hour" => 10, "calls_per_minute" => 60],
=======
    /*
    |--------------------------------------------------------------------------
    | Demo profiles
    |--------------------------------------------------------------------------
    |
    | Each profile is a demo account the sandbox token is minted on. The API
    | docs page shows one tab per profile, and every demo call runs as that
    | account. 'user' is the username (users primary key) of the account,
    | created by sandbox:seed; 'label' is the tab caption.
    |
    | Add a profile per kind of caller worth demoing (e.g. a regular user and
    | a user with a different role).
    |
    */
    'default_profile' => 'demo',

    'profiles' => [
        'demo' => [
            'label' => 'Demo',
            'user' => 'sandbox_demo',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloned routes
    |--------------------------------------------------------------------------
    |
    | Names of the real routes copied under /sandbox/* (see routes/sandbox.php).
    | Every name must exist when the routes load, or the sandbox refuses to
    | boot. Empty by default: the template exposes no demo endpoint yet.
    |
    */
    'routes' => [],

    /*
    | Frontend role names (roles.name) allowed on the sandbox routes, on top of
    | the Admin and Developer Backpack roles that RoleScope always lets through.
    | Empty: any authenticated sandbox user.
    */
    'roles' => [],

    // 9h, same as the portal access token: sandbox tokens only unlock fake,
    // rolled-back data, so a long TTL is safe and saves re-minting mid-session.
    'token_minutes' => 540,
    'limits' => ['tokens_per_hour' => 10, 'calls_per_minute' => 60],
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
];
