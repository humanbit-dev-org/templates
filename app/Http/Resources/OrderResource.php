<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Resources\OrderDetailResource;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
			"description" => $this->description,
			"import" => $this->import,
			"status" => $this->status,
			"created_at" => Carbon::parse($this->created_at)->format("d/m/Y H:i"),
			"confirm_date" => Carbon::parse($this->confirm_date)->format("d/m/Y H:i"),
			"expire_date" => Carbon::parse($this->expire_date)->format("d/m/Y H:i"),
			"complete_date" => Carbon::parse($this->complete_date)->format("d/m/Y H:i"),
			"ecommerce_description" => $this->ecommerce_description,
			"ecommerce_url" => $this->ecommerce_url,
			"group_id" => $this->group_id,
			"ecommerce" => new EcommerceResource($this->ecommerce),
			"details" => OrderDetailResource::collection($this->details),
		];
	}
}
