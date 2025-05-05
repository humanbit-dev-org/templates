<?php

namespace App\Http\Controllers\Auth;

use App\Models\Role;
use App\Models\User;
use App\Models\Invite;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rules;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;

class RegisteredUserController extends Controller
{
	/**
	 * Handle an incoming registration request.
	 *
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function store(Request $request): Response
	{
		$request->validate([
			"username" => ["required", "string", "max:255", "unique:" . User::class],
			"name" => ["required", "string", "max:255"],
			"surname" => ["required", "string", "max:255"],
			"email" => ["required", "string", "lowercase", "email", "max:255", "unique:" . User::class],
			"address" => ["required", "string", "max:255"],
			"password" => ["required", "confirmed", Rules\Password::defaults()],
		]);

		$user = User::create([
			"username" => $request->username,
			"name" => $request->name,
			"surname" => $request->surname,
			"email" => $request->email,
			"address" => $request->address,
			"role_id" => Role::where("name", "Public")->first()->id,
			"password" => Hash::make($request->string("password")),
		]);

		event(new Registered($user));

		Auth::login($user);

		return response()->noContent();
	}
}
