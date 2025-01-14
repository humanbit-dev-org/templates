<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
	use CrudTrait;
	use HasFactory;

	protected $fillable = ["name", "create_op", "read_op", "update_op", "delete_op"];

	public function users()
	{
		return $this->hasMany(User::class);
	}
}
