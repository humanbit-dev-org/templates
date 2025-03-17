<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
	use CrudTrait;
	use HasFactory;

	protected $fillable = [
		"name",
		"transaction_id",
		"import",
		"status",
		"confirm_date",
		"expire_date",
		"complete_date",
		"description",
		"ecommerce_description",
		"ecommerce_url",
		"group_id",
		"ecommerce_id",
	];

	public function group()
	{
		return $this->belongsTo(Group::class);
	}

	public function ecommerce()
	{
		return $this->belongsTo(Ecommerce::class);
	}

	public function details()
	{
		return $this->hasMany(OrderUser::class);
	}
}
