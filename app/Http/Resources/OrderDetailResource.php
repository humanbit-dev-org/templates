<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class OrderDetailResource extends JsonResource
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
			"transaction_id" => $this->transaction_id,
			"import" => $this->import,
			"status" => $this->status,
			"complete_date" => Carbon::parse($this->complete_date)->format("d/m/Y H:i"),
			"user" => new UserResource($this->user),
		];
	}
}
