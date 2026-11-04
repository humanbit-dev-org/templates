<?php

$secret = static function (?string $pathOrValue): ?string {
	if ($pathOrValue === null || $pathOrValue === "") {
		return null;
	}

	if (str_starts_with($pathOrValue, "/")) {
		if (!is_file($pathOrValue) || !is_readable($pathOrValue)) {
			return null;
		}
		$c = file_get_contents($pathOrValue);

		return $c === false ? null : rtrim($c, "\r\n");
	}

	return $pathOrValue;
};

$redisNamespace = (string) env("REDIS_NAMESPACE", env("APP_ENV", env("APP_NAME", "laravel")));

// ---------------------------------------------------------------------------
// Redis configuration — built dynamically to handle cluster vs standalone mode.
//
// REDIS_CLUSTER=redis  → production (Valkey Serverless): EVERY logical Redis
//                        connection (default, session, cache, pulse, queues)
//                        is registered under clusters.* so that RedisManager
//                        calls connectToCluster() → PhpRedisClusterConnection
//                        for all of them, not just queues. A standalone
//                        (non-cluster-aware) phpredis client pointed at a
//                        cluster endpoint does not follow MOVED redirects, so
//                        any connection left standalone here silently breaks
//                        for keys not owned by the node it happens to connect
//                        to. This previously only covered "queues" — cache
//                        and session were left standalone even though
//                        CACHE_STORE=redis and SESSION_DRIVER=redis route
//                        through them, causing intermittent failures (e.g.
//                        batch progress tracking via Cache::increment(),
//                        and potentially session reads/writes).
//                        Horizon::use() checks clusters.* first, so it picks
//                        the cluster path and wraps the prefix in a hash tag.
//
// REDIS_CLUSTER unset  → local (standalone Docker Redis): every connection is
//                        a plain standalone connection. clusters is empty so
//                        RedisManager never calls connectToCluster().
// ---------------------------------------------------------------------------
$isCluster = env("REDIS_CLUSTER") === "redis";

// Cluster-level options — credentials and TLS context for RedisCluster
// constructor. Shared by every logical connection: they all talk to the same
// Valkey Serverless cluster endpoint, only the key prefix differs per store.
$clusterOptions = [
	"username" => env("REDIS_USERNAME"),
	"password" => $secret(env("REDIS_PASSWORD_FILE", env("REDIS_PASSWORD"))),
	// Backoff for Valkey Serverless transient errors during scaling events
	"max_retries" => 3,
	"backoff_algorithm" => "decorrelated_jitter",
	"backoff_base" => 500,
	"backoff_cap" => 3000,
	// SSL context passed flat to RedisCluster::__construct() — not nested under 'stream'
	"context" => [
		"verify_peer" => false,
		"verify_peer_name" => false,
	],
];

// Cluster node config — host/scheme only, no credentials and no prefix.
// PhpRedisConnector::buildClusterConnectionString() only ever reads host/port
// from the node array (everything else on it is silently ignored); the prefix
// must instead be part of the per-connection "options" — see $clusterOptionsFor().
// Same node shape for every store: they're all seeds for the same cluster.
$clusterNode = static fn(): array => [
	"host" => env("REDIS_HOST", "127.0.0.1"),
	"port" => env("REDIS_PORT", "6379"),
	"database" => 0,
	"scheme" => env("REDIS_SCHEME", "tcp"),
];

// Per-store cluster options: shared credentials/backoff plus a store-specific
// prefix, applied by PhpRedisConnector::createRedisClusterInstance() via
// setOption(Redis::OPT_PREFIX, ...). Redis Cluster does not support SELECT,
// so every store is pinned to database 0; per-store isolation is achieved via
// this prefix instead of the DB index used in standalone mode.
$clusterOptionsFor = static fn(string $store): array => [
	...$clusterOptions,
	"prefix" => $redisNamespace . ":" . $store . ":",
];

