<?php

namespace App\Services\ApiLab;

use Illuminate\Routing\Route;

class ApiLabQuality
{
	/**
	 * @param  array<string, mixed>  $endpoint
	 * @param  array<string, mixed>  $metrics
	 * @param  list<array<string, mixed>>  $steps
	 * @return array{score: int, grade: string, dimensions: array<string, array{score: int, items: list<array{level: string, text: string}>}>}
	 */
	public function analyze(Route $route, array $endpoint, array $metrics, array $steps, ?string $handlerSource): array
	{
		$dimensions = [
			"security" => $this->security($route, $handlerSource),
			"performance" => $this->performance($metrics, $steps),
			"practices" => $this->practices($endpoint, $handlerSource),
			"reliability" => $this->reliability($metrics, $steps),
		];

		$scores = array_map(fn(array $d) => $d["score"], $dimensions);
		$score = (int) round(array_sum($scores) / max(count($scores), 1));

		return [
			"score" => $score,
			"grade" => $this->grade($score),
			"dimensions" => $dimensions,
		];
	}

	/**
	 * @return array{score: int, items: list<array{level: string, text: string}>}
	 */
	protected function security(Route $route, ?string $source): array
	{
		$items = [];
		$score = 100;

		$middleware = collect($route->gatherMiddleware())->filter(fn($m) => is_string($m))->values();

		$stack = $middleware->implode(" → ");
		if ($stack !== "") {
			$items[] = ["level" => "info", "text" => "Middleware stack: " . $stack];
		}

		$shields = $middleware
			->filter(fn(string $m) => str_starts_with($m, "shield:"))
			->map(fn(string $m) => substr($m, strlen("shield:")))
			->values();

		if ($shields->isNotEmpty()) {
			foreach ($shields as $tool) {
				$policy = config("restricted_tools.tools.{$tool}");
				if (is_array($policy)) {
					$checks = implode(" OR ", $policy["checks"] ?? []);
					$roles = isset($policy["roles"]) && is_array($policy["roles"]) ? implode(", ", $policy["roles"]) : null;
					$detail = "RestrictedAccessShield:{$tool} [{$checks}]";
					if ($roles) {
						$detail .= " roles:{$roles}";
					}
					$items[] = ["level" => "ok", "text" => $detail];
				} else {
					$items[] = ["level" => "ok", "text" => "RestrictedAccessShield:{$tool} (internal tool policy)."];
				}
			}
		}

		$hasSanctum = $middleware->contains(fn(string $m) => str_contains($m, "auth:sanctum") || $m === "auth:sanctum");
		$hasSessionAuth = $middleware->contains(fn(string $m) => $m === "auth" || str_starts_with($m, "auth:"));

		if ($middleware->contains("tenant.token")) {
			$items[] = [
				"level" => "ok",
				"text" => "B2B tenant Bearer required (API Lab auto-provisions when Authorization is omitted).",
			];
		}

		if ($hasSanctum) {
			$items[] = ["level" => "ok", "text" => "Sanctum bearer authentication required on this route."];
		} elseif ($hasSessionAuth) {
			$items[] = ["level" => "ok", "text" => "Session authentication middleware is present."];
		} elseif ($shields->isEmpty()) {
			$items[] = [
				"level" => "warn",
				"text" => "No shield:* or auth middleware — confirm this route is intentionally public.",
			];
			$score -= 25;
		}

		if ($middleware->contains(fn(string $m) => str_contains($m, "throttle"))) {
			$items[] = ["level" => "ok", "text" => "Rate limiting (throttle) is configured."];
		} elseif ($shields->isEmpty()) {
			$items[] = ["level" => "info", "text" => "Consider throttle middleware for brute-force protection."];
			$score -= 8;
		}

		if ($source !== null && $source !== "") {
			if (preg_match('/\$request->validate\s*\(/', $source)) {
				$items[] = ["level" => "ok", "text" => "Input validation detected in the handler."];
			} else {
				$items[] = ["level" => "warn", "text" => 'No $request->validate() found — validate untrusted input.'];
				$score -= 15;
			}

			if (preg_match("/DB::raw\s*\(|whereRaw\s*\(/i", $source)) {
				$items[] = ["level" => "warn", "text" => "Raw SQL fragments detected — ensure parameters are bound."];
				$score -= 12;
			}

			if (preg_match("/\beval\s*\(|\bshell_exec\s*\(|\bsystem\s*\(/i", $source)) {
				$items[] = ["level" => "fail", "text" => "Dangerous dynamic execution functions detected in source."];
				$score -= 40;
			}
		}

		return ["score" => max(0, min(100, $score)), "items" => $items];
	}

