<?php

namespace App\Http\Controllers\Restricted;

use App\Models\User;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Controller for Swagger UI functionality.
 * Handles user impersonation and token management for API testing.
 */
class SwaggerController extends Controller
{
    protected const TOKEN_NAME = 'swagger-docs';

    /**
     * Generate a token for a specific user (impersonation).
     * Allows testing APIs as if logged in as that user.
     */
    #[ExcludeRouteFromDocs]
    public function impersonate(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|string',
        ]);

        $userId = $request->input('user_id');

        // Find the user to impersonate
        $targetUser = User::where('user_id', $userId)->first();

        if (! $targetUser) {
            return response()->json([
                'success' => false,
                'message' => "User with user_id '{$userId}' not found",
            ], 404);
        }

        // Revoke any existing swagger tokens for the current session
        $this->revokeCurrentToken();

        // Generate new token for the target user
        $token = $targetUser->createToken(self::TOKEN_NAME, ['*'])->plainTextToken;

        // Store in session
        session([
            'swagger_api_token' => $token,
            'swagger_token_user_id' => $targetUser->user_id,
            'swagger_impersonating' => true,
        ]);

        Log::info('Swagger impersonation started', [
            'operator' => backpack_auth()->user()?->email,
            'target_user_id' => $targetUser->user_id,
            'target_email' => $targetUser->email,
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'user_id' => $targetUser->user_id,
                'email' => $targetUser->email,
                'ragione_sociale' => $targetUser->ragione_sociale,
            ],
        ]);
    }

    /**
     * Revoke the current swagger token.
     * Called when user leaves the docs page or manually revokes.
     */
    #[ExcludeRouteFromDocs]
    public function revoke(Request $request): JsonResponse
    {
        $revoked = $this->revokeCurrentToken();

        if ($revoked) {
            Log::info('Swagger token revoked', [
                'operator' => backpack_auth()->user()?->email,
            ]);
        }

        return response()->json([
            'success' => true,
            'revoked' => $revoked,
        ]);
    }

    /**
     * Revoke the current swagger token from session.
     */
    protected function revokeCurrentToken(): bool
    {
        $tokenUserId = session('swagger_token_user_id');

        if (! $tokenUserId) {
            return false;
        }

        // Find user and revoke their swagger tokens
        $user = User::where('user_id', $tokenUserId)->first();

        if ($user) {
            $user->tokens()->where('name', self::TOKEN_NAME)->delete();
        }

        // Clear session
        session()->forget([
            'swagger_api_token',
            'swagger_token_user_id',
            'swagger_impersonating',
        ]);

        return true;
    }
}
