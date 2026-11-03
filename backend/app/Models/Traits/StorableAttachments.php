<?php

namespace App\Models\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

trait StorableAttachments
{
    /**
     * Override setAttribute to intercept file uploads for *_path attributes.
     * Stores the file on the configured filesystem disk and saves the relative path.
     */
    public function setAttribute($key, $value)
    {
        if ($this->isFileAttribute($key, $value)) {

            // Sanitize filename — remove any character that is not alphanumeric, dash, underscore or dot
            $filename = preg_replace("/[^A-Za-z0-9\-\_\.]/", '_', $value->getClientOriginalName());

            // Store file on the default filesystem disk (local in dev, s3 in prod)
            // Path saved in DB is relative (e.g. attachments/file.pdf) — disk-agnostic
            $path = $value->storeAs(
                'attachments',
                $filename,
                ['disk' => config('filesystems.default'), 'ServerSideEncryption' => 'AES256']
            );

            $this->attributes[$key] = $path;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Determine if the given key/value pair represents a file upload.
     */
    private function isFileAttribute($key, $value): bool
    {
        return Str::endsWith($key, '_path') && $value instanceof UploadedFile;
    }
}
