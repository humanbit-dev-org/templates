<?php

namespace App\Queue;

use Illuminate\Queue\Connectors\RedisConnector as BaseConnector;
use Illuminate\Support\Arr;

class ClusterRedisConnector extends BaseConnector
{
    /**
     * Establish a queue connection using ClusterRedisQueue.
     *
     * Replaces Horizon's RedisConnector to fix bulk() on PhpRedisClusterConnection.
     */
    public function connect(array $config): ClusterRedisQueue
    {
        return new ClusterRedisQueue(
            $this->redis,
            $config['queue'],
            Arr::get($config, 'connection', $this->connection),
            Arr::get($config, 'retry_after', 60),
            Arr::get($config, 'block_for', null),
            Arr::get($config, 'after_commit', null),
        );
    }
}
