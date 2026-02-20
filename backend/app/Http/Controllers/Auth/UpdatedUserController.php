<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Notification;
use App\Notifications\PasswordUpdatedNotification;

class UpdatedUserController extends Controller
{
	public function updateProfile(Request $request)
	{
		$lang = $request->header("locale");
		App::setLocale($lang);
		$request->user()->update($request->all());

		return response()->json(["message" => __("events.profile_updated")]);
	}

	public function updatePassword(Request $request)
	{
		$lang = $request->header("locale");
		App::setLocale($lang);
		$request->validate([
			"current_password" => ["required", "current_password"],
			"new_password" => ["required", "confirmed", "different:current_password", Password::defaults()],
		]);

		// Logout from all other devices/sessions BEFORE changing password
		Auth::logoutOtherDevices($request->current_password);

		$request->user()->update([
			"password" => Hash::make($request->new_password),
		]);

		// Use user's preferred language, fallback to request locale, then to default
		$userLang = $request->user()->lang ?? ($lang ?? "en");
		Notification::send($request->user(), new PasswordUpdatedNotification($userLang));
		return response()->json(["message" => __("events.password_updated")]);
	}
}
