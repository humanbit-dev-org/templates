<?php

namespace App\Services\ApiLab;

use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;

class ApiLabTrace
{
	/** @var list<array<string, mixed>> */
	protected array $httpSteps = [];

	protected bool $active = false;

	public function activate(): void
	{
		$this->httpSteps = [];
		$this->active = true;
	}

	public function deactivate(): void
	{
		$this->active = false;
	}

	public function onRequestSending(RequestSending $event): void
	{
		if (!$this->active) {
			return;
		}

		$req = $event->request;
		$this->httpSteps[] = [
			"phase" => "pending",
			"label" => strtoupper($req->method()) . " " . $this->shortUrl((string) $req->url()),
			"request" => [
				"method" => $req->method(),
				"url" => (string) $req->url(),
				"headers" => $this->redactHeaders($req->headers()),
				"body" => $this->bodySummary($req->body()),
			],
			"started_at" => microtime(true),
		];
	}

	public function onResponseReceived(ResponseReceived $event): void
	{
		if (!$this->active) {
			return;
		}

		$req = $event->request;
		$idx = $this->findPendingStepIndex($req->method(), (string) $req->url());
		if ($idx === null) {
			return;
		}

		$step = &$this->httpSteps[$idx];
		$res = $event->response;
		$step["phase"] = $res->successful() ? "ok" : "error";
		$step["status"] = $res->status();
		$step["duration_ms"] = (int) round((microtime(true) - ($step["started_at"] ?? microtime(true))) * 1000);
		$step["response"] = [
			"status" => $res->status(),
			"headers" => $this->redactHeaders($res->headers()),
			"body_pretty" => $this->prettyBody($res->body()),
		];
		unset($step["started_at"]);
	}

	protected function findPendingStepIndex(string $method, string $url): ?int
	{
		$method = strtoupper($method);
		for ($i = count($this->httpSteps) - 1; $i >= 0; $i--) {
			$step = $this->httpSteps[$i];
			if (($step["phase"] ?? "") !== "pending") {
				continue;
			}
			if (strtoupper((string) ($step["request"]["method"] ?? "")) !== $method) {
				continue;
			}
			if ((string) ($step["request"]["url"] ?? "") === $url) {
				return $i;
			}
		}

		return null;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function httpSteps(): array
	{
		return array_values(
			array_map(
				function (array $step, int $i) {
					$step["index"] = $i + 1;
					$step["type"] = "outbound_http";

					return $step;
				},
				$this->httpSteps,
				array_keys($this->httpSteps)
			)
		);
	}

	protected function shortUrl(string $url): string
	{
		$parts = parse_url($url);
		$host = $parts["host"] ?? $url;
		$path = $parts["path"] ?? "";

		return $host . $path;
	}

	/**
	 * @param  array<string, mixed>  $headers
	 * @return array<string, string>
	 */
	protected function redactHeaders(array $headers): array
	{
		$flat = [];
		foreach ($headers as $name => $value) {
			$key = strtolower((string) $name);
			if (in_array($key, ["authorization", "cookie", "x-api-key"], true)) {
				$flat[$key] = "[REDACTED]";
			} else {
				$flat[$key] = is_array($value) ? implode(", ", $value) : (string) $value;
			}
		}
		ksort($flat);

		return $flat;
	}

	protected function bodySummary(mixed $body): ?string
	{
		if ($body === null || $body === "") {
			return null;
		}
		$str = is_string($body) ? $body : json_encode($body);
		if ($str === false) {
			return null;
		}
		if (str_contains($str, "client_secret")) {
			return "[REDACTED — contains secrets]";
		}

		return $this->prettyBody($str) ?? $str;
	}

	protected function prettyBody(?string $body): ?string
	{
		if ($body === null || $body === "") {
			return null;
		}
		$t = ltrim($body);
		if (!str_starts_with($t, "{") && !str_starts_with($t, "[")) {
			return $body;
		}
		try {
			return json_encode(
				json_decode($body, true, 512, JSON_THROW_ON_ERROR),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
			);
		} catch (\JsonException) {
			return $body;
		}
	}
}
