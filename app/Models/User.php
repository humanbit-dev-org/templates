<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\VerifyEmailCustom;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\Traits\HasTwoFactorAuth;

class User extends Authenticatable implements MustVerifyEmail
{
	use CrudTrait;
	use HasFactory, Notifiable, HasApiTokens;
	use HasTwoFactorAuth;

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		"username",
		"name",
		"surname",
		"address",
		"email",
		"phone",
		"password",
		"backpack_role_id",
		"role_id",
		"email_verified_at",
		"token",
		"token_expire",
		"token_verified",
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
		return $this->belongsTo(BackpackRole::class, 'backpack_role_id');
	}

	public function sendEmailVerificationNotification()
	{
		$this->notify(new \Illuminate\Auth\Notifications\VerifyEmail());
	}

	public function hasVerifiedEmail()
	{
		return $this->email_verified_at != null;
	}

	public function getDisplayAttribute()
	{
		return $this->name . " " . $this->surname;
	}
}
