<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
	use CrudTrait;
	use HasFactory;

	protected $fillable = ["name", "description"];

	// One-to-many relationship with groups
	public function groups()
	{
		return $this->hasMany(Group::class);
	}
}
