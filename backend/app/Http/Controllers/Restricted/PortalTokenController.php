<?php

namespace App\Http\Controllers\Restricted;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

/**
 * Exchanges an API client's refresh token for the api_token used on /api/v1/*.
 *
 * The refresh token is a Sanctum PAT named 'portal-refresh:{8-char secret
 * prefix}', minted internally via `portal:refresh-token` and handed to the
 * client out-of-band. This endpoint re-authenticates it (auth:sanctum already
 * resolved the caller) and mints a fresh 'portal-access' token for the same
 * user the credential belongs to.
 *
 * One live api_token per user: previous 'portal-access' tokens are revoked on
 * each exchange, so the newest exchange is the only working secret and the
 * token table never accumulates stale credentials.
 */
class PortalTokenController extends Controller
{
	public function __invoke(Request $request): JsonResponse
	{
		$user = $request->user();
		$token = $user?->currentAccessToken();

<<<<<<< HEAD
		// Only a portal-refresh credential on a Product Owner can mint.
		abort_unless(
			$user &&
				$user->isProductOwner() &&
				$token &&
				Str::startsWith($token->name, config("portal.refresh_token_prefix") . ":"),
			403
		);
=======
        // Only a portal-refresh credential on a user allowed on the portal can mint.
        abort_unless(
            $user && $user->canUsePortal()
                && $token
                && Str::startsWith($token->name, config('portal.refresh_token_prefix').':'),
            403
        );
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		$user->tokens()->where("name", config("portal.api_token_name"))->delete();

		$apiToken = $user->createToken(
			config("portal.api_token_name"),
			config("portal.api_token_abilities"),
			now()->addMinutes(config("portal.api_token_minutes"))
		);

<<<<<<< HEAD
		$user->notify(new ApiTokenIssuedNotification($user, $apiToken->accessToken->expires_at, $request->ip()));

		return response()->json(
			[
				"token" => $apiToken->plainTextToken,
				"expires_at" => $apiToken->accessToken->expires_at,
			],
			201
		);
	}
=======
        return response()->json([
            'token' => $apiToken->plainTextToken,
            'expires_at' => $apiToken->accessToken->expires_at,
        ], 201);
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
}
