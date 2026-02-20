<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\User;

class VerifyEmailController extends Controller
{
	/**
	 * Mark the user's email address as verified.
	 */
	public function __invoke(Request $request): RedirectResponse
	{
		// Get user from the request parameters (id from URL)
		$user = \App\Models\User::findOrFail($request->route("id"));

		// Verify the hash matches
		if (!hash_equals((string) $request->route("hash"), sha1($user->getEmailForVerification()))) {
			return redirect()->intended(config("app.frontend_url") . "/login?verified=0");
		}

		if ($user->hasVerifiedEmail()) {
			return redirect()->intended(config("app.frontend_url") . "/profile?verified=1");
		}

		if ($user->markEmailAsVerified()) {
			$user->email_verified_at = now();
			$user->role_id = Role::where("name", "User")->first()->id;
			$user->save();
			event(new Verified($user));
		}

		// Login the user automatically after verification
		auth()->login($user);

		return redirect()->intended(config("app.frontend_url") . "/profile?verified=1");
	}
}
