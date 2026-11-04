<?php

namespace App\Logging;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\CloudWatchLogs\Exception\CloudWatchLogsException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

/**
 * Sends log records to CloudWatch Logs, one stream per channel and instance.
 *
 * Records are buffered and sent in batches: at the end of the request or job
 * (flushAll(), wired in AppServiceProvider), when the buffer fills, and on
 * destruct. CloudWatch accepts appends, so a batch never rewrites anything.
 *
 * Logging must never break a request: when CloudWatch cannot be reached the
 * batch goes to a local fallback file instead.
 */
class CloudWatchHandler extends AbstractProcessingHandler
{
	// PutLogEvents limits: 10.000 events and 1 MiB per call, 26 bytes of
	// overhead per event, 256 KiB per event.
	private const MAX_BATCH_EVENTS = 10_000;

	private const MAX_BATCH_BYTES = 1_048_576;

	private const EVENT_OVERHEAD_BYTES = 26;

	private const MAX_EVENT_BYTES = 262_144 - self::EVENT_OVERHEAD_BYTES;

	/** @var array<int, self> every live handler, for flushAll() */
	private static array $instances = [];

	/** @var list<array{timestamp: int, message: string}> */
	private array $buffer = [];

	private int $bufferBytes = 0;

	private ?CloudWatchLogsClient $client = null;

	private bool $streamReady = false;

	public function __construct(
		private readonly string $group,
		private readonly string $stream,
		int|string|Level $level = Level::Debug,
		private readonly ?string $region = null,
		private readonly int $batchSize = 500,
		private readonly ?string $fallbackPath = null,
		bool $bubble = true,
		// Tests pass a client backed by the SDK's MockHandler.
		?CloudWatchLogsClient $client = null
	) {
		parent::__construct(is_string($level) ? Level::fromName($level) : $level, $bubble);

		$this->client = $client;

		self::$instances[spl_object_id($this)] = $this;
	}

	/** Sends whatever every handler has buffered. */
	public static function flushAll(): void
	{
		foreach (self::$instances as $handler) {
			$handler->flush();
		}
	}

	protected function write(LogRecord $record): void
	{
		$message = rtrim((string) $record->formatted, "\n");

		if (strlen($message) > self::MAX_EVENT_BYTES) {
			$message = mb_strcut($message, 0, self::MAX_EVENT_BYTES - 20) . " [truncated]";
		}

		$bytes = strlen($message) + self::EVENT_OVERHEAD_BYTES;

		if ($this->bufferBytes + $bytes > self::MAX_BATCH_BYTES) {
			$this->flush();
		}

		$this->buffer[] = [
			"timestamp" => (int) $record->datetime->format("Uv"),
			"message" => $message,
		];
		$this->bufferBytes += $bytes;

		if (count($this->buffer) >= min($this->batchSize, self::MAX_BATCH_EVENTS)) {
			$this->flush();
		}
	}

	public function flush(): void
	{
		if ($this->buffer === []) {
			return;
		}

		$events = $this->buffer;
		$this->buffer = [];
		$this->bufferBytes = 0;

		// CloudWatch rejects a batch that is not in chronological order.
		usort($events, static fn(array $a, array $b): int => $a["timestamp"] <=> $b["timestamp"]);

		try {
			$this->put($events);
		} catch (Throwable $e) {
			$this->fallback($events, $e);
		}
	}

	public function close(): void
	{
		$this->flush();
		unset(self::$instances[spl_object_id($this)]);
	}

	/**
	 * @param  list<array{timestamp: int, message: string}>  $events
	 */
	private function put(array $events): void
	{
		$this->ensureStream();

		try {
			$this->client()->putLogEvents($this->payload($events));
		} catch (CloudWatchLogsException $e) {
			// The stream vanished (deleted, or the group was recreated): once more.
			if ($e->getAwsErrorCode() !== "ResourceNotFoundException") {
				throw $e;
			}

			$this->streamReady = false;
			$this->ensureStream();
			$this->client()->putLogEvents($this->payload($events));
		}
	}

	/**
	 * @param  list<array{timestamp: int, message: string}>  $events
	 * @return array<string, mixed>
	 */
	private function payload(array $events): array
	{
		return [
			"logGroupName" => $this->group,
			"logStreamName" => $this->stream,
			"logEvents" => $events,
		];
	}

	private function ensureStream(): void
	{
		if ($this->streamReady) {
			return;
		}

		try {
			$this->client()->createLogStream([
				"logGroupName" => $this->group,
				"logStreamName" => $this->stream,
			]);
		} catch (CloudWatchLogsException $e) {
			if ($e->getAwsErrorCode() !== "ResourceAlreadyExistsException") {
				throw $e;
			}
		}

		$this->streamReady = true;
	}

	private function client(): CloudWatchLogsClient
	{
		// Default credential chain: the EC2 instance role, or AWS_* env keys.
		// Short timeouts and one retry: a slow CloudWatch must not hold a
		// request open; the fallback file takes the batch instead.
		return $this->client ??= new CloudWatchLogsClient([
			"version" => "latest",
			"region" => $this->region ?: "eu-west-1",
			"retries" => 1,
			"http" => ["connect_timeout" => 2, "timeout" => 5],
		]);
	}

	/**
	 * @param  list<array{timestamp: int, message: string}>  $events
	 */
	private function fallback(array $events, Throwable $e): void
	{
		// Not through Log: a failing handler logging about itself would loop.
		error_log("CloudWatchHandler: " . $this->group . "/" . $this->stream . " not reachable: " . $e->getMessage());

		if ($this->fallbackPath === null) {
			return;
		}

		$lines = array_map(
			fn(array $event): string => "[" .
				date("Y-m-d H:i:s", intdiv($event["timestamp"], 1000)) .
				"] " .
				$this->stream .
				" " .
				$event["message"],
			$events
		);

		@file_put_contents($this->fallbackPath, implode("\n", $lines) . "\n", FILE_APPEND | LOCK_EX);
	}
}
