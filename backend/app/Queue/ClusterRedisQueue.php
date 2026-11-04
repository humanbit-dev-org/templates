<?php

namespace App\Queue;

use Illuminate\Queue\LuaScripts;
use Illuminate\Redis\Connections\PhpRedisClusterConnection;
use Illuminate\Support\Facades\Log;
use Laravel\Horizon\Events\JobPending;
use Laravel\Horizon\Events\JobPushed;
use Laravel\Horizon\JobPayload;
use Laravel\Horizon\RedisQueue as BaseHorizonQueue;
use RuntimeException;
use Throwable;

class ClusterRedisQueue extends BaseHorizonQueue
{
	/**
	 * Override bulk() to use plain iteration on cluster connections.
	 *
	 * On PhpRedisClusterConnection, MULTI/EXEC transactions silently drop EVAL
	 * commands (used by pushRaw via LuaScripts::push()), causing batch job dispatch
	 * to write nothing to Redis. Plain iteration is safe here — atomicity is not
	 * required for batch dispatch since each job is independent.
	 */
	public function bulk($jobs, $data = "", $queue = null): void
	{
		$connection = $this->getConnection();

		if ($connection instanceof PhpRedisClusterConnection) {
			// Skip transaction/pipeline on cluster — EVAL inside MULTI/EXEC is dropped
			foreach ((array) $jobs as $job) {
				try {
					if (isset($job->delay)) {
						$this->later($job->delay, $job, $data, $queue);
					} else {
						$this->push($job, $data, $queue);
					}
				} catch (Throwable $e) {
					Log::critical("ClusterRedisQueue::bulk — push failed on cluster connection", [
						"queue" => $queue ?: $this->default,
						"job" => is_object($job) ? get_class($job) : (string) $job,
						"exception" => get_class($e),
						"message" => $e->getMessage(),
					]);

					throw $e;
				}
			}
		} else {
			parent::bulk($jobs, $data, $queue);
		}
	}

	/**
	 * Override pushRaw() to verify the EVAL result on cluster connections.
	 *
	 * Both the framework and Horizon ignore the return value of the push EVAL:
	 * phpredis returns false (with the detail in getLastError()) when the command
	 * fails without throwing — e.g. during Valkey Serverless scaling events. The
	 * stock implementation then reports the job id as if the push succeeded,
	 * producing batches that exist in job_batches while the Redis queue stays
	 * empty, with no error anywhere. Here a false EVAL result becomes a logged,
	 * thrown exception so the dispatcher (e.g. Bus::batch()) fails loudly and
	 * can retry instead of hanging at 0%.
	 *
	 * Success is indistinguishable from Lua nil (LuaScripts::push() returns
	 * nothing), which phpredis surfaces as null — so only a strict false with a
	 * non-empty getLastError() is treated as failure.
	 */
	public function pushRaw($payload, $queue = null, array $options = [])
	{
		$connection = $this->getConnection();

		if (!$connection instanceof PhpRedisClusterConnection) {
			return parent::pushRaw($payload, $queue, $options);
		}

		// Mirrors Laravel\Horizon\RedisQueue::pushRaw(), adding the eval check.
		$payload = (new JobPayload($payload))->prepare($this->lastPushed);

		$this->event($this->getQueue($queue), new JobPending($payload->value));

		$queueKey = $this->getQueueRedisKey($queue);

		$result = $connection->eval(LuaScripts::push(), 2, $queueKey, $queueKey . ":notify", $payload->value);

		if ($result === false) {
			$lastError = null;

			try {
				$client = $connection->client();
				$lastError = method_exists($client, "getLastError") ? $client->getLastError() : null;
			} catch (Throwable) {
				// Diagnostic only — never mask the push failure below.
			}

			if ($lastError !== null && $lastError !== "") {
				Log::critical("ClusterRedisQueue::pushRaw — EVAL push failed, job NOT written to Redis", [
					"queue_key" => $queueKey,
					"job_id" => $payload->id(),
					"redis_error" => $lastError,
				]);

				throw new RuntimeException("Redis EVAL push failed for queue [{$queueKey}]: {$lastError}");
			}

			// false without a client error is ambiguous (some phpredis builds map
			// Lua nil to false): log for correlation but do not fail the dispatch.
			Log::warning("ClusterRedisQueue::pushRaw — EVAL returned false without a client error", [
				"queue_key" => $queueKey,
				"job_id" => $payload->id(),
			]);
		}

		$this->event($this->getQueue($queue), new JobPushed($payload->value));

		return $payload->id();
	}
}
