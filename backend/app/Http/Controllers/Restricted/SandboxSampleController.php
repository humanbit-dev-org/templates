<?php

namespace App\Http\Controllers\Restricted;

use App\Http\Controllers\Controller;
use App\Support\ApiSandbox\SampleCallResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Resolves a demo call for the /api-sandbox buttons: a random valid target
 * inside the requested bank plus a valid body, ready for the page to fire
 * against the real /sandbox/* endpoint.
 */
class SandboxSampleController extends Controller
{
	public function __invoke(Request $request): JsonResponse
	{
		$validated = $request->validate([
			"operation" => ["required", "string", Rule::in(SampleCallResolver::operations())],
			"bank" => ["required", "string", Rule::in(array_keys(config("sandbox.banks")))],
		]);

		$call = SampleCallResolver::resolve($validated["operation"], $validated["bank"]);

		if ($call === null) {
			return response()->json(
				[
					"message" => "Nessun dato idoneo nel sandbox per questa operazione.",
				],
				404
			);
		}

		return response()->json($call);
	}
}
