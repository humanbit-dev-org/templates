<?php

return [
	/*
    |--------------------------------------------------------------------------
    | Folder-based CSV import (database/import/{folder}/*.csv)
    |--------------------------------------------------------------------------
    |
    | CheckImportFiles scans database/import/ and queues one ProcessImportFile
    | job per CSV found. The folder name decides the target model:
    | ucfirst($folder), unless 'model_overrides' maps it to another model
    | (e.g. 'utenti' => 'User').
    |
    | 'priority' orders the folders inside one scan, lower first: models that
    | others reference through a foreign key must be imported before them
    | (e.g. ['role' => 1, 'user' => 2]). Unlisted folders run last.
    |
    */

<<<<<<< HEAD
	"abi_prefix" => env("IMPORT_ABI_PREFIX", "03440"),
=======
    'priority' => [],

    'model_overrides' => [],

    /*
    |--------------------------------------------------------------------------
    | Upload CSV page (Backpack → Tools → Upload CSV)
    |--------------------------------------------------------------------------
    |
    | File types an operator can upload. Each type drops its files into
    | database/import/{directory}; 'patterns' are file-name prefixes used to
    | detect the type automatically. Example:
    |
    |     'utenti' => [
    |         'name' => 'Anagrafica utenti',
    |         'directory' => 'user',
    |         'description' => 'Crea o aggiorna gli utenti a partire dallo username.',
    |         'patterns' => ['Utenti_'],
    |     ],
    |
    */

    'upload_types' => [],

>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
];
