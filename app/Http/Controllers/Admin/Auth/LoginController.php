<?php

namespace App\Http\Controllers\Admin\Auth;

use Backpack\CRUD\app\Http\Controllers\Auth\LoginController as AuthLoginController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Models\User;
use App\Services\TwoFactorAuthService;

class LoginController extends AuthLoginController
{
	protected TwoFactorAuthService $twoFactorService;

	public function __construct(TwoFactorAuthService $twoFactorService)
	{
		parent::__construct();
		$this->twoFactorService = $twoFactorService;
	}

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
		if (method_exists($this, "hasTooManyLoginAttempts") && $this->hasTooManyLoginAttempts($request)) {
			$this->fireLockoutEvent($request);
			return $this->sendLockoutResponse($request);
		}

		$credentials = $this->credentials($request);

		if (backpack_auth()->validate($credentials)) {
			$user = backpack_auth()->getProvider()->retrieveByCredentials($credentials);

			if (config("backpack.base.setup_two_factor_auth", false)) {
				return $this->handleTwoFactorAuth($request, $user, $credentials);
			} else {
				return $this->loginUser($request, $user);
			}
		}

		if (method_exists($this, "incrementLoginAttempts")) {
			$this->incrementLoginAttempts($request);
		}

		return $this->sendFailedLoginResponse($request);
	}

	/**
	 * Handle two-factor authentication flow
	 */
	private function handleTwoFactorAuth(Request $request, User $user, array $credentials)
	{
		if ($this->twoFactorService->needsNewToken($user)) {
			$this->twoFactorService->resetRateLimit($user);
			$this->twoFactorService->sendTokenEmail($user);

			$request->session()->put("2fa_user_id", $user->id);
			$request->session()->put("2fa_login_credentials", $credentials);

			if (method_exists($this, "clearLoginAttempts")) {
				$this->clearLoginAttempts($request);
			}

			return redirect()->route("backpack.auth.two-factor");
		}

		return $this->loginUser($request, $user);
	}

	/**
	 * Login the user and clear attempts
	 */
	private function loginUser(Request $request, User $user)
	{
		backpack_auth()->login($user, $request->filled("remember"));

		if (method_exists($this, "clearLoginAttempts")) {
			$this->clearLoginAttempts($request);
		}

		return $this->sendLoginResponse($request);
	}

	/**
	 * Show the two-factor authentication form.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\View\View
	 */
	public function showTwoFactorForm(Request $request)
	{
		if (!$request->session()->has("2fa_user_id")) {
			return redirect()->route("backpack.auth.login");
		}

		return view("vendor.backpack.theme-tabler.auth.login.two-factor");
	}

	/**
	 * Verify the two-factor authentication token.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function verifyTwoFactor(Request $request)
	{
		$request->validate(["token" => "required|string|size:6"]);

		$userId = $request->session()->get("2fa_user_id");

		if (!$userId) {
			return redirect()
				->route("backpack.auth.login")
				->withErrors(["token" => 'Sessione scaduta. Riprova l\'accesso.']);
		}

		$user = User::find($userId);

		if (!$user || !$this->twoFactorService->validateToken($user, $request->token)) {
			return back()->withErrors(["token" => "Codice non valido o scaduto."]);
		}

		$this->twoFactorService->markTokenVerified($user);
		backpack_auth()->login($user, $request->filled("remember"));

		$request->session()->forget(["2fa_user_id", "2fa_login_credentials"]);

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
		$userId = $request->session()->get("2fa_user_id");

		if (!$userId) {
			return redirect()->route("backpack.auth.login");
		}

		$user = User::find($userId);
		if (!$user) {
			return redirect()->route("backpack.auth.login");
		}

		if (!$this->twoFactorService->canSendToken($user)) {
			$minutes = ceil($this->twoFactorService->getTimeUntilNextAttempt($user) / 60);

			return back()->withErrors([
				"throttle" => "Troppi tentativi di invio. Riprova tra {$minutes} minuti.",
			]);
		}

		$this->twoFactorService->recordTokenAttempt($user);
		$this->twoFactorService->sendTokenEmail($user);

		$remaining = $this->twoFactorService->getRemainingAttempts($user);
		$message = "Nuovo codice inviato via email.";

		if ($remaining <= 2 && $remaining > 0) {
			$message .= " (Rimangono {$remaining} tentativi)";
		}

		return back()->with("status", $message);
	}
}
