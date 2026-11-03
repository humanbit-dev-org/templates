<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminSupportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The subject is a fixed category picked from a select (see
     * config/backpack/support.php), and up to a configured number of
     * screenshots can be attached as images.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', Rule::in(config('backpack.support.categories', []))],
            'message' => ['required', 'string', 'max:5000'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'screenshots' => ['nullable', 'array', 'max:'.(int) config('backpack.support.max_screenshots', 3)],
            'screenshots.*' => [
                'file',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:'.(int) config('backpack.support.screenshot_max_kb', 5120),
            ],
        ];
    }

    /**
     * Custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category' => __('backpack::support.subject'),
            'message' => __('backpack::support.message'),
            'screenshots' => __('backpack::support.screenshots'),
            'screenshots.*' => __('backpack::support.screenshot'),
        ];
    }
}
