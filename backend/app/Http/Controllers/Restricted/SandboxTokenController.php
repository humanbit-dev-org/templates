<?php

namespace App\Http\Controllers\Restricted;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Issues short-lived Sanctum tokens for a sandbox demo account.
 *
 * One account per sandbox profile: `profile` selects which, defaulting to
 * sandbox.default_profile. Unlike the Swagger impersonation, existing tokens
 * are NOT revoked here: sandbox tokens are independent, so a new token never
 * expires someone else's.
 */
class SandboxTokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['sometimes', 'string', Rule::in(array_keys(config('sandbox.profiles', [])))],
        ]);

        $profile = $validated['profile'] ?? config('sandbox.default_profile');
        $username = config("sandbox.profiles.{$profile}.user");

        // The account lives in the sandbox schema: EnterSandbox already
        // switched the default connection, and sandbox:seed created it.
        $token = User::query()->findOrFail($username)
            ->createToken('sandbox', ['*'], now()->addMinutes(config('sandbox.token_minutes')));

        return response()->json([
            'token' => $token->plainTextToken,
            'profile' => $profile,
            'expires_at' => $token->accessToken->expires_at,
        ], 201);
    }
}
