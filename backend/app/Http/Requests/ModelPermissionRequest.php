<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class ModelPermissionRequest extends FormRequest
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
			"model_name" => "required|array",
			"model_name.*" => "string",
			"backpack_role_id" => "nullable|exists:backpack_roles,id",
			"role_id" => "nullable|exists:roles,id",
			"can_read" => "boolean",
			"can_create" => "boolean",
			"can_update" => "boolean",
			"can_delete" => "boolean",
		];
	}

	/**
	 * Prepare the data for validation.
	 *
	 * @return void
	 */
	protected function prepareForValidation() {}

	/**
	 * Configure the validator instance.
	 *
	 * @param  \Illuminate\Validation\Validator  $validator
	 * @return void
	 */
	public function withValidator($validator) {}

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
			"validation_error" => "È necessario specificare almeno un ruolo (Backpack o Frontend)",
		];
	}
}
