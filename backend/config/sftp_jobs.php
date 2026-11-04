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
	"disk" => "s3",

	"root" => "jobs",

	// As the import jobs: the standalone cache connection, no CROSSSLOT on the cluster.
	"lock_store" => "redis",

<<<<<<< HEAD
	// Served by supervisor-import in config/horizon.php.
	"queue" => "{import}",
=======
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
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

	// folder => ['abi' => ABI its clients are found by, 'operazioni' => operazione =>
	// ['job' => SftpJob subclass, ...options]]. A bank with an empty ABI is
	// registered but not processed: its files go to failed/ with the reason.
	"banche" => [
		"sparkasse" => [
			"abi" => "06045",
			"operazioni" => [
				// Acceptances and early extinctions (the trattenute come from Nexi).
				"pratiche" => [
					"job" => ElaboraPratiche::class,
					"parser" => PraticheCsv::class,
					// Nexi split-payment events: start on acceptance, deal_complete on
					// extinction. Off until confirmed for Sparkasse.
					"start_event" => false,
					"complete_event" => false,
				],
			],
		],
		// Same file layout as Sparkasse. ABI still to be confirmed.
		"civibank" => [
			"abi" => "",
			"operazioni" => [
				"pratiche" => [
					"job" => ElaboraPratiche::class,
					"parser" => PraticheCsv::class,
					"start_event" => false,
					"complete_event" => false,
				],
			],
		],
	],
];
