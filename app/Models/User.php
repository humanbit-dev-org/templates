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

class User extends Authenticatable implements MustVerifyEmail
{
	use CrudTrait;
	use HasFactory, Notifiable, HasApiTokens;

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
		"backpack_role",
		"password",
		"role_id",
		"email_verified_at",
	];

	/**
	 * The attributes that should be hidden for serialization.
	 *
	 * @var array<int, string>
	 */
	protected $hidden = ["password", "remember_token"];

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
		];
	}

	// Relationship with user-created groups
	public function createdGroups()
	{
		return $this->hasMany(Group::class, "creator_id");
	}

	// Many-to-many relationship with groups in which the user is a member
	public function groups()
	{
		return $this->belongsToMany(Group::class)->using(GroupUser::class)->withPivot("id", "percentage");
	}

	public function orders()
	{
		return $this->hasMany(OrderUser::class);
	}

	public function role()
	{
		return $this->belongsTo(Role::class);
	}

	public function ecommerce()
	{
		return $this->hasMany(Ecommerce::class);
	}

	public function sendEmailVerificationNotification()
	{
		if ($this->backpack_role == "user") {
			// Send a custom notification for admin users
			$this->notify(new VerifyEmailCustom());
		} else {
			// Use the default notification for regular users
			$this->notify(new \Illuminate\Auth\Notifications\VerifyEmail());
		}
	}
}
