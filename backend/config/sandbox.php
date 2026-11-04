<?php

return [
    // Staging and local only — never integration, never production.
    'enabled' => (bool) env('SANDBOX_ENABLED', false)
        && ! in_array(env('APP_ENV'), ['production', 'integration'], true),

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
];
