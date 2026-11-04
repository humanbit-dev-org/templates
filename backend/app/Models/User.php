<?php

namespace App\Models;

use App\Models\Traits\HasDisplayAttributes;
use App\Models\Traits\NullableEmptyStrings;
use App\Notifications\ResetPasswordNotification;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
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

<<<<<<< HEAD
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
=======
    /**
     * Get the route key for the model.
     * This tells Laravel to use 'username' instead of 'id' in URLs
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
    protected $hidden = ['password', 'remember_token', 'token'];

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

    /**
     * Trusted Backpack 2FA devices of the user (user_two_factors.username).
     */
    public function twoFactor()
    {
        return $this->hasMany(UserTwoFactor::class, 'username', 'username');
    }

    public function isAdmin(): bool
    {
        return $this->backpackRole?->name === 'Admin';
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

	public function isDeveloper(): bool
	{
		return $this->backpackRole?->name === "Developer";
	}

<<<<<<< HEAD
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
=======
    public function isBackpackAdmin(): bool
    {
        return $this->isAdmin() || $this->isDeveloper();
    }

    /**
     * Whether the user may hold API portal credentials (routes/portal.php).
     *
     * config('portal.roles') lists the frontend roles allowed on the portal;
     * an empty list allows every user. Admin and Developer Backpack users are
     * always allowed, mirroring the RoleScope middleware.
     */
    public function canUsePortal(): bool
    {
        $roles = array_map(static fn ($role): string => Str::lower((string) $role), config('portal.roles', []));

        return $roles === []
            || $this->isBackpackAdmin()
            || in_array(Str::lower((string) $this->role?->name), $roles, true);
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmail);
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
        return trim($this->username.' - '.$this->name.' '.$this->surname);
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
}
