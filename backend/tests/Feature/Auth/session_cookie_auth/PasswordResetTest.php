<?php

namespace Tests\Feature\Auth\session_cookie_auth;

use App\Models\BackpackRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
	use RefreshDatabase;

<<<<<<< HEAD
	protected function setUp(): void
	{
		parent::setUp();
		// Create required roles if they don't exist
		Role::firstOrCreate(["name" => "User"], ["description" => "User"]);
		BackpackRole::firstOrCreate(["name" => "Guest"], ["description" => "Guest"]);
	}
=======
    protected function setUp(): void
    {
        parent::setUp();

        // These tests cover the optional Sanctum session-cookie auth routes,
        // commented out in routes/auth.php: skip them while that mode is off.
        if (! Route::has('password.email')) {
            $this->markTestSkipped('Session-cookie auth routes are disabled (routes/auth.php).');
        }
        // Create required roles if they don't exist
        Role::firstOrCreate(['name' => 'User'], ['description' => 'User']);
        BackpackRole::firstOrCreate(['name' => 'Guest'], ['description' => 'Guest']);
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

	public function test_reset_password_link_can_be_requested(): void
	{
		Notification::fake();

<<<<<<< HEAD
		$user = User::create([
			"ndg" => "reset@example.com",
			"email" => "reset@example.com",
			"password" => bcrypt("password"),
			"email_verified_at" => now(),
			"role_id" => Role::where("name", "User")->first()->id,
			"backpack_role_id" => BackpackRole::where("name", "Guest")->first()->id,
		]);
=======
        $user = User::create([
            'username' => 'reset',
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'reset@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
            'role_id' => Role::where('name', 'User')->first()->id,
            'backpack_role_id' => BackpackRole::where('name', 'Guest')->first()->id,
        ]);
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		$this->post("/forgot-password", ["email" => $user->email]);

		Notification::assertSentTo($user, ResetPassword::class);
	}

	public function test_password_can_be_reset_with_valid_token(): void
	{
		Notification::fake();

<<<<<<< HEAD
		$user = User::create([
			"ndg" => "reset2@example.com",
			"email" => "reset2@example.com",
			"password" => bcrypt("password"),
			"email_verified_at" => now(),
			"role_id" => Role::where("name", "User")->first()->id,
			"backpack_role_id" => BackpackRole::where("name", "Guest")->first()->id,
		]);
=======
        $user = User::create([
            'username' => 'reset2',
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'reset2@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
            'role_id' => Role::where('name', 'User')->first()->id,
            'backpack_role_id' => BackpackRole::where('name', 'Guest')->first()->id,
        ]);
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		$this->post("/forgot-password", ["email" => $user->email]);

		Notification::assertSentTo($user, ResetPassword::class, function (object $notification) use ($user) {
			$response = $this->post("/reset-password", [
				"token" => $notification->token,
				"email" => $user->email,
				"password" => "password",
				"password_confirmation" => "password",
			]);

			$response->assertSessionHasNoErrors()->assertStatus(200);

			return true;
		});
	}
}
