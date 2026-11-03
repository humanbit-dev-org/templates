<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccessiImportFileRequest extends FormRequest
{
    public const FILE_FIELD = 'importa-file-users';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            self::FILE_FIELD => [
                'required',
                'file',
                'mimes:csv,txt,xlsx',
                // 20 MB, in KB. PHP upload_max_filesize (Dockerfile, zz-uploads.ini) must stay above it.
                'max:20480',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            self::FILE_FIELD => __('validation.attributes.importa-file-users'),
        ];
    }
}
