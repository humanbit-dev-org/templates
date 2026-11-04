<?php

namespace Tests\Feature\Auth\session_cookie_auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
	use RefreshDatabase;

<<<<<<< HEAD
	public function test_new_users_can_register(): void
	{
		$response = $this->post("/register", [
			"name" => "Test User",
			"email" => "test@example.com",
			"password" => "password",
			"password_confirmation" => "password",
		]);
=======
    protected function setUp(): void
    {
        parent::setUp();

        // These tests cover the optional Sanctum session-cookie auth routes,
        // commented out in routes/auth.php: skip them while that mode is off.
        if (! Route::has('register')) {
            $this->markTestSkipped('Session-cookie auth routes are disabled (routes/auth.php).');
        }
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		$this->assertAuthenticated();
		$response->assertNoContent();
	}
}
