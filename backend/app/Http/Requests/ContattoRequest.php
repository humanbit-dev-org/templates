<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContattoRequest extends FormRequest
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
			"email" => "required|email",
			"oggetto" => "required|string|in:richiesta,conferma_richiesta,gestione_credito,altro",
			"messaggio" => "required|string",
		];
	}
}
