<?php

return [
	/*
    |--------------------------------------------------------------------------
    | Restricted Tool Policies
    |--------------------------------------------------------------------------
    |
    | Centralized access rules for internal tools.
    |
    | enabled_environments: APP_ENV values where the tool routes are registered
    | and reachable (others receive HTTP 404 from RestrictedAccessShield).
    |
    | Environment matrix:
    | - local: all tools
    | - staging, integration: same as local
    | - production: saml, horizon, pulse only
    | - testing: mirrors local (PHPUnit)
    |
    | checks values:
    | - auth: authenticated Backpack user
    | - ip: client IP in whitelist (cors.allowed_ips)
    |
    | If both are present, access is granted with OR logic.
    | If roles are specified, role validation is applied on auth-based access.
    |
    */
	"tools" => [
		"swagger" => [
			"enabled_environments" => ["local", "staging", "integration", "testing"],
			"checks" => ["auth", "ip"],
			"roles" => ["developer"],
		],

		// API sandbox docs page (/api-sandbox). No integration/production: the
		// sandbox itself is disabled there, so the page would demo dead routes.
		"api_sandbox" => [
			"enabled_environments" => ["local", "staging", "testing"],
			"checks" => ["auth", "ip"],
			"roles" => ["developer"],
		],

		// API portal docs page (Portal tab of /api-docs). The production twin
		// of the sandbox page: documents the real /api/v1/* surface and lists
		// the issued password tokens (semi-masked). Production on purpose — it
		// is where the live API lives; internal-only via developer role.
		"api_portal" => [
			"enabled_environments" => ["local", "testing", "production"],
			"checks" => ["auth"],
			"roles" => ["developer"],
		],

		// Unified API documentation page (/api-docs): single page with a
		// Sandbox | Portal tab toggle, replacing the two standalone pages.
		// Each tab checks its own feature flag (sandbox.enabled /
		// portal.enabled) and tool availability: staging gets the Sandbox
		// tab, production the Portal tab, local both. Not in integration —
		// neither surface exists there, so the page would only 404.
		"api_docs" => [
			"enabled_environments" => ["local", "staging", "testing", "production"],
			"checks" => ["auth", "ip"],
			"roles" => ["developer"],
		],

		"api_lab" => [
			"enabled_environments" => ["local", "staging", "integration", "testing"],
			"checks" => ["auth"],
			"roles" => ["developer"],
		],

		"pulse" => [
			"enabled_environments" => ["local", "staging", "integration", "production", "testing"],
			"checks" => ["auth"],
			"roles" => ["developer"],
		],

		"horizon" => [
			"enabled_environments" => ["local", "staging", "integration", "production", "testing"],
			"checks" => ["auth"],
			"roles" => ["developer"],
		],

		"auth_sim" => [
			"enabled_environments" => ["local", "staging", "integration", "production", "testing"],
			"checks" => ["auth"],
			"roles" => ["developer", "admin"],
		],

		// Backpack admin IP restriction. IP_BLOCK_ENABLE in cors.php is the
		// master switch. No 'auth' check here — Backpack's own middleware
		// (CheckIfAdmin) handles authentication. This adds IP gating on top.
		// 'backpack' => [
		//     'enabled_environments' => ['local', 'staging', 'integration', 'production', 'testing'],
		//     'checks' => ['ip'],
		// ],
	],
];
