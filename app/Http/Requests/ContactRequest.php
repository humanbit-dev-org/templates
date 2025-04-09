<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
	/**
	 * Determine if the user is authorized to make this request.
	 */
	public function authorize(): bool
	{
		return true;
	}

	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
	 */
	public function rules(): array
	{
		return [
			"name" => "required",
			"surname" => "required",
			"email" => [
				"required",
				"email",
				Rule::unique("contacts")->where(function ($query) {
					return $query
						->where(function ($q) {
							$q->whereNull("status")->orWhere("status", "");
						})
						->where("id", "!=", $this->route("id"));
				}),
			],
			"phone" => [
				"required",
				Rule::unique("contacts")->where(function ($query) {
					return $query
						->where(function ($q) {
							$q->whereNull("status")->orWhere("status", "");
						})
						->where("id", "!=", $this->route("id"));
				}),
			],
			"company" => "",
			"url" => "",
			"message" => "",
			"lang" => "",
		];
	}

	/**
	 * Get custom messages for validator errors.
	 *
	 * @return array
	 */
	public function messages(): array
	{
		return [
			"email.unique" => "Esiste già una richiesta in attesa con questa email.",
			"phone.unique" => "Esiste già una richiesta in attesa con questo numero di telefono.",
		];
	}
}
