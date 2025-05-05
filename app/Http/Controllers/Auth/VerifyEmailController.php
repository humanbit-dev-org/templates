<?php

namespace App\Http\Controllers\Auth;

use App\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

class VerifyEmailController extends Controller
{
	/**
	 * Mark the authenticated user's email address as verified.
	 */
	public function __invoke(EmailVerificationRequest $request): RedirectResponse
	{
		if ($request->user()->hasVerifiedEmail()) {
			return redirect()->intended(config("app.frontend_url") . "/profile?verified=1");
		}

		if ($request->user()->markEmailAsVerified()) {
			event(new Verified($request->user()));
			$request->user()->role_id = Role::where("name", "User")->first()->id;
			$request->user()->save();
		}

		return redirect()->intended(config("app.frontend_url") . "/profile?verified=1");
	}
}
