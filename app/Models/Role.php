<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
	use CrudTrait;
	use HasFactory;

	protected $fillable = ["name", "description"];

	public function users()
	{
		return $this->hasMany(User::class);
	}

	public function modelPermissions()
	{
		return $this->hasMany(ModelPermission::class);
	}

	public function getDisplayAttribute()
	{
		return $this->name;
	}
}
