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
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

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
		"password",
		"backpack_role_id",
		"role_id",
		"email_verified_at",
		"token",
		"token_expire",
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
		return $this->belongsTo(BackpackRole::class);
	}

	// public function sendEmailVerificationNotification()
	// {
	// 	if ($this->backpack_role == "user") {
	// 		// Send a custom notification for admin users
	// 		$this->notify(new VerifyEmailCustom());
	// 	} else {
	// 		// Use the default notification for regular users
	// 		$this->notify(new \Illuminate\Auth\Notifications\VerifyEmail());
	// 	}
	// }

	public function sendEmailVerificationNotification()
	{
		$this->notify(new \Illuminate\Auth\Notifications\VerifyEmail());
	}

	/**
	 * Generate a new two-factor authentication token
	 */
	public function generateTwoFactorToken(): string
	{
		$token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
		
		$this->token = $token;
		$this->token_expire = Carbon::now()->addDays(30);
		$this->save();
		
		return $token;
	}

	/**
	 * Check if the two-factor token is valid
	 */
	public function isValidTwoFactorToken(string $token): bool
	{
		return $this->token === $token && 
			   $this->token_expire && 
			   $this->token_expire->isFuture();
	}

	/**
	 * Check if token has expired and needs regeneration
	 */
	public function needsNewToken(): bool
	{
		return !$this->token || 
			   !$this->token_expire || 
			   $this->token_expire->isPast();
	}

	/**
	 * Send two-factor authentication token via email
	 */
	public function sendTwoFactorTokenEmail(): void
	{
		$token = $this->generateTwoFactorToken();
		
		Mail::send('emails.two-factor-token', ['token' => $token, 'user' => $this], function ($message) {
			$message->to($this->email)
					->subject('Codice di accesso - Autenticazione a due fattori');
		});
	}

	public function getDisplayAttribute()
	{
		return $this->name . " " . $this->surname;
	}
}
