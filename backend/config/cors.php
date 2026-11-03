<?php

$origins = array_values(
    array_filter(array_map(static fn ($v) => trim($v), explode(',', (string) env('ALLOWED_ORIGINS', ''))))
);

$frontendUrl = env('FRONTEND_URL', '');
if ($frontendUrl) {
    $origins[] = $frontendUrl;
}

// Add localhost only in local/development environment
if (env('APP_ENV', 'production') === 'local') {
    $origins[] = 'http://localhost:3000';
}

$origins = array_values(array_unique($origins));

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

    'paths' => [
        'api/*',
        // "login",
        // "logout",
        // "register",
        // "sanctum/csrf-cookie",
        // "/email/verification-notification",
        // "forgot-password",
        // "reset-password",
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Accept', 'Authorization', 'X-Locale', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Set this to true if the client sends cookies with credentials (e.g. session-based authentication).
    // Example: fetch(..., { credentials: 'include' or 'same-origin' }) → this means cookies are sent with the request.
    // Keep it false if your API only uses API tokens (e.g. authorization header with API token)
    'supports_credentials' => false,

    'iframe_enable' => (bool) env('IFRAME_ENABLE', false),

    /*
     * Public IP addresses allowed to access API documentation without authentication.
     * Use public IPs only (e.g., office IP, VPN exit IP, partner IPs).
     *
     * Supports:
     * - Exact IPs: '203.0.113.50'
     * - CIDR notation: '203.0.113.0/24'
     * - Wildcards: '203.0.113.*'
     *
     * Set via ALLOWED_IPS env variable (comma-separated).
     * If empty, Backpack authentication is required.
     *
     * IP_BLOCK_ENABLE is the master switch: when false, all 'ip' checks in
     * restricted_tools.php are disabled (auth-only fallback) and IP-only
     * restrictions (e.g. backpack) do not block anything.
     */

    'ip_block_enable' => (bool) env('IP_BLOCK_ENABLE', false),

    'allowed_ips' => array_filter(array_map(static fn ($v) => trim($v), explode(',', (string) env('ALLOWED_IPS', '')))),
];
