<?php

namespace App\Http\Resources;

use App\Support\Url\StoredFileUrl;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

#[SchemaName('Attachment')]
class AttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The URL is resolved by StoredFileUrl from the stored path: CDN or disk
     * URL for public files, an expiring signed URL for private ones. Null
     * when the attachment has no file yet.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'url' => $this->file_path ? StoredFileUrl::resolve($this->file_path) : null,
        ];
    }
}
