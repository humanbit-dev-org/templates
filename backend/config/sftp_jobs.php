<?php

/*
|--------------------------------------------------------------------------
| Partner file jobs (SFTP / bucket drop)
|--------------------------------------------------------------------------
| Partners drop their files in {root}/{partner}/{operation}/inbox/ of the
| bucket below. A processing command moves each file to processing/ and
| queues the job registered here for that partner and operation; the job
| ends it in processed/ (with its summary) or failed/ (with the reason).
|
| The template registers no partner yet.
*/

return [

    // The tenant/environment bucket (AWS_BUCKET).
    'disk' => 's3',

    'root' => 'jobs',

    // As the import jobs: the standalone cache connection, no CROSSSLOT on the cluster.
    'lock_store' => 'redis',

    // Served by supervisor-import in config/horizon.php.
    'queue' => '{import}',

    // folder => ['operations' => operation => ['job' => job class, ...options]].
    // Example:
    //
    //     'acme' => [
    //         'operations' => [
    //             'orders' => ['job' => \App\Jobs\Sftp\ImportAcmeOrders::class],
    //         ],
    //     ],
    'partners' => [],

];
