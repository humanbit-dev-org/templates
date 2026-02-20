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
		$role =
			$this->role_id == 1 ? "Public" : ($this->role_id == 2 ? "User" : ($this->role_id == 3 ? "Desio" : "Filiale"));
		return [
			"id" => $this->id,
			"ndg" => $this->ndg,
			"ragione_sociale" => $this->ragione_sociale,
			"email" => $this->email,
			"ruolo" => $role,
			"sospeso" => $this->sospeso,
			"filiale" => FilialeResource::make($this->filiale),
		];
	}
}
