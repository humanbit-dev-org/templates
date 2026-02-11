<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Traits\HasTwoFactorAuth;
use App\Notifications\VerifyEmailCustom;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
// use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable implements MustVerifyEmail
{
	use CrudTrait, HasTwoFactorAuth, HasFactory, Notifiable;

	/**
	 * The primary key associated with the table.
	 *
	 * @var string
	 */
	protected $primaryKey = "username";

	/**
	 * The "type" of the auto-incrementing ID.
	 *
	 * @var string
	 */
	protected $keyType = "string";

	/**
	 * Indicates if the IDs are auto-incrementing.
	 *
	 * @var bool
	 */
	public $incrementing = false;

	/**
	 * Get the route key for the model.
	 * This tells Laravel to use 'ndg' instead of 'id' in URLs
	 *
	 * @return string
	 */
	public function getRouteKeyName()
	{
		return "username";
	}

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		"username",
		"email",
		"name",
		"surname",
		"address",
		"phone",
		"role_id",
		"backpack_role_id",
		"token",
		"token_expire",
		"token_verified",
		"email_verified_at",
		"password",
		"remember_token",
	];

	/**
	 * The attributes that should be hidden for serialization.
	 *
	 * @var array<int, string>
	 */
	protected $hidden = ["password", "remember_token", "token"];

	/**
	 * Get the attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			"email_verified_at" => "datetime",
			"password" => "hashed",
			"token_expire" => "datetime",
		];
	}

	public function role()
	{
		return $this->belongsTo(Role::class);
	}

	public function backpackRole()
	{
		return $this->belongsTo(BackpackRole::class, "backpack_role_id");
	}

	public function sendEmailVerificationNotification()
	{
		$this->notify(new VerifyEmailCustom());
	}

	public function hasVerifiedEmail()
	{
		return $this->email_verified_at != null;
	}

	public function getDisplayAttribute()
	{
		return $this->name . " " . $this->surname;
	}

	/**
	 * Custom display attribute for relations
	 * This method allows to customize how the model appears in relation lists
	 *
	 * @return string Custom display text for relations
	 */
	public function getRelationDisplayAttribute()
	{
		return $this->name . " " . $this->surname;
	}
}
