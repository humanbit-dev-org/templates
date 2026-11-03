<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bulk quick actions (labels & enum options)
    |--------------------------------------------------------------------------
    |
    | This config controls labels shown in Backpack bulk quick actions.
    | Keys are model FQCNs; each field can define custom labels.
    |
    | Structure:
    | - models[ModelFqcn][boolean][field] => [
    |       '1' => '...', // label when value is 1 (legacy/simple schema)
    |       '0' => '...', // label when value is 0
    |       'icons' => [ // optional icon classes for each value
    |           '1' => 'la la-toggle-on',
    |           '0' => 'la la-toggle-off',
    |       ],
    |       'colors' => [ // optional color variants for each value
    |           '1' => 'success', // bootstrap-like variants: danger, warning, success, info
    |           '0' => 'warning',
    |       ],
    |   ]
    | - models[ModelFqcn][enum][field] => [
    |       'apply_label' => '...',
    |       'apply_icon'  => 'la la-check', // optional icon class for the Applica button
    |       'apply_color' => 'info', // optional color variant: danger, warning, success, info
    |       'options' => ['db_value' => 'Human label', ...],
    |   ]
    |
    */

    'models' => [
        \App\Models\User::class => [
            'boolean' => [
                'sospeso' => [
                    '1' => 'Sospendi utente',
                    '0' => 'Riattiva utente',
                    'icons' => [
                        '1' => 'la la-times',
                        '0' => 'la la-check',
                    ],
                    'colors' => [
                        '1' => 'danger',
                        '0' => 'success',
                    ],
                ],
            ],
        ],
    ],
];
