<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Traits\HasDisplayAttributes;
use App\Models\Traits\NullableEmptyStrings;
use App\Notifications\ResetPasswordNotification;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
	use CrudTrait, HasApiTokens, HasDisplayAttributes, Notifiable, NullableEmptyStrings;

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

	public function twoFactor()
	{
		return $this->hasMany(UserTwoFactor::class, "user_id", "user_id");
	}

	public function isAdmin(): bool
	{
		return $this->backpackRole?->name === "Admin";
	}

	public function isDeveloper(): bool
	{
		return $this->backpackRole?->name === "Developer";
	}

	public function isBackpackAdmin(): bool
	{
		return $this->isAdmin() || $this->isDeveloper();
	}
	public function sendEmailVerificationNotification()
	{
		$this->notify(new VerifyEmail());
	}

	/**
	 * Send the password reset email with the branded sender name
	 * (see ResetPasswordNotification) instead of Laravel's default one.
	 *
	 * @param  string  $token
	 */
	public function sendPasswordResetNotification($token)
	{
		$this->notify(new ResetPasswordNotification($token));
	}
	public function hasVerifiedEmail()
	{
		return $this->email_verified_at != null;
	}

	// see HasDisplayAttributes for full doc
	public function getDisplayAttribute(): string
	{
		return $this->username;
	}

	// see HasDisplayAttributes for full doc
	public function getRelationDisplayAttribute(): string
	{
		return $this->user_id . " - " . $this->ragione_sociale;
	}
}
