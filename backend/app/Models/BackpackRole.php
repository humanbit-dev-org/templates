<?php

namespace App\Models;

use App\Models\Traits\HasDisplayAttributes;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class BackpackRole extends Model
{
	use CrudTrait, HasDisplayAttributes;

	protected $fillable = ["name", "description"];

	public function users()
	{
		return $this->hasMany(User::class);
	}

	public function modelPermissions()
	{
		return $this->hasMany(ModelPermission::class);
	}

	// see HasDisplayAttributes for full doc
	public function getDisplayAttribute(): string
	{
		return $this->name;
	}
}
