<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
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
			"name" => "required|min:5|max:255",
			"id_transaction" => "required|unique:orders,id_transaction",
			"import" => "required",
			"group_id" => "required|exists:groups,id",
			"confirm_date" => "required|date",
			"ecommerce_id" => "required|exists:ecommerce,id",
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
