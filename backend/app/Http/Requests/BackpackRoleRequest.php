<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BackpackRoleRequest extends FormRequest
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
			"name" => "required|string|max:255|unique:backpack_roles,name," . $this->id,
			// backpack_roles.description is NOT NULL
			"description" => "required|string|max:255",
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
			"name.required" => "Il nome del ruolo è obbligatorio",
			"name.unique" => "Questo nome è già in uso",
		];
	}
}
