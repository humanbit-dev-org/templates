<?php

namespace App\Support\Cache;

use Illuminate\Cache\RedisStore;
use Illuminate\Redis\Connections\PhpRedisClusterConnection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;

/**
 * A Redis cache store whose flush deletes only its own keys.
 *
 * Laravel flushes with FLUSHDB. Locally redis.conf disables it, so cache:clear
 * silently kept every entry; on Valkey Serverless it wipes the whole cluster,
 * sessions and queues included, and the entrypoint runs it on every start.
 * SCAN and UNLINK on the store prefix work on both.
 */
class ScopedRedisStore extends RedisStore
{
    public function flush()
    {
        $connection = $this->connection();

        $connectionPrefix = match (true) {
            $connection instanceof PhpRedisConnection => $connection->_prefix(''),
            $connection instanceof PredisConnection => $connection->getOptions()->prefix ?: '',
            default => '',
        };
        $prefix = $connectionPrefix.$this->getPrefix();

        // Without a prefix the pattern would match every key on the server.
        if ($prefix === '') {
            return false;
        }

        $initial = $connection instanceof PhpRedisConnection && version_compare(phpversion('redis'), '6.1.0', '>=') ? null : '0';
        $cursor = $initial;

        do {
            $result = $connection->scan($cursor, ['match' => $prefix.'*', 'count' => 1000]);

            if (! is_array($result)) {
                break;
            }

            [$cursor, $keys] = $result;

            // SCAN returns full key names; the client adds its own prefix again.
            $keys = array_map(fn (string $key) => substr($key, strlen($connectionPrefix)), $keys ?: []);

            // One key at a time on a cluster: a multi-key UNLINK across slots is refused.
            foreach ($connection instanceof PhpRedisClusterConnection ? array_chunk($keys, 1) : array_chunk($keys, 500) as $chunk) {
                $connection->unlink(...$chunk);
            }
        } while ((string) $cursor !== (string) $initial);

        return true;
    }
}
