<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelPermission extends Model
{
	use CrudTrait;
	use HasFactory;

	protected $fillable = [
		"backpack_role_id",
		"role_id",
		"model_name",
		"can_read",
		"can_create",
		"can_update",
		"can_delete",
	];

	protected $casts = [
		"model_name" => "array",
	];

	public function backpackRole()
	{
		return $this->belongsTo(BackpackRole::class);
	}

	public function role()
	{
		return $this->belongsTo(Role::class);
	}

	public function getDisplayAttribute()
	{
		// Handle both array and string cases
		if (is_array($this->model_name)) {
			return implode(",", $this->model_name);
		}

		return $this->model_name;
	}
}
