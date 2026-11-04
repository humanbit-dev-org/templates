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
            $this->routeIs('backpack.*') ||
            in_array('admin', $this->route()->middleware()) ||
            in_array(config('backpack.base.middleware_key', 'admin'), $this->route()->middleware());

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
