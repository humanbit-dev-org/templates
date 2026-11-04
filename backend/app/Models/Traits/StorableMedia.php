<?php

namespace App\Models\Traits;

use App\Support\Media\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Turns an uploaded file assigned to a *_path attribute into a stored file,
 * saving the disk-relative path on the model.
 *
 * The upload is deferred to the saving event rather than done inside
 * setAttribute(): the destination folder may include an owner segment
 * (mediaOwner()) read from another attribute, which on a new record is not
 * guaranteed to be set yet when the file attribute is filled — mass
 * assignment applies attributes in payload order.
 */
trait StorableMedia
{
    /**
     * Uploaded files waiting to be written, keyed by attribute.
     *
     * @var array<string, UploadedFile>
     */
    protected array $pendingMedia = [];

    public static function bootStorableMedia(): void
    {
        static::saving(function (self $model): void {
            $model->flushPendingMedia();
        });
    }

    /**
     * Intercept file uploads assigned to *_path attributes.
     */
    public function setAttribute($key, $value)
    {
        if ($this->isFileAttribute($key, $value)) {
            $this->pendingMedia[$key] = $value;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Write the pending uploads, now that every attribute is set.
     */
    protected function flushPendingMedia(): void
    {
        foreach ($this->pendingMedia as $key => $file) {
            $this->attributes[$key] = MediaStorage::storePublic(
                $file,
                $this->mediaFolderFor($key),
                $this->mediaOwner(),
            );
        }

        $this->pendingMedia = [];
    }

    /**
     * Destination folder for an attribute (logos, fonts, media).
     */
    protected function mediaFolderFor(string $key): string
    {
        return match ($key) {
            'logo_path' => 'logos',
            'font_primary_path', 'font_secondary_path', 'font_tertiary_path' => 'fonts',
            default => 'media',
        };
    }

    /**
     * Owner the asset belongs to, used as an optional folder segment
     * (see MediaStorage). Null keeps every file of the type in one folder;
     * override it in a model to group files, e.g. by tenant.
     */
    protected function mediaOwner(): ?string
    {
        return null;
    }

    private function isFileAttribute($key, $value): bool
    {
        return Str::endsWith($key, '_path') && $value instanceof UploadedFile;
    }
}