	/**
	 * @param  list<array<string, mixed>>  $steps
	 * @return array{score: int, items: list<array{level: string, text: string}>}
	 */
	protected function performance(array $metrics, array $steps): array
	{
		$items = [];
		$score = 100;
		$total = (int) ($metrics["duration_ms"] ?? 0);
		$sql = (int) ($metrics["sql_queries"] ?? 0);
		$outbound = (int) ($metrics["outbound_calls"] ?? 0);

		if ($total > 2000) {
			$items[] = ["level" => "fail", "text" => "Total duration {$total} ms exceeds 2 s — investigate bottlenecks."];
			$score -= 35;
		} elseif ($total > 800) {
			$items[] = ["level" => "warn", "text" => "Total duration {$total} ms is high — profile handler and IO."];
			$score -= 18;
		} else {
			$items[] = ["level" => "ok", "text" => "Total duration {$total} ms is within a healthy range."];
		}

		if ($sql > 25) {
			$items[] = ["level" => "fail", "text" => "{$sql} SQL queries — likely N+1; use eager loading."];
			$score -= 30;
		} elseif ($sql > 12) {
			$items[] = ["level" => "warn", "text" => "{$sql} SQL queries — review query count."];
			$score -= 15;
		} elseif ($sql > 0) {
			$items[] = ["level" => "ok", "text" => "{$sql} SQL queries executed."];
		} else {
			$items[] = ["level" => "info", "text" => "No SQL queries recorded for this request."];
		}

		if ($outbound > 3) {
			$items[] = [
				"level" => "warn",
				"text" => "{$outbound} outbound HTTP calls — consider consolidating external IO.",
			];
			$score -= 12;
		} elseif ($outbound > 0) {
			$items[] = ["level" => "ok", "text" => "{$outbound} outbound HTTP call(s) traced."];
		}

		$bytes = (int) ($metrics["response_bytes"] ?? 0);
		if ($bytes > 512000) {
			$items[] = [
				"level" => "warn",
				"text" => "Response payload is large (" . round($bytes / 1024) . " KB) — paginate or trim fields.",
			];
			$score -= 10;
		}

		return ["score" => max(0, min(100, $score)), "items" => $items];
	}

	/**
	 * @return array{score: int, items: list<array{level: string, text: string}>}
	 */
	protected function practices(array $endpoint, ?string $source): array
	{
		$items = [];
		$score = 100;

		if (!empty($endpoint["inputs"])) {
			$items[] = ["level" => "ok", "text" => 'Request inputs are read via $request->input() / validate().'];
		}

		if ($source !== null && preg_match("/\btry\s*\{/", $source)) {
			$items[] = ["level" => "ok", "text" => "try/catch present for controlled error handling."];
		} else {
			$items[] = ["level" => "info", "text" => "No try/catch in handler — ensure exceptions map to safe API errors."];
			$score -= 5;
		}

		if ($source !== null && preg_match("/Http::(get|post|put|patch|delete)\s*\(/i", $source)) {
			if (preg_match("/Http::\w+\([^)]*timeout/i", $source)) {
				$items[] = ["level" => "ok", "text" => "Outbound HTTP calls specify timeouts."];
			} else {
				$items[] = [
					"level" => "warn",
					"text" => "Outbound HTTP without explicit timeout — set ->timeout() to avoid hangs.",
				];
				$score -= 10;
			}
		}

		if ($source !== null && preg_match('/return\s+response\s*\(\s*\$/', $source)) {
			$items[] = ["level" => "info", "text" => "Proxied raw response body — ensure upstream errors are normalized."];
			$score -= 5;
		}

		return ["score" => max(0, min(100, $score)), "items" => $items];
	}

	/**
	 * @param  list<array<string, mixed>>  $steps
	 * @return array{score: int, items: list<array{level: string, text: string}>}
	 */
	protected function reliability(array $metrics, array $steps): array
	{
		$items = [];
		$score = 100;

		$failed = collect($steps)->filter(fn($s) => ($s["phase"] ?? "") === "error")->count();
		if ($failed > 0) {
			$items[] = ["level" => "fail", "text" => "{$failed} pipeline step(s) returned an error status."];
			$score -= 40;
		} else {
			$items[] = ["level" => "ok", "text" => "All traced pipeline steps completed successfully."];
		}

		$handler = collect($steps)->firstWhere("type", "handler");
		$status = (int) ($handler["status"] ?? 0);
		if ($status >= 500) {
			$items[] = ["level" => "fail", "text" => "Handler returned HTTP {$status}."];
			$score -= 25;
		} elseif ($status >= 400) {
			$items[] = ["level" => "warn", "text" => "Handler returned HTTP {$status} — verify client input or auth."];
			$score -= 12;
		}

		return ["score" => max(0, min(100, $score)), "items" => $items];
	}

	protected function grade(int $score): string
	{
		return match (true) {
			$score >= 90 => "A",
			$score >= 75 => "B",
			$score >= 60 => "C",
			$score >= 45 => "D",
			default => "F",
		};
	}
}
