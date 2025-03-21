<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invite extends Model
{
	use CrudTrait;
	use HasFactory;

	protected $fillable = ["group_id", "sender_id", "receiver_id", "email", "status", "expire_date"];

	// public function sender()
	// {
	// 	return $this->belongsTo(User::class);
	// }

	public function sender()
	{
		return $this->belongsTo(User::class);
	}

	public function receiver()
	{
		return $this->belongsTo(User::class);
	}

	public function group()
	{
		return $this->belongsTo(Group::class);
	}
}
