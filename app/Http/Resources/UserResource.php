<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
			"username" => $this->username,
			"name" => $this->name,
			"surname" => $this->surname,
			"email" => $this->email,
			"created_at" => Carbon::parse($this->created_at)->format("d/m/Y"),
		];
	}
}
