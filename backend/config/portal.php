<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Portal
    |--------------------------------------------------------------------------
    |
    | The production-facing counterpart of the API sandbox: API clients
    | receive a portal refresh token (issued internally via
    | portal:refresh-token) and exchange it at POST /api/v1/token for an
    | api_token that performs real writes under /api/v1/*.
    |
    | Live in production and local/testing. Never on staging/integration —
    | there the sandbox already covers the demo surface.
    |
    */
    'enabled' => (bool) env('PORTAL_ENABLED', true)
        && in_array(env('APP_ENV'), ['local', 'testing', 'production'], true),

    // api_token lifetime: 9 hours, PayPal-style — short-lived working token,
    // re-exchanged as needed. The refresh token (root credential) is
    // unexpiring — rotation is manual via portal:refresh-token --rotate.
    'api_token_minutes' => (int) env('PORTAL_API_TOKEN_MINUTES', 540),

    /*
    | Names of the internal routes published under /api/v1/* (see
    | routes/portal.php). Every name must exist when the routes load, or the
    | portal refuses to boot. Empty by default: only the token exchange is live.
    */
    'routes' => [],

    /*
    | Frontend role names (roles.name) allowed on the published routes, on top
    | of the Admin and Developer Backpack roles that RoleScope always lets
    | through. Empty: any authenticated client holding the api:write ability.
    */
    'roles' => [],

    'limits' => [
        'tokens_per_hour' => 20,
        'calls_per_minute' => 60,
    ],

    // Token-name conventions. The PortalTokenFence recognizes these names to
    // contain portal credentials to their own surface; the token exchange
    // recognizes 'portal-refresh:*' as the only valid grant.
    'refresh_token_prefix' => 'portal-refresh',
    'api_token_name' => 'portal-access',
    'api_token_abilities' => ['api:write'],
];
