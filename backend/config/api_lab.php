<?php

<<<<<<< HEAD
use Carbon\CarbonImmutable;

// Endeka historicalData: 12 closed months before the current one, and 12 from
// the current one on, each dated on its last day — computed from today, as
// Endeka asks for real calls (its sample used fixed dates).
$endekaSeries = static function (int $fromOffset, float $amount): array {
	$start = CarbonImmutable::now()->startOfMonth();

	return array_map(
		static fn(int $i): array => [
			"date" => $start
				->addMonths($fromOffset + $i)
				->endOfMonth()
				->toDateString(),
			"amount" => $amount + 250 * $i,
		],
		range(0, 11)
	);
};

return [
	"categories" => [
		"api" => [
			"label" => "API · Frontend",
			"file" => "api.php",
		],
		"web" => [
			"label" => "Web · Session & pages",
			"file" => "web.php",
		],
		"simulate" => [
			"label" => "Simulate inbound (Nexi → Rays)",
			// Inbound sia B2B (integrations.php) sia SSO (sso.php): una categoria puo' coprire piu' file.
			"file" => ["integrations.php", "sso.php"],
		],
		"integrations" => [
			"label" => "B2B · Outbound integrations",
			"file" => "integrations.php",
		],
		// Second level: one group per bank, so the list never turns into one long column.
		"banche" => [
			"label" => "Banche · Outbound integrations",
			"file" => "integrations.php",
			"groups" => [
				"endeka" => ["label" => "Endeka"],
			],
		],
		"restricted" => [
			"label" => "Restricted · Internal dev tools",
			"file" => "restricted.php",
		],
	],

	"allowed_route_files" => ["api.php", "web.php", "restricted.php", "integrations.php", "sso.php"],

	/*
    | Each entry: category + route_name. Optional: key (URL slug override),
    | path_param_defaults, body_encoding (json|form), sample_body.
=======
return [
    /*
    |--------------------------------------------------------------------------
    | API Lab categories
    |--------------------------------------------------------------------------
    |
    | Each category groups the endpoints declared in one or more route files.
    | A category may also declare 'groups' (key => ['label' => ...]) to split
    | its endpoints into a second level (e.g. one sub-section per partner);
    | an endpoint picks its group with the 'group' key.
    |
    */
    'categories' => [
        'api' => [
            'label' => 'API · Frontend',
            'file' => 'api.php',
        ],
        'web' => [
            'label' => 'Web · Session & pages',
            'file' => 'web.php',
        ],
        'simulate' => [
            'label' => 'Simulate inbound (partner → app)',
            // Inbound calls live in both the B2B routes and the SSO routes.
            'file' => ['integrations.php', 'sso.php'],
        ],
        'integrations' => [
            'label' => 'B2B · Outbound integrations',
            'file' => 'integrations.php',
        ],
        'partners' => [
            'label' => 'Partners · Outbound integrations',
            'file' => 'integrations.php',
            'groups' => [],
        ],
        'restricted' => [
            'label' => 'Restricted · Internal dev tools',
            'file' => 'restricted.php',
        ],
    ],

    // Route files API Lab is allowed to read endpoints from.
    'allowed_route_files' => ['api.php', 'web.php', 'restricted.php', 'integrations.php', 'sso.php'],

    /*
    |--------------------------------------------------------------------------
    | Endpoint catalog
    |--------------------------------------------------------------------------
    |
    | Each entry: category + route_name. Optional: key (URL slug override),
    | group, path_param_defaults, body_encoding (json|form), sample_query,
    | sample_body, inject_oauth_credentials (fills client_id/client_secret
    | from services.oauth_inbound when left empty). Entries whose route does
    | not exist are skipped.
    |
    | simulate     → inbound APIs, called as a partner would call us
    | integrations → outbound proxies towards the tenant's API
    | partners     → outbound proxies towards a third-party partner
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
    |
    */
<<<<<<< HEAD
	"endpoints" => [
		["category" => "api", "route_name" => "user.anagrafica"],
		["category" => "web", "route_name" => "health"],
		["category" => "web", "route_name" => "laravel.version"],

		// B2B · Simulate inbound (Nexi → Rays) — use before outbound calls
		[
			"category" => "simulate",
			"route_name" => "b2b.nexi.oauth.token",
			"body_encoding" => "form",
			"sample_body" => [
				"grant_type" => "client_credentials",
				"client_id" => "",
				"client_secret" => "",
			],
		],
		[
			"category" => "simulate",
			"route_name" => "b2b.nexi.split-payment.confirm-event",
			"sample_body" => [
				"deal_id" => "DEAL-2026-00042",
				"success" => true,
				"message" => "Payment confirmed",
			],
		],
		[
			"category" => "simulate",
			"route_name" => "b2b.nexi.split-payment.settlements",
			"sample_body" => [
				"repayments" => [
					[
						"batchDate" => "2026-01-01T00:00:00Z",
						"batchNumber" => "BATCH-001",
						"companyId" => "COMP-001",
						"dealId" => "DEAL-2026-00042",
						"currencyCode" => "EUR",
						"transactionType" => "Settled",
						"transactionAmount" => 1000.0,
						"repaymentAmount" => 250.0,
					],
				],
			],
		],
		// SSO inbound — le route reali, in routes/sso.php
		[
			"category" => "simulate",
			"route_name" => "nexi.sso.request",
			"sample_query" => ["external_id" => "0000000000"], // GET: l'id va in query string
			"sample_body" => [],
		],
		[
			"category" => "simulate",
			"route_name" => "nexi.sso.confirm",
			"path_param_defaults" => ["authz_code" => "fake-authz-code", "state" => "fake-state"],
			"sample_body" => [],
		],

		// NB: gli endpoint del mock provider (oidc.sim.token/jwks/authorize) NON sono catalogati:
		// standalone non sono testabili (token → invalid_grant senza un code da /authorize; authorize
		// richiede l'utenza in sessione). Il mock si esercita end-to-end dalla pagina Auth Simulation.

		// B2B · Outbound (Rays → Nexi Cashberry)
		[
			"category" => "integrations",
			"route_name" => "b2b.nexi.oauth.tenant-token",
			"body_encoding" => "form",
			"sample_body" => [],
		],
		[
			"category" => "integrations",
			"route_name" => "b2b.nexi.split-payment.start-event",
			"sample_body" => [
				"deal_id" => "ID-PRATICA",
				"type" => "Capitale",
				"balance" => "0.00",
				"currency" => "EUR",
				"start_date" => "2026-01-02T00:00:00Z",
				"pricing" => [
					"repayment_amount" => "0.00",
					"funded_amount" => "0.00",
					"split_percent" => "0",
					"factor_rate" => "0",
					"estimated_length_months" => "0",
				],
				"references" => [
					"application" => "RAYS",
					"merchant" => "ID-PRATICA",
					"external_merchant" => "ABI03104_PC0000151194",
				],
				"actions" => [
					"notify" => "/nexi/v1/split-payment/confirm-event",
				],
			],
		],
		[
			"category" => "integrations",
			"route_name" => "b2b.nexi.split-payment.complete-event",
			"sample_body" => [
				"payload" => [
					"deal_id" => "ID-PRATICA",
					"end_date" => "2026-01-02T00:00:00Z",
					"references" => [
						"application" => "RAYS",
						"merchant" => "ID-PRATICA",
						"external_merchant" => "id_payment_contract",
					],
					"actions" => [
						"notify" => "callback_url",
					],
				],
			],
		],
		[
			"category" => "banche",
			"group" => "endeka",
			"route_name" => "b2b.integratori.endeka.partner.onboarding",
			// Format of Endeka's specification. The merchant data are placeholders:
			// the real call will be built from the tiraggio request (see the notes).
			"sample_body" => [
				"nexiId" => "NEXIDIPC0000012345",
				"taxCode" => "12345678903",
				"email" => "mario.rossi@esempio.it",
				"requestedAmount" => 20000,
				"factorRate" => 1.145,
				"privacyAccepted" => true,
				"iban" => "IT60X0542811101000000123456",
				"bankName" => "Banca Esempio",
				"entryChannel" => "redirect",
				"splitPercentage" => 0.6,
				"rating" => "B",
				"historicalData" => [
					"history" => $endekaSeries(-12, 30000),
					"forecast" => $endekaSeries(0, 36000),
				],
			],
		],
	],
=======
    'endpoints' => [
        [
            'category' => 'api',
            'route_name' => 'seo.index',
            'path_param_defaults' => ['lang' => 'it', 'page' => 'home'],
        ],
        [
            'category' => 'api',
            'route_name' => 'password.forgot',
            'sample_body' => ['email' => 'user@example.com'],
        ],
        [
            'category' => 'api',
            'route_name' => 'password.reset',
            'sample_body' => [
                'token' => '',
                'email' => 'user@example.com',
                'password' => '',
                'password_confirmation' => '',
            ],
        ],
        ['category' => 'restricted', 'route_name' => 'swagger.impersonate'],
        ['category' => 'restricted', 'route_name' => 'swagger.revoke'],
    ],
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
];
