<?php

namespace App\Models;

use App\Models\Traits\HasDisplayAttributes;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Notification extends Model
{
<<<<<<< HEAD
	use CrudTrait, HasDisplayAttributes, Notifiable;
=======
    // notifications.id is a UUID primary key (Laravel database channel):
    // HasUuids makes the key a non-incrementing string and generates it on create.
    use CrudTrait, HasDisplayAttributes, HasUuids, Notifiable;
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

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
