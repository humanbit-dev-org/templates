<?php

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
    |
    */
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
];
