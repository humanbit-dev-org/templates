<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
	use CrudTrait;
	use HasFactory;

	protected $fillable = [
		"name",
		"surname",
		"email",
		"phone",
		"company",
		"unit_type",
		"type",
		"order_time",
		"message",
		"url",
		"status",
		"lang",
		"reject_reason",
	];
}
