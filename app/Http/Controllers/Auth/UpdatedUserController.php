<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Notification;
use App\Notifications\PasswordUpdatedNotification;

class UpdatedUserController extends Controller
{
	public function updateProfile(Request $request)
	{
		$request->user()->update($request->all());

		return response()->json(["message" => "Profile updated successfully"]);
	}

	public function updatePassword(Request $request)
	{
		$request->validate([
			"current_password" => ["required", "current_password"],
			"password" => ["required", "confirmed", Password::defaults()],
		]);

		$request->user()->update([
			"password" => Hash::make($request->password),
		]);

		// Logout from all other devices/sessions
		Auth::logoutOtherDevices($request->current_password);

		Notification::send($request->user(), new PasswordUpdatedNotification());
		return response()->json(["message" => "Password updated successfully"]);
	}
}
