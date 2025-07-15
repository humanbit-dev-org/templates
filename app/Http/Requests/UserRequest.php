<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;
use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
	/**
	 * Determine if the user is authorized to make this request.
	 *
	 * @return bool
	 */
	public function authorize()
	{
		// Check if request comes from backpack by verifying middleware or guard
		$isBackpackRequest =
			$this->routeIs("backpack.*") ||
			in_array("admin", $this->route()->middleware()) ||
			in_array(config("backpack.base.middleware_key", "admin"), $this->route()->middleware());

		// If request comes from backpack, require backpack authentication
		if ($isBackpackRequest) {
			return backpack_auth()->check();
		}

		// If request comes from frontend API, allow without authentication
		return true;
	}

	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array
	 */
	public function rules()
	{
		return [
			"username" => "required|unique:users,username," . $this->route("id"),
			"name" => "required",
			"surname" => "required",
			"email" => "required|email|max:255|unique:users,email," . $this->route("id"),
			"phone" => "required",
			"address" => "required",
			"password" => $this->route("id")
				? ["nullable", Password::min(6)->mixedCase()->numbers()->symbols()->uncompromised()]
				: ["required", Password::min(6)->mixedCase()->numbers()->symbols()->uncompromised()],
			"role_id" => $this->isMethod("POST") && $this->hasHeader("X-Requested-With") ? "nullable" : "required",
			"backpack_role_id" => "nullable",
			"lang" => "required",
			"send_email_notifications" => "nullable",
			"send_push_notifications" => "nullable",
		];
	}

	/**
	 * Get the validation attributes that apply to the request.
	 *
	 * @return array
	 */
	public function attributes()
	{
		return [
				//
			];
	}

	/**
	 * Get the validation messages that apply to the request.
	 *
	 * @return array
	 */
	public function messages()
	{
		return [
				//
			];
	}
}
