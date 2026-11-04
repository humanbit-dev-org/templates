<?php

namespace App\Http\Controllers\Restricted;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Issues short-lived Sanctum tokens for a sandbox PO user.
 *
 * One PO per sandbox bank: `bank` selects which, defaulting to
 * sandbox.default_bank. Unlike the console login and the Swagger
 * impersonation, existing tokens are NOT revoked here: sandbox tokens are
 * independent, so a new token never expires someone else's.
 */
class SandboxTokenController extends Controller
{
	public function __invoke(Request $request): JsonResponse
	{
		$validated = $request->validate([
			"bank" => ["sometimes", "string", Rule::in(array_keys(config("sandbox.banks")))],
		]);

		$abi = $validated["bank"] ?? config("sandbox.default_bank");
		$po = config("sandbox.banks.{$abi}.po");

		$token = User::where("user_id", $po)
			->firstOrFail()
			->createToken("sandbox", ["*"], now()->addMinutes(config("sandbox.token_minutes")));

		return response()->json(
			[
				"token" => $token->plainTextToken,
				"bank" => $abi,
				"expires_at" => $token->accessToken->expires_at,
			],
			201
		);
	}
}
