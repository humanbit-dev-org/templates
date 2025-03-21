<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MediaRequest extends FormRequest
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
			"title" => "required",
			"image_path" => "nullable|image|mimes:jpeg,png,jpg,gif,webp|max:1500",
			"webm_path" => "nullable|file|mimes:webm|max:3000",
			"mp4_path" => "nullable|mimes:mp4|max:3000",
			"mp3_path" => "nullable|file|mimes:mp3|max:10000",
			"ogg_path" => "nullable|file|mimes:ogg|max:3000",
			"ogv_path" => "nullable|file|mimes:ogv,ogm|max:3000",
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
