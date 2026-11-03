<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Models\User;
use App\Services\Auth\TwoFactorAuthService;
use App\Support\TwoFactorDevice;
use Backpack\CRUD\app\Http\Controllers\Auth\LoginController as AuthLoginController;
use Illuminate\Http\Request;

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
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $this->validateLogin($request);

        if (method_exists($this, 'hasTooManyLoginAttempts') && $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        $credentials = $this->credentials($request);

        if (backpack_auth()->validate($credentials)) {
            $user = backpack_auth()->getProvider()->retrieveByCredentials($credentials);

            if (config('backpack.base.setup_two_factor_auth', false)) {
                return $this->handleTwoFactorAuth($request, $user, $credentials);
            }

            return $this->loginUser($request, $user);
        }

        if (method_exists($this, 'incrementLoginAttempts')) {
            $this->incrementLoginAttempts($request);
        }

        return $this->sendFailedLoginResponse($request);
    }

    /**
     * Validate the user login request.
     */
    protected function validateLogin(Request $request): void
    {
        $rules = [
            $this->username() => 'required|string',
            'password' => 'required|string',
        ];

        if (config('backpack.base.setup_two_factor_auth', false)) {
            $rules[TwoFactorDevice::REQUEST_KEY] = TwoFactorDevice::validationRules();
        }

        $request->validate($rules);
    }

    /**
     * Handle two-factor authentication flow for the current device.
     */
    private function handleTwoFactorAuth(Request $request, User $user, array $credentials)
    {
        $deviceId = TwoFactorDevice::fromRequest($request);

        if ($this->twoFactorService->needsNewToken($user, $deviceId)) {
            $this->twoFactorService->resetRateLimit($user);
            $this->twoFactorService->sendTokenEmail($user, $deviceId);

            $request->session()->put('2fa_user_id', $user->getKey());
            $request->session()->put('2fa_login_credentials', $credentials);
            $request->session()->put(TwoFactorDevice::SESSION_KEY, $deviceId);

            if (method_exists($this, 'clearLoginAttempts')) {
                $this->clearLoginAttempts($request);
            }

            return redirect()->route('backpack.auth.two-factor');
        }

        return $this->loginUser($request, $user);
    }

    /**
     * Login the user and clear attempts.
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    private function loginUser(Request $request, User $user)
    {
        backpack_auth()->login($user, $request->filled('remember'));

        if (method_exists($this, 'clearLoginAttempts')) {
            $this->clearLoginAttempts($request);
        }

        return $this->sendLoginResponse($request);
    }

    /**
     * Show the two-factor authentication form.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showTwoFactorForm(Request $request)
    {
        if (! $request->session()->has('2fa_user_id') || ! $request->session()->has(TwoFactorDevice::SESSION_KEY)) {
            return redirect()->route('backpack.auth.login');
        }

        return view('vendor.backpack.theme-tabler.auth.login.two-factor');
    }

    /**
     * Verify the two-factor authentication token for the session device.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verifyTwoFactor(Request $request)
    {
        $request->validate(['token' => 'required|string|size:6']);

        $userId = $request->session()->get('2fa_user_id');
        $deviceId = TwoFactorDevice::fromSession($request);

        if (! $userId || ! $deviceId) {
            return redirect()
                ->route('backpack.auth.login')
                ->withErrors(['token' => 'Sessione scaduta. Riprova l\'accesso.']);
        }

        $user = User::find($userId);

        if (! $user || ! $this->twoFactorService->validateToken($user, $deviceId, $request->token)) {
            return back()->withErrors(['token' => 'Codice non valido o scaduto.']);
        }

        $this->twoFactorService->markTokenVerified($user, $deviceId);
        backpack_auth()->login($user, $request->filled('remember'));

        $request->session()->forget(['2fa_user_id', '2fa_login_credentials', TwoFactorDevice::SESSION_KEY]);

        return $this->sendLoginResponse($request);
    }

    /**
     * Resend the two-factor authentication token for the session device.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resendTwoFactorToken(Request $request)
    {
        $userId = $request->session()->get('2fa_user_id');
        $deviceId = TwoFactorDevice::fromSession($request);

        if (! $userId || ! $deviceId) {
            return redirect()->route('backpack.auth.login');
        }

        $user = User::find($userId);
        if (! $user) {
            return redirect()->route('backpack.auth.login');
        }

        if (! $this->twoFactorService->canSendToken($user)) {
            $minutes = ceil($this->twoFactorService->getTimeUntilNextAttempt($user) / 60);

            return back()->withErrors([
                'throttle' => "Troppi tentativi di invio. Riprova tra {$minutes} minuti.",
            ]);
        }

        $this->twoFactorService->recordTokenAttempt($user);
        $this->twoFactorService->sendTokenEmail($user, $deviceId);

        $remaining = $this->twoFactorService->getRemainingAttempts($user);
        $message = 'Nuovo codice inviato via email.';

        if ($remaining <= 2 && $remaining > 0) {
            $message .= " (Rimangono {$remaining} tentativi)";
        }

        return back()->with('status', $message);
    }
}
