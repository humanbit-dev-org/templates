<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

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
        // Backpack uses 'id' as route parameter, not 'user_id'
        $userId = $this->route('id') ?? $this->route('user_id');
        $isUpdate = ! empty($userId);

        return [
            'user_id' => $isUpdate
                ? 'required|max:30|unique:users,user_id,'.$userId.',user_id'
                : 'nullable|max:30|unique:users,user_id',
            'email' => $isUpdate
                ? 'nullable|email|max:255|unique:users,email,'.$userId.',user_id'
                : 'nullable|email|max:255|unique:users,email',
            'password' => $isUpdate ? 'nullable' : 'nullable',
            'role_id' => 'nullable',
            'backpack_role_id' => 'nullable',
            'filiale_id' => ($this->filled('backpack_role_id') || $this->filled('role_id') == (int) Role::where('name', 'Product Owner')->first()->id) ? 'nullable' : 'required',
            'banca_abi' => $this->filled('backpack_role_id') ? 'nullable' : 'required|max:255|exists:banche,banca_abi',
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