$redisConfig = [
	"client" => env("REDIS_CLIENT", "phpredis"),

	"options" => [
		// Prefix managed per-connection via each connection's own 'prefix' key.
		// Global prefix left empty to avoid double-prefixing on cluster connections.
		"prefix" => "",
	],

	// clusters is populated only in cluster mode so that RedisManager::resolve()
	// does not find e.g. clusters.cache when running locally with a standalone Redis.
	//
	// "queues" prefix — REQUIRED for environment isolation. Queue keys built by
	// RedisQueue::getQueue() ("queues:{name}", ":reserved", ":delayed", ":notify")
	// carry no environment namespace of their own, so every stack sharing the same
	// Valkey endpoint reads and writes the SAME global queue keys. Verified on
	// integration (2026-07): jobs dispatched here were drained within seconds by
	// another stack's Horizon workers even with the local worker stopped; the
	// foreign worker finds no matching row in its own job_batches ($this->batch()
	// returns null), executes the job against the WRONG database, and the batch
	// here hangs at 0% forever. phpredis applies OPT_PREFIX to all commands
	// including the KEYS arguments of EVAL, and the hash tag {name} survives the
	// prefix, so Lua slot atomicity is preserved. Horizon meta keys are NOT
	// affected: Horizon::use() clones this config for its own "horizon" connection
	// and overwrites options.prefix with the hash-tagged horizon.prefix.
	// NOTE: isolation relies on REDIS_NAMESPACE being unique per stack sharing
	// the cluster — set it explicitly (e.g. "nexi-integration") in each .env.
	//
	// "pulse" is NOT registered here: Laravel Pulse's RedisAdapter::client() only
	// accepts Redis|Predis\Client|Relay — a PhpRedisClusterConnection (RedisCluster
	// client) makes every Pulse ingest fail with a TypeError. Pulse operates on a
	// single stream key, so the standalone client below works against the Valkey
	// Serverless proxy endpoint.
	"clusters" => $isCluster
		? [
			"default" => [$clusterNode(), "options" => $clusterOptionsFor("default")],
			"session" => [$clusterNode(), "options" => $clusterOptionsFor("session")],
			"cache" => [$clusterNode(), "options" => $clusterOptionsFor("cache")],
			"queues" => [$clusterNode(), "options" => [...$clusterOptions, "prefix" => $redisNamespace . ":"]],
		]
		: [],
];

// Pulse stays standalone in BOTH modes (see the note above the clusters block).
$redisConfig["pulse"] = [
	"url" => env("REDIS_URL"),
	"host" => env("REDIS_HOST", "127.0.0.1"),
	"username" => env("REDIS_USERNAME"),
	"password" => $secret(env("REDIS_PASSWORD_FILE", env("REDIS_PASSWORD"))),
	"port" => env("REDIS_PORT", "6379"),
	"database" => env("REDIS_PULSE_DB", 0),
	"prefix" => $redisNamespace . ":pulse:",
	"scheme" => env("REDIS_SCHEME", "tcp"),
	"ssl" => ["verify_peer" => false, "verify_peer_name" => false],
];

// In standalone mode (local), register every logical connection as a plain
// connection. RedisManager checks $config[$name] before $config['clusters'][$name],
// so these keys must NOT exist in cluster mode — otherwise the standalone
// connection always wins and PhpRedisClusterConnection is never used (see
// resources/docs/1.0/PHPREDIS_VALKEY_SERVERLESS_HORIZON.md, "Problem 2").
if (!$isCluster) {
	$redisConfig["default"] = [
		"url" => env("REDIS_URL"),
		"host" => env("REDIS_HOST", "127.0.0.1"),
		"username" => env("REDIS_USERNAME"),
		"password" => $secret(env("REDIS_PASSWORD_FILE", env("REDIS_PASSWORD"))),
		"port" => env("REDIS_PORT", "6379"),
		"database" => env("REDIS_DEFAULT_DB", 0),
		"prefix" => $redisNamespace . ":default:",
		"scheme" => env("REDIS_SCHEME", "tcp"),
		"ssl" => ["verify_peer" => false, "verify_peer_name" => false],
	];

	$redisConfig["session"] = [
		"url" => env("REDIS_URL"),
		"host" => env("REDIS_HOST", "127.0.0.1"),
		"username" => env("REDIS_USERNAME"),
		"password" => $secret(env("REDIS_PASSWORD_FILE", env("REDIS_PASSWORD"))),
		"port" => env("REDIS_PORT", "6379"),
		"database" => env("REDIS_SESSION_DB", 0),
		"prefix" => $redisNamespace . ":session:",
		"scheme" => env("REDIS_SCHEME", "tcp"),
		"ssl" => ["verify_peer" => false, "verify_peer_name" => false],
	];

	$redisConfig["cache"] = [
		"url" => env("REDIS_URL"),
		"host" => env("REDIS_HOST", "127.0.0.1"),
		"username" => env("REDIS_USERNAME"),
		"password" => $secret(env("REDIS_PASSWORD_FILE", env("REDIS_PASSWORD"))),
		"port" => env("REDIS_PORT", "6379"),
		"database" => env("REDIS_CACHE_DB", 0),
		"prefix" => $redisNamespace . ":cache:",
		"scheme" => env("REDIS_SCHEME", "tcp"),
		"ssl" => ["verify_peer" => false, "verify_peer_name" => false],
	];

	$redisConfig["queues"] = [
		"url" => env("REDIS_URL"),
		"host" => env("REDIS_HOST", "127.0.0.1"),
		"username" => env("REDIS_USERNAME"),
		"password" => $secret(env("REDIS_PASSWORD_FILE", env("REDIS_PASSWORD"))),
		"port" => env("REDIS_PORT", "6379"),
		"database" => env("REDIS_QUEUE_DB", 0),
		"scheme" => env("REDIS_SCHEME", "tcp"),
		"ssl" => ["verify_peer" => false, "verify_peer_name" => false],
	];
}

