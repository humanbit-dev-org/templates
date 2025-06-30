<?php

namespace App\Http\Controllers\Admin\Auth;

use Backpack\CRUD\app\Http\Controllers\Auth\LoginController as AuthLoginController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class LoginController extends AuthLoginController
{
	/**
	 * Handle a login request to the application.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Http\JsonResponse
	 */
	public function login(Request $request)
	{
		$this->validateLogin($request);

		// Check if too many login attempts have been made
		if (method_exists($this, 'hasTooManyLoginAttempts') &&
			$this->hasTooManyLoginAttempts($request)) {
			$this->fireLockoutEvent($request);
			return $this->sendLockoutResponse($request);
		}

		// Attempt to authenticate the user with provided credentials
		$credentials = $this->credentials($request);
		
		if (backpack_auth()->validate($credentials)) {
			$user = backpack_auth()->getProvider()->retrieveByCredentials($credentials);
			
			// Check if user needs new token or doesn't have one
			if ($user->needsNewToken()) {
				// Send new token and redirect to 2FA form
				$user->sendTwoFactorTokenEmail();
				
				// Store user ID in session for 2FA verification
				$request->session()->put('2fa_user_id', $user->id);
				$request->session()->put('2fa_login_credentials', $credentials);
				
				// Clear login attempts
				if (method_exists($this, 'clearLoginAttempts')) {
					$this->clearLoginAttempts($request);
				}
				
				// Redirect to 2FA verification form
				return redirect()->route('backpack.auth.two-factor');
			} else {
				// Token is still valid, complete login directly
				backpack_auth()->login($user, $request->filled('remember'));
				
				// Clear login attempts
				if (method_exists($this, 'clearLoginAttempts')) {
					$this->clearLoginAttempts($request);
				}
				
				return $this->sendLoginResponse($request);
			}
		}

		// Authentication failed
		if (method_exists($this, 'incrementLoginAttempts')) {
			$this->incrementLoginAttempts($request);
		}

		return $this->sendFailedLoginResponse($request);
	}

	/**
	 * Show the two-factor authentication form.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\View\View
	 */
	public function showTwoFactorForm(Request $request)
	{
		if (!$request->session()->has('2fa_user_id')) {
			return redirect()->route('backpack.auth.login');
		}

		return view('vendor.backpack.theme-tabler.auth.login.two-factor');
	}

	/**
	 * Verify the two-factor authentication token.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function verifyTwoFactor(Request $request)
	{
		$request->validate([
			'token' => 'required|string|size:6'
		]);

		$userId = $request->session()->get('2fa_user_id');
		$credentials = $request->session()->get('2fa_login_credentials');
		
		if (!$userId || !$credentials) {
			return redirect()->route('backpack.auth.login')
				->withErrors(['token' => 'Sessione scaduta. Riprova l\'accesso.']);
		}

		$user = User::find($userId);
		
		if (!$user || !$user->isValidTwoFactorToken($request->token)) {
			return back()->withErrors([
				'token' => 'Codice non valido o scaduto.'
			]);
		}

		// Complete the login process
		backpack_auth()->login($user, $request->filled('remember'));
		
		// Clear 2FA session data
		$request->session()->forget(['2fa_user_id', '2fa_login_credentials']);
		
		return $this->sendLoginResponse($request);
	}

	/**
	 * Resend the two-factor authentication token.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function resendTwoFactorToken(Request $request)
	{
		$userId = $request->session()->get('2fa_user_id');
		
		if (!$userId) {
			return redirect()->route('backpack.auth.login');
		}

		$user = User::find($userId);
		if ($user) {
			$user->sendTwoFactorTokenEmail();
		}

		return back()->with('status', 'Nuovo codice inviato via email.');
	}
}
