<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
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

<<<<<<< HEAD
	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array
	 */
	public function rules()
	{
		// Backpack uses 'id' as route parameter, not 'user_id'
		$userId = $this->route("id") ?? $this->route("user_id");
		$isUpdate = !empty($userId);

		return [
			"user_id" => $isUpdate
				? "required|max:30|unique:users,user_id," . $userId . ",user_id"
				: "nullable|max:30|unique:users,user_id",
			"email" => $isUpdate
				? "nullable|email|max:255|unique:users,email," . $userId . ",user_id"
				: "nullable|email|max:255|unique:users,email",
			"password" => $isUpdate ? "nullable" : "nullable",
			"role_id" => "nullable",
			"backpack_role_id" => "nullable",
			"filiale_id" =>
				$this->filled("backpack_role_id") ||
				$this->filled("role_id") == (int) Role::where("name", "Product Owner")->first()->id
					? "nullable"
					: "required",
			"banca_abi" => $this->filled("backpack_role_id") ? "nullable" : "required|max:255|exists:banche,banca_abi",
		];
	}
=======
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        // Backpack passes the primary key (username) as the 'id' route parameter.
        $username = $this->route('id');
        $isUpdate = ! empty($username);

        // Unique checks ignore the edited row, matched on the username primary key.
        $unique = static fn (string $column): string => $isUpdate
            ? Rule::unique('users', $column)->ignore($username, 'username')->__toString()
            : Rule::unique('users', $column)->__toString();

        return [
            'username' => ['required', 'string', 'max:255', $unique('username')],
            'email' => ['required', 'email', 'max:255', $unique('email')],
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            // users.password is NOT NULL: mandatory on create, blank keeps it on update.
            'password' => $isUpdate ? ['nullable', 'string', 'min:8'] : ['required', 'string', 'min:8'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'backpack_role_id' => ['nullable', 'integer', 'exists:backpack_roles,id'],
        ];
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

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
