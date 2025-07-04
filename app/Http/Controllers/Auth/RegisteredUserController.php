<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Models\Invite;
use App\Models\Role;
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
	public function store(Request $request)
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

		$invite = $request->invite;
		if ($invite === "yes") {
			$invite = Invite::where("email", $request->email)->first();
			$invite->receiver_id = $user->id;
			$invite->save();
		}

		event(new Registered($user));

		return response()->json(["id" => $user->id, "errors" => $request->errors]);
	}
}
