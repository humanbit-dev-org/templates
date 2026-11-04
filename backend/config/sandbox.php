<?php

return [
	// Staging and local only — never integration, never production.
	"enabled" => (bool) env("SANDBOX_ENABLED", false) && !in_array(env("APP_ENV"), ["production", "integration"], true),

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
];
