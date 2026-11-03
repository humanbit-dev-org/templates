<?php

namespace App\Http\Resources;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

#[SchemaName('Attachment')]
class AttachmentResource extends JsonResource
{
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
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'order' => $this->order,
            'url' => route('documenti.download', ['attachment' => $this->id]),
        ];
    }
}
