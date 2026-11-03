<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class EmailVerificationNotificationController extends Controller
{
	/**
	 * Send a new email verification notification.
	 */
	public function store(Request $request, $id): JsonResponse|RedirectResponse
	{
		$user = User::find($id);
		if ($user == null) {
			return response()->json(["status" => "error", "message" => __("api.user_not_found")], 404);
		}

		if ($user->hasVerifiedEmail()) {
			return response()->json(["status" => "already-verified", "message" => __("api.email_already_verified")], 200);
		}

		try {
			$user->sendEmailVerificationNotification();
			return response()->json([
				"status" => "verification-link-sent",
				"message" => __("api.verification_email_sent"),
			]);
		} catch (\Exception $e) {
			return response()->json(["status" => "error", "message" => __("api.verification_email_failed")], 500);
		}
	}
}
