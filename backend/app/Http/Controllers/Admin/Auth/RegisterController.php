<?php

namespace App\Http\Controllers\Admin\Auth;

use Backpack\CRUD\app\Http\Controllers\Auth\RegisterController as AuthRegisterController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends AuthRegisterController
{
	protected function validator(array $data)
	{
		$user_model_fqn = config("backpack.base.user_model_fqn");
		$user = new $user_model_fqn();
		$users_table = $user->getTable();
		$email_validation = backpack_authentication_column() == "email" ? "email|" : "";

		return Validator::make($data, [
			"name" => "required|max:255",
			"surname" => "required|max:255",
			backpack_authentication_column() => "required|" . $email_validation . "max:255|unique:" . $users_table,
			"password" => "required|min:6|confirmed",
		]);
	}

	/**
	 * Create a new user instance after a valid registration.
	 *
	 * @param  array  $data
	 * @return \Illuminate\Contracts\Auth\Authenticatable
	 */
	protected function create(array $data)
	{
		$user_model_fqn = config("backpack.base.user_model_fqn");
		$user = new $user_model_fqn();

		return $user->create([
			"name" => $data["name"],
			"surname" => $data["surname"],
			"backpack_role" => "guest",
			"role_id" => "1",
			backpack_authentication_column() => $data[backpack_authentication_column()],
			"password" => Hash::make($data["password"]),
		]);
	}
}
