<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
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
			"private" => $this->private,
			"creator" => new UserResource($this->creator),
			"users" => UserResource::collection($this->users),
			"category" => new CategoryResource($this->category),
			"created_at" => Carbon::parse($this->created_at)->format("d/m/Y"),
			"updated_at" => Carbon::parse($this->updated_at)->format("d/m/Y"),
			"orders" => OrderResource::collection($this->orders),
		];
	}
}
