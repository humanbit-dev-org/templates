<?php

use App\Integrations\Banche\Sparkasse\PraticheCsv;
use App\Jobs\Sftp\ElaboraPratiche;

/*
|--------------------------------------------------------------------------
| Bank file jobs
|--------------------------------------------------------------------------
| The data team's Lambda drops each bank file in
| {root}/{banca}/{operazione}/inbox/ of the production bucket, naming the bank
| folder from the file name (SPKC… → sparkasse, CVBC… → civibank);
| `sftp:processa` moves it to processing/ and queues the job registered here.
| The job ends it in processed/ (with its summary) or failed/ (with the reason).
| See analisi-sftp-jobs.md.
*/

return [

    // The tenant/environment bucket (AWS_BUCKET).
    'disk' => 's3',

    'root' => 'jobs',

    // As the import jobs: the standalone cache connection, no CROSSSLOT on the cluster.
    'lock_store' => 'redis',

    // Served by supervisor-import in config/horizon.php.
    'queue' => '{import}',

    // folder => ['abi' => ABI its clients are found by, 'operazioni' => operazione =>
    // ['job' => SftpJob subclass, ...options]]. A bank with an empty ABI is
    // registered but not processed: its files go to failed/ with the reason.
    'banche' => [
        'sparkasse' => [
            'abi' => '06045',
            'operazioni' => [
                // Acceptances and early extinctions (the trattenute come from Nexi).
                'pratiche' => [
                    'job' => ElaboraPratiche::class,
                    'parser' => PraticheCsv::class,
                    // Nexi split-payment events: start on acceptance, deal_complete on
                    // extinction. Off until confirmed for Sparkasse.
                    'start_event' => false,
                    'complete_event' => false,
                ],
            ],
        ],
        // Same file layout as Sparkasse. ABI still to be confirmed.
        'civibank' => [
            'abi' => '',
            'operazioni' => [
                'pratiche' => [
                    'job' => ElaboraPratiche::class,
                    'parser' => PraticheCsv::class,
                    'start_event' => false,
                    'complete_event' => false,
                ],
            ],
        ],
    ],

];
