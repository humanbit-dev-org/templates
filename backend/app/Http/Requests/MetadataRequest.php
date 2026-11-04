<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MetadataRequest extends FormRequest
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
			// metadata.it and metadata.code are NOT NULL; image_path keeps the stored
			// file on update, so it is only validated when a new upload is sent.
			"it" => "required|string|max:255",
			"en" => "nullable|string|max:255",
			"code" => "required|string|max:255",
			"image_path" => "nullable|image|mimes:jpeg,png,jpg,gif,webp|max:1500",
			"page_id" => "required|exists:pages,id",
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
