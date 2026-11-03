<?php

namespace App\Http\Controllers\Restricted;

use App\Notifications\ApiTokenIssuedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

/**
 * Exchanges a bank's refresh token for the api_token used on /api/v1/*.
 *
 * The refresh token is a Sanctum PAT named 'portal-refresh:{8-char secret
 * prefix}', minted internally via `portal:refresh-token` and handed to the
 * bank out-of-band. This endpoint re-authenticates it (auth:sanctum already
 * resolved the caller) and mints a fresh 'portal-access' token for the same
 * Product Owner the credential belongs to.
 *
 * One live api_token per PO: previous 'portal-access' tokens are revoked on
 * each exchange, so the newest exchange is the only working secret and the
 * token table never accumulates stale credentials.
 */
class PortalTokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        // Only a portal-refresh credential on a Product Owner can mint.
        abort_unless(
            $user && $user->isProductOwner()
                && $token
                && Str::startsWith($token->name, config('portal.refresh_token_prefix').':'),
            403
        );

        $user->tokens()->where('name', config('portal.api_token_name'))->delete();

        $apiToken = $user->createToken(
            config('portal.api_token_name'),
            config('portal.api_token_abilities'),
            now()->addMinutes(config('portal.api_token_minutes')),
        );

        $user->notify(new ApiTokenIssuedNotification($user, $apiToken->accessToken->expires_at, $request->ip()));

        return response()->json([
            'token' => $apiToken->plainTextToken,
            'expires_at' => $apiToken->accessToken->expires_at,
        ], 201);
    }
}
