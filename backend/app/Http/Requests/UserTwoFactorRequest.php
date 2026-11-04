<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserTwoFactorRequest extends FormRequest
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
	public function rules() {}

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
				//
			];
	}
}
