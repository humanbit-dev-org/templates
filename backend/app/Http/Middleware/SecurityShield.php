<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityShield
{
	// Block high-confidence keys typical of RCE/webshell (query + body)
	private const BLOCKED_KEYS_EXACT = [
		"cmd",
		"command",
		"shell",
		"exec",
		"execute",
		"system",
		"passthru",
		"shell_exec",
		"popen",
		"proc_open",
		"eval",
		"assert",
	];

	// High-confidence patterns (not common words like php/sh that cause false positives on base64)
	private const HIGH_CONFIDENCE_PATTERNS = [
		// PHP wrappers / dangerous streams
		"/\bphp:\/\/(?:input|filter|memory|temp)\b/i",
		"/\b(?:data|expect|phar|zip|glob|file|gopher):\/\//i",

		// Traversal + encoded traversal
		"/(?:\.\.\/|\.\.\\\\|%2e%2e%2f|%2e%2e%5c)/i",

		// Null byte
		'/%00|\x00/',

		// Main shell metacharacters (command injection). Note: Do not block ":" or "-" etc.
		'/(?:`|\$\(|\)\s*;|\|\||&&)/',

		// Typical redirects
		"/(?:\>\>|\s\>\s|\s\<\s)/",
	];

	private const MAX_URI_BYTES = 4096;

	private const MAX_BODY_BYTES = 20_971_520; // 20MB: coerente con post_max_size in .htaccess

	public function handle(Request $request, Closure $next): Response
	{
		// 1) URI length limit (only if Content-Length present)
		$uri = $request->getRequestUri();
		if (strlen($uri) > self::MAX_URI_BYTES) {
			return $this->deny($request, 414, "URI_TOO_LONG");
		}

		$path = "/" . ltrim($request->path(), "/");

		if (preg_match('#^/(shell|cgi-bin|wp-admin|wp-login\.php|\.env)$#i', $path)) {
			return $this->deny($request, 404, "BLOCKED_PATH");
		}

		// 2) Allowed HTTP methods
		$method = strtoupper($request->getMethod());
		$allowed = ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS", "HEAD"];
		if (!in_array($method, $allowed, true)) {
			return $this->deny($request, 405, "METHOD_NOT_ALLOWED");
		}

		// 3) Body size limit (only if Content-Length present)
		$cl = $request->headers->get("Content-Length");
		if ($cl !== null && ctype_digit((string) $cl) && (int) $cl > self::MAX_BODY_BYTES) {
			return $this->deny($request, 413, "PAYLOAD_TOO_LARGE");
		}

		// 4) Scan QUERY_STRING (where you saw ?cmd=..., ?shell=...)
		$rawQuery = (string) $request->server->get("QUERY_STRING", "");
		if ($rawQuery !== "") {
			if ($this->matchesHighConfidence($rawQuery) || $this->matchesHighConfidence($this->safeUrldecode($rawQuery))) {
				return $this->deny($request, 403, "DANGEROUS_QUERY");
			}
		}

		// 5) Block forbidden keys (query + body) — without analyzing long values (avoid false positives)
		if ($this->hasBlockedKeys($request->query->all()) || $this->hasBlockedKeys($request->request->all())) {
			return $this->deny($request, 403, "DANGEROUS_KEY");
		}

		// 6) Scan “selective” on values ONLY for small/suspicious keys ONLY if short strings - do not touch SAMLResponse/JWT/Blob
		if (
			$this->hasHighConfidenceInSelectedValues($request->query->all(), $request) ||
			$this->hasHighConfidenceInSelectedValues($request->request->all(), $request)
		) {
			return $this->deny($request, 403, "DANGEROUS_VALUE");
		}

		/** @var Response $response */
		$response = $next($request);

		// 7) Security headers (safe-by-default, does not break HTML/API)
		$response->headers->set("X-Content-Type-Options", "nosniff");
		$response->headers->set("Referrer-Policy", "no-referrer");

		$iframeEnabled = (bool) config("cors.iframe_enable");

		if ($iframeEnabled) {
			// Whitelist via CSP (only allowed_origins)
			$response->headers->remove("X-Frame-Options");

			$origins = (array) explode(",", config("cors.origins"));
			$ancestors = ["'self'"];

			foreach ($origins as $o) {
				if (!is_string($o)) {
					continue;
				}
				$o = trim($o);
				if ($o === "" || $o === "*") {
					continue;
				}

				$parts = parse_url($o);
				if (!$parts) {
					continue;
				}

				$scheme = strtolower($parts["scheme"] ?? "");
				$host = $parts["host"] ?? "";
				$port = $parts["port"] ?? null;

				if (!in_array($scheme, ["http", "https"], true)) {
					continue;
				}
				if ($host === "" || !preg_match('/^[A-Za-z0-9.-]+$/', $host)) {
					continue;
				}

				$ancestors[] = $scheme . "://" . $host . ($port !== null ? ":" . (int) $port : "");
			}

			$ancestors = array_values(array_unique($ancestors));

			$csp = "frame-ancestors " . implode(" ", $ancestors) . ";";

			// Do not overwrite existing CSP with frame-ancestors
			$existing = $response->headers->get("Content-Security-Policy");
			if (!$existing) {
				$response->headers->set("Content-Security-Policy", $csp);
			} elseif (!preg_match("/\bframe-ancestors\b/i", $existing)) {
				$sep = str_ends_with(trim($existing), ";") ? " " : "; ";
				$response->headers->set("Content-Security-Policy", $existing . $sep . $csp);
			}
		} else {
			// Default: block iframe
			$response->headers->set("X-Frame-Options", "DENY");

			// Optional: explicit via CSP (redundant but ok)
			$existing = $response->headers->get("Content-Security-Policy");
			if (!$existing) {
				$response->headers->set("Content-Security-Policy", "frame-ancestors 'none';");
			} elseif (!preg_match("/\bframe-ancestors\b/i", $existing)) {
				$sep = str_ends_with(trim($existing), ";") ? " " : "; ";
				$response->headers->set("Content-Security-Policy", $existing . $sep . "frame-ancestors 'none';");
			}
		}

		// HSTS only if HTTPS
		if ($request->isSecure()) {
			$response->headers->set("Strict-Transport-Security", "max-age=31536000; includeSubDomains");
		}

		return $response;
	}

	private function hasBlockedKeys(array $data): bool
	{
		$stack = [$data];

		while ($stack) {
			$node = array_pop($stack);

			foreach ($node as $k => $v) {
				$key = strtolower((string) $k);

				foreach (self::BLOCKED_KEYS_EXACT as $bad) {
					if ($key === $bad) {
						return true;
					}
				}

				if (is_array($v)) {
					$stack[] = $v;
				}
			}
		}

		return false;
	}

	private function hasHighConfidenceInSelectedValues(array $data, Request $request): bool
	{
		$stack = [$data];

		while ($stack) {
			$node = array_pop($stack);

			foreach ($node as $k => $v) {
				$key = strtolower((string) $k);

				// Do not analyze known blobs (SAML/JWT etc.)
				if (in_array($key, ["samlresponse", "samlrequest", "access_token", "jwt", "token"], true)) {
					continue;
				}

				if (is_array($v)) {
					$stack[] = $v;

					continue;
				}

				if (!is_string($v)) {
					continue;
				}

				// Short strings (avoid false positives on long base64/xml)
				if (strlen($v) > 512) {
					continue;
				}

				$vv = $v;
				if ($this->matchesHighConfidence($vv) || $this->matchesHighConfidence($this->safeUrldecode($vv))) {
					return true;
				}
			}
		}

		return false;
	}

	private function matchesHighConfidence(string $s): bool
	{
		if ($s === "") {
			return false;
		}

		$s = preg_replace('/[\x00-\x1F\x7F]+/u', " ", $s) ?? $s;

		foreach (self::HIGH_CONFIDENCE_PATTERNS as $rx) {
			if (preg_match($rx, $s)) {
				return true;
			}
		}

		return false;
	}

	private function safeUrldecode(string $s): string
	{
		$prev = $s;
		for ($i = 0; $i < 2; $i++) {
			$next = rawurldecode($prev);
			if ($next === $prev) {
				break;
			}
			$prev = $next;
		}

		return $prev;
	}

	private function deny(Request $request, int $status, string $reason): Response
	{
		logger()->warning("SecurityShield blocked request", [
			"reason" => $reason,
			"status" => $status,
			"ip" => $request->ip(),
			"method" => $request->getMethod(),
			"path" => $request->path(),
		]);

		return response()->json(["message" => "Forbidden"], $status);
	}
}
