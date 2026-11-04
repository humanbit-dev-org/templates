<?php

namespace App\Http\Controllers\Restricted;

use App\Services\ApiLab\ApiLab;
use App\Services\ApiLab\ApiLabNetwork;
use App\Services\ApiLab\ApiLabTrace;
use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use JsonException;
use Throwable;

#[ExcludeAllRoutesFromDocs]
class ApiLabController extends Controller
{
	public function __construct(protected ApiLab $lab) {}

	public function index(Request $request, ApiLabNetwork $network)
	{
		$groups = $this->lab->groupedEndpoints();
		$active =
			$this->lab->find((string) $request->query("key", "")) ??
			$groups->flatMap(fn(array $g) => $g["endpoints"])->first();
		$networkInfo = $network->snapshot($request);

		return view("api-lab.index", compact("groups", "active", "networkInfo"));
	}

	/**
	 * Binary payload for client-side downlink measurement (no cache).
	 */
	public function probe(): Response
	{
		return response(random_bytes(65536), 200, [
			"Content-Type" => "application/octet-stream",
			"Cache-Control" => "no-store, no-cache, must-revalidate",
			"Pragma" => "no-cache",
		]);
	}

	public function execute(Request $request, string $key): JsonResponse
	{
		$endpoint = $this->lab->find($key);
		if ($endpoint === null) {
			return response()->json(["message" => "Endpoint not found."], 404);
		}

		$validated = $request->validate([
			"method" => ["required", "string", "in:" . implode(",", $endpoint["methods"])],
			"path_params" => ["nullable", "array"],
			"path_params.*" => ["nullable", "string"],
			"query" => ["nullable", "array"],
			"body" => ["nullable", "string"],
			"headers" => ["nullable", "array"],
			"headers.*" => ["nullable", "string"],
			"acting_as_user_id" => ["nullable", "string"],
		]);

		$pathParams = [];
		foreach ($endpoint["parameter_names"] as $name) {
			$defaults = $endpoint["path_param_defaults"] ?? [];
			$pathParams[$name] = (string) ($validated["path_params"][$name] ?? ($defaults[$name] ?? ""));
		}

		$trace = new ApiLabTrace();
		app()->instance(ApiLabTrace::class, $trace);

<<<<<<< HEAD
		try {
			$result = $this->lab->run(
				$endpoint,
				$validated["method"],
				$pathParams,
				$validated["query"] ?? [],
				$validated["body"] ?? null,
				$validated["headers"] ?? [],
				$validated["acting_as_user_id"] ?? backpack_auth()->user()?->user_id,
				$request,
				$trace
			);
		} catch (JsonException | InvalidArgumentException $e) {
			return response()->json($this->labErrorPayload($endpoint, $e), 422);
		} catch (Throwable $e) {
			report($e);
=======
        try {
            $result = $this->lab->run(
                $endpoint,
                $validated['method'],
                $pathParams,
                $validated['query'] ?? [],
                $validated['body'] ?? null,
                $validated['headers'] ?? [],
                $validated['acting_as_user_id'] ?? backpack_auth()->user()?->getKey(),
                $request,
                $trace,
            );
        } catch (JsonException|InvalidArgumentException $e) {
            return response()->json($this->labErrorPayload($endpoint, $e), 422);
        } catch (Throwable $e) {
            report($e);
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

			return response()->json($this->labErrorPayload($endpoint, $e), 500);
		} finally {
			app()->forgetInstance(ApiLabTrace::class);
		}

		return response()->json($result);
	}

	/**
	 * @param  array<string, mixed>  $endpoint
	 * @return array<string, mixed>
	 */
	protected function labErrorPayload(array $endpoint, Throwable $e): array
	{
		return [
			"ok" => false,
			"duration_ms" => 0,
			"rollback_applied" => false,
			"metrics" => null,
			"quality" => null,
			"inbound" => null,
			"steps" => [
				[
					"index" => 1,
					"type" => "handler",
					"label" => "Route handler · " . ($endpoint["route_name"] ?? "unknown"),
					"phase" => "error",
					"status" => 500,
					"duration_ms" => 0,
					"response" => [
						"status" => 500,
						"headers" => [],
						"body_pretty" => $e::class . ": " . $e->getMessage(),
					],
				],
			],
			"message" => $e->getMessage(),
		];
	}
}
