<?php

namespace Tests\Feature\Auth\session_cookie_auth;

use App\Models\BackpackRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationTest extends TestCase
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

	public function test_users_can_authenticate_using_the_login_screen(): void
	{
		$user = User::create([
			"ndg" => "test@example.com",
			"email" => "test@example.com",
			"password" => bcrypt("password"),
			"email_verified_at" => now(),
			"role_id" => Role::where("name", "User")->first()->id,
			"backpack_role_id" => BackpackRole::where("name", "Guest")->first()->id,
		]);
=======
    protected function setUp(): void
    {
        parent::setUp();

        // These tests cover the optional Sanctum session-cookie auth routes,
        // commented out in routes/auth.php: skip them while that mode is off.
        if (! Route::has('login')) {
            $this->markTestSkipped('Session-cookie auth routes are disabled (routes/auth.php).');
        }
        // Create required roles if they don't exist
        Role::firstOrCreate(['name' => 'User'], ['description' => 'User']);
        BackpackRole::firstOrCreate(['name' => 'Guest'], ['description' => 'Guest']);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::create([
            'username' => 'test',
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'test@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
            'role_id' => Role::where('name', 'User')->first()->id,
            'backpack_role_id' => BackpackRole::where('name', 'Guest')->first()->id,
        ]);
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		$response = $this->post("/login", [
			"email" => $user->email,
			"password" => "password",
		]);

		$this->assertAuthenticated();
		$response->assertNoContent();
	}

<<<<<<< HEAD
	public function test_users_can_not_authenticate_with_invalid_password(): void
	{
		$user = User::create([
			"ndg" => "test2@example.com",
			"email" => "test2@example.com",
			"password" => bcrypt("password"),
			"email_verified_at" => now(),
			"role_id" => Role::where("name", "User")->first()->id,
			"backpack_role_id" => BackpackRole::where("name", "Guest")->first()->id,
		]);
=======
    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::create([
            'username' => 'test2',
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'test2@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
            'role_id' => Role::where('name', 'User')->first()->id,
            'backpack_role_id' => BackpackRole::where('name', 'Guest')->first()->id,
        ]);
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		$this->post("/login", [
			"email" => $user->email,
			"password" => "wrong-password",
		]);

		$this->assertGuest();
	}

<<<<<<< HEAD
	public function test_users_can_logout(): void
	{
		$user = User::create([
			"ndg" => "test3@example.com",
			"email" => "test3@example.com",
			"password" => bcrypt("password"),
			"email_verified_at" => now(),
			"role_id" => Role::where("name", "User")->first()->id,
			"backpack_role_id" => BackpackRole::where("name", "Guest")->first()->id,
		]);
=======
    public function test_users_can_logout(): void
    {
        $user = User::create([
            'username' => 'test3',
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'test3@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
            'role_id' => Role::where('name', 'User')->first()->id,
            'backpack_role_id' => BackpackRole::where('name', 'Guest')->first()->id,
        ]);
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		$response = $this->actingAs($user)->post("/logout");

		$this->assertGuest();
		$response->assertNoContent();
	}
}
