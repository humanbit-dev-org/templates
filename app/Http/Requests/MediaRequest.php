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
			"title" => "required|min:5|max:255",
			"image_path" => "mimes:jpeg,png,jpg,gif|max:2048",
			"webm_path" => "mimes:webm|max:10240",
			"mp4_path" => "mimes:mp4|max:10240",
			"ogv_path" => "mimes:ogg|max:10240",
			"article_id" => "nullable|exists:articles,id",
			"institutional_id" => "nullable|exists:institutionals,id",
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
