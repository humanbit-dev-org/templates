<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Dedoc\Scramble\Attributes\SchemaName;

/**
 * @mixin \App\Models\User
 */
#[SchemaName("User")]
class UserResource extends JsonResource
{
	public function toArray(Request $request): array
	{
		return [
			"username" => $this->username,
			"name" => $this->name,
			"surname" => $this->surname,
			"email" => $this->email,
			"ruolo" => $this->role?->name,
			"email_verified" => $this->email_verified_at !== null,
		];
	}
}
