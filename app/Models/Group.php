<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
	use CrudTrait;
	use HasFactory;

	protected $fillable = ["name", "private", "creator_id", "category_id"];

	// Define the relationship with the group creator
	public function creator()
	{
		return $this->belongsTo(User::class, "creator_id");
	}

	// Define the relationship with the group category
	public function category()
	{
		return $this->belongsTo(Category::class);
	}

	// Many-to-many relationship with group member users
	public function users()
	{
		return $this->belongsToMany(User::class)->using(GroupUser::class)->withPivot("id", "percentage");
	}

	public function orders()
	{
		return $this->hasMany(Order::class);
	}
}
