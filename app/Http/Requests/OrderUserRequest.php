<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderUserRequest extends FormRequest
{
	/**
	 * Determine if the user is authorized to make this request.
	 */
	public function authorize(): bool
	{
		return false;
	}

	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
	 */
	public function rules(): array
	{
		return [
			"import" => "required",
			"order_id" => "required|exists:orders,id",
			"user_id" => "required|exists:users,id",
		];
	}
}
