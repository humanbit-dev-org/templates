<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GroupUser extends Pivot
{
	use CrudTrait;
	use HasFactory;

	protected $fillable = ["user_id", "group_id", "percentage"];

	public $table = "group_user";

	public function group()
	{
		return $this->belongsTo(Group::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
