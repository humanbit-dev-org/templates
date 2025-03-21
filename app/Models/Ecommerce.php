<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use App\StorableMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ecommerce extends Model
{
	use CrudTrait, StorableMedia;
	use HasFactory;

	protected $fillable = ["name", "url", "image_path", "showcase", "user_id", "public_key", "unit_type", "order_time"];

	public $table = "ecommerce";

	public function orders()
	{
		return $this->hasMany(Order::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
