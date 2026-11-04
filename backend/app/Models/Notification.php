<?php

namespace App\Models;

use App\Models\Traits\HasDisplayAttributes;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Notification extends Model
{
	use CrudTrait, HasDisplayAttributes, Notifiable;

	protected $fillable = ["type", "data", "notifiable_type", "notifiable_id", "read_at"];

	protected $casts = [
		"data" => "array",
		"read_at" => "datetime",
	];

	protected $table = "notifications";

	public function notifiable()
	{
		return $this->morphTo();
	}

	// see HasDisplayAttributes for full doc
	public function getDisplayAttribute(): string
	{
		return $this->data["email"] ?? ($this->data["oggetto"] ?? $this->type);
	}
}
