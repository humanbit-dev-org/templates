<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserController extends Controller
{
	public function authDashboard(Request $request)
	{
		$frontendUrl = rtrim(config('app.frontend_url'), "/") . "/dashboard";

		// Validate input
		$request->validate([
			"email" => "required|email",
			"password" => "required",
		]);

		$genericErrorMessage = "Credenziali non corrette o profilo non abilitato";

		if (!Auth::attempt(["email" => $request->email, "password" => $request->password])) {
			return redirect()->away("{$frontendUrl}?error=" . urlencode($genericErrorMessage));
		}

		$user = User::where("email", $request->email)->first();

		$isFiliale = $user->role_id === 4;
		$isBackpackAdmin = $user->backpackRole?->name === "Admin";
		$isDeveloper = $user->backpackRole?->name === "Developer";

		if ($isFiliale || $isBackpackAdmin || $isDeveloper) {
			$apiToken = explode("|", $user->createToken("api-token")->plainTextToken, 2)[1];
			$domain = config('session.domain');

			$cookie = cookie(
				name: "apiToken",
				value: $apiToken,
				minutes: 60 * 24 * 7,
				path: "/",
				domain: $domain,
				secure: false,
				httpOnly: false,
				raw: false,
				sameSite: "lax"
			);
			return redirect()
				->away("{$frontendUrl}")
				->withCookie($cookie);
		} else {
			return redirect()->away("{$frontendUrl}?error=" . urlencode($genericErrorMessage));
		}
	}

	/**
	 * Logout dell'utente
	 *
	 * Elimina il token di accesso e disconnette l'utente
	 */
	public function logout(Request $request)
	{
		$request->user()->tokens()->delete();
		return response()->noContent();
	}

	/**
	 * Anagrafica dell'utente
	 *
	 * Restituisce l'anagrafica dell'utente con la filiale
	 *
	 * @return array{user: UserResource}
	 */
	public function anagrafica(): UserResource
	{
		$user = Auth::user();
		if (!$user) {
			throw new NotFoundHttpException("Utente non trovato");
		}
		$isAdmin = $user->backpackRole?->name === "Admin";
		$isDeveloper = $user->backpackRole?->name === "Developer";
		if ($isAdmin || $isDeveloper) {
			return UserResource::make($user)->additional(["isAdmin" => $isAdmin, "isDeveloper" => $isDeveloper]);
		} else {
			return UserResource::make($user);
		}
	}
}
