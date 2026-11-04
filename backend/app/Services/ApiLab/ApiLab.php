<?php

namespace App\Services\ApiLab;

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApiLab
{
	public const LAB_REQUEST_HEADER = "X-Api-Lab-Request";

	public function __construct(
		protected Kernel $kernel,
		protected ApiLabInspector $inspector,
		protected ApiLabMetrics $metrics,
		protected ApiLabQuality $quality
	) {}

	/**
	 * Categories, each with its endpoints. A category that declares "groups"
	 * (e.g. one per bank) also gets them as a second level: the endpoints of
	 * every group, in the declared order, and the ones with no group apart.
	 *
	 * @return Collection<string, array{key: string, label: string, file: string, endpoints: Collection<int, array<string, mixed>>, groups: Collection<int, array{key: string, label: string, endpoints: Collection<int, array<string, mixed>>}>, ungrouped: Collection<int, array<string, mixed>>}>
	 */
	public function groupedEndpoints(): Collection
	{
		return collect(config("api_lab.categories", []))
			->map(function (array $category, string $key) {
				$items = $this->endpoints()->filter(fn(array $e) => ($e["category_key"] ?? "") === $key)->values();

				$file = $category["file"] ?? "";

				$groups = collect($category["groups"] ?? [])
					->map(
						fn(array $group, string $groupKey) => [
							"key" => $key . "-" . $groupKey,
							"label" => $group["label"] ?? $groupKey,
							"endpoints" => $items->filter(fn(array $e) => ($e["group_key"] ?? null) === $groupKey)->values(),
						]
					)
					->filter(fn(array $group) => $group["endpoints"]->isNotEmpty())
					->values();

				return [
					"key" => $key,
					"label" => $category["label"] ?? $key,
					"file" => is_array($file) ? implode(", ", $file) : $file,
					"endpoints" => $items,
					"groups" => $groups,
					"ungrouped" => $items
						->reject(
							fn(array $e) => $groups->contains(
								fn(array $g) => $g["key"] === $key . "-" . ($e["group_key"] ?? "")
							)
						)
						->values(),
				];
			})
			->filter(fn(array $group) => $group["endpoints"]->isNotEmpty());
	}

	/**
	 * @return Collection<int, array<string, mixed>>
	 */
	public function endpoints(): Collection
	{
		return collect(config("api_lab.endpoints", []))->map(fn(array $entry) => $this->resolve($entry))->filter()->values();
	}

	public function find(string $key): ?array
	{
		return $this->endpoints()->firstWhere("key", $key);
	}

	/**
	 * @param  array<string, mixed>  $entry
	 * @return array<string, mixed>|null
	 */
	protected function resolve(array $entry): ?array
	{
		$routeName = $entry["route_name"] ?? null;
		$categoryKey = $entry["category"] ?? null;

		if (!is_string($routeName) || !is_string($categoryKey)) {
			return null;
		}

		$category = config("api_lab.categories.{$categoryKey}");
		if (!is_array($category)) {
			return null;
		}

		// Una categoria puo' coprire piu' file di route (es. le inbound stanno sia in
		// integrations.php sia in sso.php): tutti devono essere allowed, e la route deve
		// essere dichiarata in almeno uno di essi.
		$routeFiles = array_values(array_filter((array) ($category["file"] ?? []), "is_string"));
		$allowed = config("api_lab.allowed_route_files", []);
		if ($routeFiles === [] || array_diff($routeFiles, $allowed) !== []) {
			return null;
		}

		$route = RouteFacade::getRoutes()->getByName($routeName);
		$declaredInAny = array_reduce(
			$routeFiles,
			fn(bool $carry, string $f) => $carry || $this->routeDeclaredIn($routeName, $f),
			false
		);
		if (!$route instanceof Route || !$declaredInAny) {
			return null;
		}

		$spec = $this->inspector->inspect($route);
		$methods = collect($route->methods())->reject(fn(string $m) => $m === "HEAD")->values()->all();
		$defaultMethod = $this->defaultMethod($methods);
		$usesGet = in_array("GET", $methods, true);
		$routeMiddleware = collect($route->gatherMiddleware())->filter(fn($m) => is_string($m));
		$needsSanctum = $routeMiddleware->contains(fn(string $m) => str_contains($m, "auth:sanctum"));
		$needsTenantToken = $routeMiddleware->contains("tenant.token");
		$uses = $route->getAction("uses");
		$handlerKind = "method";
		$controller = $route->getControllerClass();
		$handlerMethod = $route->getActionMethod();
		$handlerSource = null;
		$sourceFile = null;

		if ($uses instanceof \Closure) {
			$handlerKind = "closure";
			$reflection = new \ReflectionFunction($uses);
			$handlerSource = $this->inspector->closureSource($reflection);
			$sourceFile = $reflection->getFileName() . ":" . $reflection->getStartLine();
			$controller = null;
			$handlerMethod = null;
		} elseif ($controller && $handlerMethod && $handlerMethod !== "Closure") {
			try {
				$reflection = new \ReflectionMethod($controller, $handlerMethod);
				$handlerSource = $this->inspector->methodSource($reflection);
				$sourceFile = $reflection->getFileName();
			} catch (\ReflectionException) {
				//
			}
		}

		return [
			"key" => $entry["key"] ?? str_replace(".", "-", $routeName),
			"category_key" => $categoryKey,
			"category_label" => $category["label"] ?? $categoryKey,
			"group_key" => is_string($entry["group"] ?? null) ? $entry["group"] : null,
			"route_name" => $routeName,
			"display_name" => $this->displayRouteName($routeName),
			"catalog_title" => $this->catalogTitle($routeName, $spec["doc_summary"] ?? null),
			"auth_hints" => $this->catalogAuthHints($needsSanctum, $needsTenantToken, $categoryKey),
			"uri" => "/" . $route->uri(),
			"handler_kind" => $handlerKind,
			"controller_class" => $controller,
			"handler_method" => $handlerMethod,
			"handler_source" => $handlerSource,
			"source_file" => $sourceFile,
			"methods" => $methods,
			"method_selectable" => count($methods) > 1,
			"default_method" => $defaultMethod,
			"parameter_names" => $route->parameterNames(),
			"path_param_defaults" => $this->pathParamDefaults($entry, $route),
			"body_encoding" => $this->bodyEncoding($entry, $routeName),
			"needs_sanctum" => $needsSanctum,
			"needs_tenant_token" => $needsTenantToken,
			// Forza il campo query se la entry pre-compila dei parametri (l'inspector non li rileva
			// quando sono letti dinamicamente via $request->query(), es. nexi.sso.request/external_id).
			"show_query" => $spec["show_query"] || !empty($entry["sample_query"]),
			"show_body" => $spec["show_body"] || (!$usesGet && !empty($entry["sample_body"])),
			"sample_query" => $entry["sample_query"] ?? [],
			"sample_body" => $entry["sample_body"] ?? $spec["sample_body"],
			"protocol_lines" => $spec["protocol_lines"],
			"doc_summary" => $spec["doc_summary"],
			"inputs" => $spec["inputs"],
			"internal_pipeline" => $spec["internal_pipeline"],
			"controller" => $spec["controller"],
		];
	}

	protected function routeDeclaredIn(string $routeName, string $routeFile): bool
	{
		$path = base_path("routes/" . $routeFile);
		if (!is_readable($path)) {
			return false;
		}
		$contents = file_get_contents($path);
		if ($contents === false) {
			return false;
		}

		return str_contains($contents, "->name('{$routeName}')") || str_contains($contents, '->name("' . $routeName . '")');
	}

	/**
	 * @param  list<string>  $methods
	 */
	protected function defaultMethod(array $methods): string
	{
		foreach (["GET", "POST", "PUT", "PATCH", "DELETE"] as $preferred) {
			if (in_array($preferred, $methods, true)) {
				return $preferred;
			}
		}

		return $methods[0] ?? "GET";
	}

	public function route(array $endpoint): Route
	{
		$route = RouteFacade::getRoutes()->getByName($endpoint["route_name"]);
		if (!$route instanceof Route) {
			throw new InvalidArgumentException("Route [{$endpoint["route_name"]}] not found.");
		}

		return $route;
	}

	/**
	 * @param  array<string, string>  $pathParams
	 * @param  array<string, mixed>  $query
	 * @param  array<string, string>  $requestHeaders
	 * @return array<string, mixed>
	 */
	public function run(
		array $endpoint,
		string $method,
		array $pathParams,
		array $query,
		?string $jsonBody,
		array $requestHeaders,
		?string $actingAsUserId,
		Request $parent,
		ApiLabTrace $trace
	): array {
		$route = $this->route($endpoint);
		$method = strtoupper($method);
		$uri = $this->buildUri($route, $pathParams);

		$decodedBody = $this->decodeBody($jsonBody, $method);
		$bodyEncoding = (string) ($endpoint["body_encoding"] ?? "json");
		$payload = $method === "GET" ? $query : $decodedBody ?? [];
		$payload = $this->applyInboundOAuthCredentialsIfNeeded($route, $payload);
		$content = null;

		if (in_array($method, ["POST", "PUT", "PATCH"], true) && $jsonBody !== null && $bodyEncoding === "json") {
			$content = $jsonBody;
		}

		$sub = Request::create($uri, $method, $payload, $parent->cookies->all(), [], $parent->server->all(), $content);
		$sub->headers->set("Accept", "application/json");
		if ($bodyEncoding === "form" && in_array($method, ["POST", "PUT", "PATCH"], true)) {
			$sub->headers->set("Content-Type", "application/x-www-form-urlencoded");
		} elseif ($content !== null) {
			$sub->headers->set("Content-Type", "application/json");
		}
		foreach ($requestHeaders as $name => $value) {
			if (is_string($name) && is_string($value) && $value !== "") {
				$sub->headers->set($name, $value);
			}
		}
		$sub->headers->set(self::LAB_REQUEST_HEADER, "1");
		if ($parent->hasSession()) {
			$sub->setLaravelSession($parent->session());
			$sub->headers->set("X-CSRF-TOKEN", $parent->session()->token());
		}

		$this->applyTenantBearerIfNeeded($route, $sub);
		$this->authenticate($route, $actingAsUserId, $sub);

		$inboundHeaders = $this->flatHeaders($sub->headers->all());
		$inboundBodyPretty =
			$content !== null
				? $this->pretty($content)
				: ($payload !== [] && $method !== "GET"
					? json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
					: null);

		$this->metrics->start();
		$trace->activate();
		$started = microtime(true);
		$exception = null;
		$response = null;

		// API Lab non deve mai persistere: le chiamate mutating girano dentro una transazione
		// di cui facciamo SEMPRE rollback (dry-run). Le eventuali transazioni interne del
		// controller diventano savepoint e vengono annullate insieme; gli hook afterCommit non
		// scattano perche' la transazione esterna non committa mai. Effetti non-DB (HTTP, mail,
		// code) restano.
		$isMutating = in_array($method, ["POST", "PUT", "PATCH", "DELETE"], true);
		$rollbackApplied = false;

		try {
			if ($isMutating) {
				DB::beginTransaction();
			}

			try {
				$response = $this->kernel->handle($sub);
			} finally {
				if ($isMutating) {
					DB::rollBack();
					$rollbackApplied = true;
				}
			}
		} catch (Throwable $e) {
			$exception = $e;
		} finally {
			$trace->deactivate();
			$this->metrics->stop();
			if ($response instanceof Response) {
				$this->kernel->terminate($sub, $response);
			}
			Auth::guard("sanctum")->forgetUser();
		}

		$ms = (int) round((microtime(true) - $started) * 1000);

		$steps = $trace->httpSteps();
		$handlerStep = $exception
			? $this->handlerExceptionStep($exception, $ms)
			: $this->handlerResponseStep($response, $ms);

		$handlerStep["label"] = "Route handler · " . $endpoint["route_name"];
		$steps[] = $handlerStep;
		foreach ($steps as $i => &$step) {
			$step["index"] = $i + 1;
		}
		unset($step);

		$responseBody = $response?->getContent();
		$metrics = $this->metrics->collect($ms, $responseBody, $steps);
		$quality = $this->quality->analyze($route, $endpoint, $metrics, $steps, $endpoint["handler_source"] ?? null);

		return [
			"ok" => $exception === null && ($response?->isSuccessful() || $response?->isRedirection()),
			"duration_ms" => $ms,
			"metrics" => $metrics,
			"quality" => $quality,
			"rollback_applied" => $rollbackApplied,
			"inbound" => [
				"method" => $method,
				"uri" => $uri,
				"headers" => $inboundHeaders,
				"body_pretty" => $inboundBodyPretty,
			],
			"steps" => $steps,
		];
	}

	protected function displayRouteName(string $routeName): string
	{
		return $routeName;
	}

	/**
	 * Human-readable label for the sidebar catalog.
	 */
	protected function catalogTitle(string $routeName, ?string $docSummary): string
	{
		$summary = is_string($docSummary) ? trim(strtok($docSummary, "\n")) : "";
		if ($summary !== "" && mb_strlen($summary) <= 80) {
			return $summary;
		}

		return collect(explode(".", $routeName))
			->map(
				fn(string $part) => (string) str($part)
					->replace(["-", "_"], " ")
					->title()
			)
			->implode(" · ");
	}

	/**
	 * @return list<string>
	 */
	protected function catalogAuthHints(bool $needsSanctum, bool $needsTenantToken, string $categoryKey): array
	{
		$hints = [];

		if ($needsSanctum) {
			$hints[] = "Sanctum";
		}

		if ($needsTenantToken) {
			$hints[] = "B2B Bearer";
		}

		if ($categoryKey === "restricted") {
			$hints[] = "Shield";
		}

		return $hints;
	}

	/**
	 * @param  array<string, mixed>  $entry
	 * @return array<string, string>
	 */
	protected function pathParamDefaults(array $entry, Route $route): array
	{
		$defaults = [];

		if (in_array("tenant", $route->parameterNames(), true)) {
			$defaults["tenant"] = (string) config("app.tenant");
		}

		foreach ($entry["path_param_defaults"] ?? [] as $name => $value) {
			if (is_string($name) && (is_string($value) || is_numeric($value))) {
				$defaults[$name] = (string) $value;
			}
		}

		return $defaults;
	}

	/**
	 * @param  array<string, mixed>  $entry
	 */
	protected function bodyEncoding(array $entry, string $routeName): string
	{
		$encoding = $entry["body_encoding"] ?? null;
		if (is_string($encoding) && in_array($encoding, ["json", "form"], true)) {
			return $encoding;
		}

		return in_array($routeName, ["b2b.nexi.oauth.token", "b2b.nexi.oauth.tenant-token"], true) ? "form" : "json";
	}

	/**
	 * @return list<array{key: string, display_name: string, uri: string, method: string}>
	 */
	public function catalog(): array
	{
		return $this->endpoints()
			->map(
				fn(array $e) => [
					"key" => $e["key"],
					"display_name" => $e["display_name"],
					"uri" => $e["uri"],
					"method" => $e["default_method"],
				]
			)
			->values()
			->all();
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function handlerResponseStep(?Response $response, int $ms): array
	{
		$body = $this->responseBodyForTrace($response);

		return [
			"index" => 0,
			"type" => "handler",
			"label" => "Route handler response",
			"phase" => $response && ($response->isSuccessful() || $response->isRedirection()) ? "ok" : "error",
			"status" => $response?->getStatusCode() ?? 0,
			"duration_ms" => $ms,
			"response" => [
				"status" => $response?->getStatusCode() ?? 0,
				"headers" => $response ? $this->flatHeaders($response->headers->all()) : [],
				"body_pretty" => $this->pretty($body),
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function handlerExceptionStep(Throwable $e, int $ms): array
	{
		return [
			"index" => 0,
			"type" => "handler",
			"label" => "Route handler exception",
			"phase" => "error",
			"status" => 500,
			"duration_ms" => $ms,
			"response" => [
				"status" => 500,
				"headers" => [],
				"body_pretty" => $e::class . ": " . $e->getMessage() . "\n" . $e->getFile() . ":" . $e->getLine(),
			],
		];
	}

	/**
	 * @param  array<string, string>  $pathParams
	 */
	protected function buildUri(Route $route, array $pathParams): string
	{
		$uri = $route->uri();
		foreach ($pathParams as $name => $value) {
			$uri = str_replace(["{" . $name . "}", "{" . $name . "?}"], rawurlencode($value), $uri);
		}

		return "/" . ltrim($uri, "/");
	}

	/**
	 * @return array<string, mixed>|null
	 */
	protected function decodeBody(?string $jsonBody, string $method): ?array
	{
		if (!in_array($method, ["POST", "PUT", "PATCH", "DELETE"], true)) {
			return null;
		}
		if ($jsonBody === null || trim($jsonBody) === "") {
			return [];
		}
		$decoded = json_decode($jsonBody, true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($decoded)) {
			throw new JsonException("Body must be a JSON object.");
		}

		return $decoded;
	}

	/**
	 * b2b.nexi.oauth.token expects services.oauth_rays credentials.
	 * API Lab fills them when the body omits values or still has catalog placeholders.
	 */
	protected function applyInboundOAuthCredentialsIfNeeded(Route $route, array $payload): array
	{
		if ($route->getName() !== "b2b.nexi.oauth.token") {
			return $payload;
		}

		$clientId = config("services.oauth_rays.client_id");
		$clientSecret = config("services.oauth_rays.client_secret");

		if ($clientId === null || $clientSecret === null) {
			return $payload;
		}

		$submittedId = $payload["client_id"] ?? null;
		$submittedSecret = $payload["client_secret"] ?? null;

		if (!$this->shouldInjectInboundOAuthCredentials($submittedId, $submittedSecret)) {
			return $payload;
		}

		$payload["grant_type"] = ($payload["grant_type"] ?? "") !== "" ? $payload["grant_type"] : "client_credentials";
		$payload["client_id"] = $clientId;
		$payload["client_secret"] = $clientSecret;

		return $payload;
	}

	protected function shouldInjectInboundOAuthCredentials(mixed $clientId, mixed $clientSecret): bool
	{
		foreach ([$clientId, $clientSecret] as $value) {
			if (!is_string($value) || $value === "") {
				return true;
			}

			if (preg_match('/^\{[^}]+\}$/', $value) === 1) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Routes behind tenant.token expect a Rays-issued Bearer (from b2b.nexi.oauth.token).
	 * API Lab provisions one when the transmit request omits Authorization.
	 */
	protected function applyTenantBearerIfNeeded(Route $route, Request $sub): void
	{
		$needsTenantToken = collect($route->gatherMiddleware())->contains(fn($m) => $m === "tenant.token");

		if (!$needsTenantToken || $sub->bearerToken()) {
			return;
		}

		$tenantId = config("app.tenant");
		$token = hash("sha256", random_bytes(40));

		cache()->put(key: "oauth_token:{$tenantId}:{$token}", value: true, ttl: now()->addHour());

		$sub->headers->set("Authorization", "Bearer " . $token);
	}

	protected function authenticate(Route $route, ?string $userId, Request $sub): void
	{
		$needsSanctum = collect($route->gatherMiddleware())->contains(
			fn($m) => is_string($m) && str_contains($m, "auth:sanctum")
		);
		if (!$needsSanctum) {
			return;
		}

		$user = $userId ? User::query()->where("user_id", $userId)->first() : backpack_auth()->user();
		if (!$user instanceof User) {
			return;
		}

		Auth::shouldUse("sanctum");
		Auth::guard("sanctum")->setUser($user);
		Auth::setUser($user);
		$sub->setUserResolver(fn() => $user);
	}

	protected function responseBodyForTrace(?Response $response): ?string
	{
		if (!$response instanceof Response) {
			return null;
		}

		$content = $response->getContent();
		if ($content !== false && $content !== "") {
			return $this->pretty($content);
		}

		if ($response instanceof JsonResponse) {
			try {
				return json_encode($response->getData(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
			} catch (JsonException) {
				return null;
			}
		}

		return null;
	}

	protected function pretty(?string $body): ?string
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
		} catch (JsonException) {
			return $body;
		}
	}

	/**
	 * @param  array<string, array<int, string>>  $headers
	 * @return array<string, string>
	 */
	protected function flatHeaders(array $headers): array
	{
		$flat = [];
		foreach ($headers as $name => $values) {
			$flat[strtolower((string) $name)] = implode(", ", $values);
		}
		ksort($flat);

		return $flat;
	}
}
