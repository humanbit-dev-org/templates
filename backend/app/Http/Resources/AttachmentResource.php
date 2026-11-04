<?php

namespace App\Http\Resources;

use App\Support\Url\StoredFileUrl;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

#[SchemaName("Attachment")]
class AttachmentResource extends JsonResource
{
<<<<<<< HEAD
	/**
	 * Transform the resource into an array.
	 *
	 * The URL points at the platform download route, which streams the file
	 * from the private disk after an ownership check. Stable across local
	 * and S3: the disk choice is the controller's, not the client's.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(Request $request): array
	{
		return [
			"id" => $this->id,
			"title" => $this->title,
			"description" => $this->description,
			"order" => $this->order,
			"url" => route("documenti.download", ["attachment" => $this->id]),
		];
	}
=======
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
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
}
