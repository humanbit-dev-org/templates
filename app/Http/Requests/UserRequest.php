<?php

namespace App\Http\Requests;

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
		// only allow updates if the user is logged in
		return backpack_auth()->check();
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
			"password" => $this->route("id") ? "nullable|min:6" : "required|min:6",
			"backpack_role" => "required",
			"phone" => "required|unique:users,phone," . $this->route("id"),
			"address" => "required",
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