return [
	/*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    */

	"default" => env("DB_CONNECTION", "sqlite"),

	/*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    */

	"connections" => [
		"sqlite" => [
			"driver" => "sqlite",
			"url" => env("DB_URL"),
			"database" => env("DB_DATABASE", database_path("database.sqlite")),
			"prefix" => "",
			"foreign_key_constraints" => env("DB_FOREIGN_KEYS", true),
			"busy_timeout" => null,
			"journal_mode" => null,
			"synchronous" => null,
		],

		"mysql" => [
			"driver" => "mysql",
			"url" => env("DB_URL"),
			"host" => env("DB_HOST", "127.0.0.1"),
			"port" => env("DB_PORT", "3306"),
			"database" => env("DB_DATABASE", "laravel"),
			"username" => env("DB_USERNAME", "root"),
			"password" => $secret(env("DB_PASSWORD_FILE", env("DB_PASSWORD", ""))),
			"unix_socket" => env("DB_SOCKET", ""),
			"charset" => env("DB_CHARSET", "utf8mb4"),
			"collation" => env("DB_COLLATION", "utf8mb4_unicode_ci"),
			"prefix" => "",
			"prefix_indexes" => true,
			"strict" => true,
			"engine" => null,
			"options" => extension_loaded("pdo_mysql")
				? array_filter([PDO::MYSQL_ATTR_SSL_CA => env("MYSQL_ATTR_SSL_CA")])
				: [],
		],

		// Sandbox connections: same MySQL server as 'mysql' but a separate schema
		// (sandbox_rays). No 'url' key on purpose: with DB_URL set it would point
		// at the real database. The 'sanduser' app user has no DDL privileges so
		// a TRUNCATE/ALTER inside a request can't break the rollback guarantee.
		"sandbox" => [
			"driver" => "mysql",
			"host" => env("SANDBOX_DB_HOST", env("DB_HOST", "127.0.0.1")),
			"port" => env("SANDBOX_DB_PORT", env("DB_PORT", "3306")),
			"database" => env("SANDBOX_DB_DATABASE", "sandbox_rays"),
			"username" => env("SANDBOX_DB_USERNAME", "sanduser"),
			"password" => $secret(env("SANDBOX_DB_PASSWORD_FILE", env("SANDBOX_DB_PASSWORD", ""))),
			"unix_socket" => env("DB_SOCKET", ""),
			"charset" => env("DB_CHARSET", "utf8mb4"),
			"collation" => env("DB_COLLATION", "utf8mb4_unicode_ci"),
			"prefix" => "",
			"prefix_indexes" => true,
			"strict" => true,
			"engine" => null,
			"options" => extension_loaded("pdo_mysql")
				? array_filter([PDO::MYSQL_ATTR_SSL_CA => env("MYSQL_ATTR_SSL_CA")])
				: [],
		],

		// Dedicated DDL user (sandadmin) — migrations and seeding only.
		"sandbox_admin" => [
			"driver" => "mysql",
			"host" => env("SANDBOX_DB_HOST", env("DB_HOST", "127.0.0.1")),
			"port" => env("SANDBOX_DB_PORT", env("DB_PORT", "3306")),
			"database" => env("SANDBOX_DB_DATABASE", "sandbox_rays"),
			"username" => env("SANDBOX_DB_ADMIN_USERNAME", "sandadmin"),
			"password" => $secret(env("SANDBOX_DB_ADMIN_PASSWORD_FILE", env("SANDBOX_DB_ADMIN_PASSWORD", ""))),
			"unix_socket" => env("DB_SOCKET", ""),
			"charset" => env("DB_CHARSET", "utf8mb4"),
			"collation" => env("DB_COLLATION", "utf8mb4_unicode_ci"),
			"prefix" => "",
			"prefix_indexes" => true,
			"strict" => true,
			"engine" => null,
			"options" => extension_loaded("pdo_mysql")
				? array_filter([PDO::MYSQL_ATTR_SSL_CA => env("MYSQL_ATTR_SSL_CA")])
				: [],
		],

		"mariadb" => [
			"driver" => "mariadb",
			"url" => env("DB_URL"),
			"host" => env("DB_HOST", "127.0.0.1"),
			"port" => env("DB_PORT", "3306"),
			"database" => env("DB_DATABASE", "laravel"),
			"username" => env("DB_USERNAME", "root"),
			"password" => $secret(env("DB_PASSWORD_FILE", env("DB_PASSWORD", ""))),
			"unix_socket" => env("DB_SOCKET", ""),
			"charset" => env("DB_CHARSET", "utf8mb4"),
			"collation" => env("DB_COLLATION", "utf8mb4_unicode_ci"),
			"prefix" => "",
			"prefix_indexes" => true,
			"strict" => true,
			"engine" => null,
			"options" => extension_loaded("pdo_mysql")
				? array_filter([PDO::MYSQL_ATTR_SSL_CA => env("MYSQL_ATTR_SSL_CA")])
				: [],
		],

		"pgsql" => [
			"driver" => "pgsql",
			"url" => env("DB_URL"),
			"host" => env("DB_HOST", "127.0.0.1"),
			"port" => env("DB_PORT", "5432"),
			"database" => env("DB_DATABASE", "laravel"),
			"username" => env("DB_USERNAME", "root"),
			"password" => $secret(env("DB_PASSWORD_FILE", env("DB_PASSWORD", ""))),
			"charset" => env("DB_CHARSET", "utf8"),
			"prefix" => "",
			"prefix_indexes" => true,
			"search_path" => "public",
			"sslmode" => "prefer",
		],

		/*
        | Assistant (AI support agent) storage.
        |
        | Conversation history is kept out of the platform database on purpose:
        | it has its own retention policy, its own growth profile and a blast
        | radius that should not overlap with the credit domain.
        |
        | Driver defaults to pgsql so the local container and the dedicated
        | database provisioned from integration onwards (alongside Aurora) share
        | one dialect, and the phase 2 migrations only ever run against one.
        |
        | Nothing reads this connection yet — config('assistant.database.connection')
        | is null by default, which keeps the assistant on the default connection.
        | Laravel resolves connections lazily, so declaring an unreachable host
        | here does not affect boot, config:cache or route:cache.
        */
		"assistant" => [
			"driver" => env("ASSISTANT_DB_DRIVER", "pgsql"),
			"url" => env("ASSISTANT_DB_URL"),
			"host" => env("ASSISTANT_DB_HOST", "127.0.0.1"),
			"port" => env("ASSISTANT_DB_PORT", "5432"),
			"database" => env("ASSISTANT_DB_DATABASE", "assistant"),
			"username" => env("ASSISTANT_DB_USERNAME", "assistant"),
			"password" => $secret(env("ASSISTANT_DB_PASSWORD_FILE", env("ASSISTANT_DB_PASSWORD", ""))),
			"charset" => env("ASSISTANT_DB_CHARSET", "utf8"),
			"prefix" => "",
			"prefix_indexes" => true,
			"search_path" => "public",
			"sslmode" => env("ASSISTANT_DB_SSLMODE", "prefer"),
		],

		"sqlsrv" => [
			"driver" => "sqlsrv",
			"url" => env("DB_URL"),
			"host" => env("DB_HOST", "localhost"),
			"port" => env("DB_PORT", "1433"),
			"database" => env("DB_DATABASE", "laravel"),
			"username" => env("DB_USERNAME", "root"),
			"password" => $secret(env("DB_PASSWORD_FILE", env("DB_PASSWORD", ""))),
			"charset" => env("DB_CHARSET", "utf8"),
			"prefix" => "",
			"prefix_indexes" => true,
		],
	],

	/*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    */

	"migrations" => [
		"table" => "migrations",
		"update_date_on_publish" => true,
	],

	/*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    */

	"redis" => $redisConfig,
];
