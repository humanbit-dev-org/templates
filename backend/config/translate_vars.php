<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Models used to auto-generate placeholder registry from $fillable
    |--------------------------------------------------------------------------
    */
    'models' => [
        'user' => \App\Models\User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Global blacklist (fields excluded from placeholders)
    |--------------------------------------------------------------------------
    */
    'blacklist' => [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'password',
        'remember_token',
        'token',
        'token_expire',
        'token_verified',
        'email_verified_at',
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional per-field overrides
    |--------------------------------------------------------------------------
    | You can redefine labels/description/model for generated keys.
    | Example:
    | 'overrides' => [
    |   'ragione_sociale' => ['label_it' => 'Ragione Sociale', 'label_en' => 'Company Name'],
    | ],
    */
    'overrides' => [],

    /*
    |--------------------------------------------------------------------------
    | Frontend-only variables (calculated in Next.js, not from DB models)
    |--------------------------------------------------------------------------
    | These are shown as a separate "Frontend" group in the variable picker.
    | On the frontend, pass a matching key in the vars object passed to
    | interpolateHtml() / interpolateNodes().
    |
    | Example:
    |   'tiraggio_percentuale_trattenuta' => [
    |       'label_it'    => 'Tiraggio Trattenuto (%)',
    |       'label_en'    => 'Loan Repayment (%)',
    |       'description' => 'Percentuale di avanzamento del tiraggio calcolato lato frontend',
    |   ],
    */
    'frontend_vars' => [

    ],
];
