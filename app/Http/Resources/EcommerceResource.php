<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EcommerceResource extends JsonResource
{
	/**
	 * Transform the resource into an array.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(Request $request): array
	{
		return [
			"id" => $this->id,
			"name" => $this->name,
			"image_path" => $this->image_path,
			"url" => $this->url,
			"created_at" => Carbon::parse($this->created_at)->format("d/m/Y"),
		];
	}
}
