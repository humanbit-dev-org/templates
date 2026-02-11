<?php

/*
|--------------------------------------------------------------------------
| SSH Configuration
|--------------------------------------------------------------------------
|
| This option controls the SSH configuration for the application.
| This is used to connect to the SSH server and transfer the database dump.
*/

return [
    'host' => env('SSH_HOST'),
    'port' => env('SSH_PORT'),
    'username' => env('SSH_USERNAME'),
    'private_key_path' => env('SSH_PRIVATE_KEY_PATH'),
    'known_hosts_path' => env('SSH_KNOWN_HOSTS_PATH'),
    'remote_base_path' => env('SSH_REMOTE_BASE_PATH'),
    'timeout' => env('SSH_TIMEOUT'),
];