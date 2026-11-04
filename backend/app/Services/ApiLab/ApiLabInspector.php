<?php

namespace App\Services\ApiLab;

use Illuminate\Routing\Route;
use ReflectionFunction;
use ReflectionMethod;

class ApiLabInspector
{
	/**
	 * @return array<string, mixed>
	 */
	public function inspect(Route $route): array
	{
		$uses = $route->getAction("uses");
		if ($uses instanceof \Closure) {
			return $this->inspectClosure(new ReflectionFunction($uses), $route);
		}

		$controller = $route->getControllerClass();
		$method = $route->getActionMethod();

		if (!$controller || !$method || $method === "Closure") {
			return $this->emptySpec();
		}

		try {
			$reflection = new ReflectionMethod($controller, $method);
		} catch (\ReflectionException) {
			return $this->emptySpec();
		}

		$source = $this->methodSource($reflection);
		$inputs = $this->extractInputs($source);
		$internalCalls = $this->extractInternalCalls($source);
		$httpCalls = array_values(
			array_unique(
				array_merge(
					$this->extractHttpCalls($source),
					$this->extractHttpCallsFromInternal($controller, $internalCalls)
				)
			)
		);
		$docSummary = $this->docSummary($reflection);

		$sampleBody = $this->buildSampleBody($inputs);
		$usesGet = in_array("GET", $route->methods(), true);

		return [
			"controller" => class_basename($controller) . "@" . $method,
			"doc_summary" => $docSummary,
			"inputs" => $inputs,
			"internal_pipeline" => $internalCalls,
			"http_hints" => $httpCalls,
			"sample_body" => $sampleBody,
			"show_body" => !$usesGet && $inputs !== [],
			"show_query" => $usesGet && $inputs !== [],
			"protocol_lines" => $this->buildProtocolLines($reflection, $inputs, $internalCalls, $httpCalls, $usesGet),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function emptySpec(): array
	{
		return [
			"controller" => null,
			"doc_summary" => null,
			"inputs" => [],
			"internal_pipeline" => [],
			"http_hints" => [],
			"sample_body" => null,
			"show_body" => false,
			"show_query" => false,
			"protocol_lines" => [],
		];
	}

	public function methodSource(ReflectionMethod $method): string
	{
		return $this->sourceFromReflection($method);
	}

	public function closureSource(ReflectionFunction $function): string
	{
		return $this->isolateClosureSnippet($this->sourceFromReflection($function));
	}

	/**
	 * Strip Route::… wrapper and ->name() from reflected closure lines (PHP includes them in the span).
	 */
	public function isolateClosureSnippet(string $raw): string
	{
		$lines = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];
		if ($lines === []) {
			return "";
		}

		if (preg_match("/\bfunction\b/", $lines[0], $match, PREG_OFFSET_CAPTURE)) {
			$lines[0] = substr($lines[0], $match[0][1]);
		}

		$last = count($lines) - 1;
		$lines[$last] =
			preg_replace('/\}\s*\)(?:\s*->\s*name\s*\(\s*[\'"][^\'"]*[\'"]\s*\))?\s*;?\s*$/', "}", rtrim($lines[$last])) ??
			$lines[$last];

		return implode("\n", $lines);
	}

	/**
	 * @param  ReflectionMethod|ReflectionFunction  $reflection
	 */
	protected function sourceFromReflection(ReflectionMethod|ReflectionFunction $reflection): string
	{
		$filename = $reflection->getFileName();
		if (!$filename || !is_readable($filename)) {
			return "";
		}

		$lines = file($filename, FILE_IGNORE_NEW_LINES);
		if ($lines === false) {
			return "";
		}

		$start = $reflection->getStartLine() - 1;
		$end = $reflection->getEndLine();
		$slice = array_slice($lines, $start, $end - $start);

		return implode("\n", $slice);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function inspectClosure(ReflectionFunction $function, Route $route): array
	{
		$source = $this->closureSource($function);
		$inputs = $this->extractInputs($source);
		$httpCalls = $this->extractHttpCalls($source);
		$usesGet = in_array("GET", $route->methods(), true);
		$routeName = $route->getName() ?? "unnamed";

		$protocolLines = [
			"Handler: route closure · " . $routeName . " (fixed in config/api_lab.php — not editable here)",
			"Sandbox runs the anonymous function body only; URI and route name stay as registered.",
		];

		if (preg_match('/\$request->query/', $source)) {
			$protocolLines[] = "Uses query string — extra query params may change behaviour (e.g. abort 404).";
		}

		if ($httpCalls !== []) {
			$protocolLines[] = "Outbound HTTP detected in source: " . implode("; ", $httpCalls);
		}

		if ($inputs === []) {
			$protocolLines[] = $usesGet
				? 'No query/body fields detected from $request->input() / validate().'
				: 'No body fields detected from $request->input() / validate().';
		} else {
			$names = implode(", ", array_map(fn($i) => $i["name"] . ($i["required"] ? "*" : ""), $inputs));
			$protocolLines[] = ($usesGet ? "Query" : "Body") . " fields (* required): " . $names;
		}

		$protocolLines[] = "Each outbound HTTP call and the final handler response appear as separate steps after transmit.";

		return [
			"controller" => "closure · " . $routeName,
			"doc_summary" => null,
			"inputs" => $inputs,
			"internal_pipeline" => [],
			"http_hints" => $httpCalls,
			"sample_body" => $this->buildSampleBody($inputs),
			"show_body" => !$usesGet && $inputs !== [],
			"show_query" => $usesGet && $inputs !== [],
			"protocol_lines" => $protocolLines,
		];
	}

	/**
	 * @return list<array{name: string, required: bool, hint: string}>
	 */
	protected function extractInputs(string $source): array
	{
		$inputs = [];

		if (preg_match_all('/\$request->input\(\s*[\'"]([^\'"]+)[\'"]/', $source, $m)) {
			foreach (array_unique($m[1]) as $name) {
				if ($name === "access_token") {
					continue;
				}
				$inputs[$name] = [
					"name" => $name,
					"required" => true,
					"hint" => 'From request body/query via $request->input()',
				];
			}
		}

		if (preg_match('/\$request->validate\(\s*\[(.*?)\]\s*\)/s', $source, $vm)) {
			if (preg_match_all('/[\'"]([^\'"]+)[\'"]\s*=>/', $vm[1], $rules)) {
				foreach ($rules[1] as $name) {
					$inputs[$name] = [
						"name" => $name,
						"required" =>
							str_contains($vm[1], "'{$name}' => 'required") ||
							str_contains($vm[1], "\"{$name}\" => 'required"),
						"hint" => "Validation rule on controller",
					];
				}
			}
		}

		return array_values($inputs);
	}

	/**
	 * @return list<string>
	 */
	protected function extractInternalCalls(string $source): array
	{
		if (!preg_match_all('/\$this->(\w+)\s*\(/', $source, $m)) {
			return [];
		}

		return array_values(array_unique($m[1]));
	}

	/**
	 * @return list<string>
	 */
	protected function extractHttpCalls(string $source): array
	{
		$hints = [];
		if (
			preg_match_all(
				'/Http::[\s\S]*?->(get|post|put|patch|delete)\(\s*[\'"]([^\'"]+)[\'"]/i',
				$source,
				$m,
				PREG_SET_ORDER
			)
		) {
			foreach ($m as $match) {
				$hints[] = strtoupper($match[1]) . " " . $match[2];
			}
		}

		return $hints;
	}

	/**
	 * @param  list<string>  $internalMethods
	 * @return list<string>
	 */
	protected function extractHttpCallsFromInternal(string $controller, array $internalMethods): array
	{
		$hints = [];
		foreach ($internalMethods as $methodName) {
			try {
				$reflection = new ReflectionMethod($controller, $methodName);
			} catch (\ReflectionException) {
				continue;
			}
			$hints = array_merge($hints, $this->extractHttpCalls($this->methodSource($reflection)));
		}

		return $hints;
	}

	protected function docSummary(ReflectionMethod $method): ?string
	{
		$doc = $method->getDocComment();
		if (!$doc) {
			return null;
		}
		if (preg_match("/@summary\s+(.+)/", $doc, $m)) {
			return trim($m[1]);
		}
		if (preg_match('/^\s*\*\s+([^@\*\n].+)$/m', $doc, $m)) {
			return trim($m[1]);
		}

		return null;
	}

	/**
	 * @param  list<array{name: string, required: bool, hint: string}>  $inputs
	 * @return array<string, mixed>|null
	 */
	protected function buildSampleBody(array $inputs): ?array
	{
		if ($inputs === []) {
			return null;
		}
		$body = [];
		foreach ($inputs as $input) {
			$name = $input["name"];
			$body[$name] = $name === "payload" ? ["…" => "Nexi Cashberry payload object"] : "";
		}

		return $body;
	}

	/**
	 * @param  list<array{name: string, required: bool, hint: string}>  $inputs
	 * @param  list<string>  $internal
	 * @param  list<string>  $http
	 * @return list<string>
	 */
	protected function buildProtocolLines(
		ReflectionMethod $method,
		array $inputs,
		array $internal,
		array $http,
		bool $usesGet
	): array {
		$lines = [];
		$lines[] = "Handler: " . class_basename($method->getDeclaringClass()->getName()) . "@" . $method->getName();

		if ($internal !== []) {
			$lines[] =
				"Internal pipeline: " .
				implode(" → ", $internal) .
				" (runs inside handler; outbound HTTP traced automatically).";
		}

		if ($http !== []) {
			$lines[] = "Outbound HTTP detected in source: " . implode("; ", $http);
		}

		if ($inputs === []) {
			$lines[] = $usesGet
				? "No request inputs detected — query/body likely unused."
				: 'No body fields detected from $request->input() / validate().';
		} else {
			$names = implode(", ", array_map(fn($i) => $i["name"] . ($i["required"] ? "*" : ""), $inputs));
			$lines[] = ($usesGet ? "Query" : "Body") . " fields (* required): " . $names;
		}

		$lines[] = "Each outbound HTTP call and the final handler response appear as separate steps after transmit.";

		return $lines;
	}
}
