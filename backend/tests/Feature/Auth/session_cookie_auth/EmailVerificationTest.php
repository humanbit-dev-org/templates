<?php

namespace Tests\Feature\Auth\session_cookie_auth;

use App\Models\BackpackRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
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

	public function test_email_can_be_verified(): void
	{
		$user = User::create([
			"ndg" => "unverified@example.com",
			"email" => "unverified@example.com",
			"password" => bcrypt("password"),
			"email_verified_at" => null,
			"role_id" => Role::where("name", "User")->first()->id,
			"backpack_role_id" => BackpackRole::where("name", "Guest")->first()->id,
		]);
=======
    protected function setUp(): void
    {
        parent::setUp();

        // These tests cover the optional Sanctum session-cookie auth routes,
        // commented out in routes/auth.php: skip them while that mode is off.
        if (! Route::has('user.verification.verify')) {
            $this->markTestSkipped('Session-cookie auth routes are disabled (routes/auth.php).');
        }
        // Create required roles if they don't exist
        Role::firstOrCreate(['name' => 'User'], ['description' => 'User']);
        BackpackRole::firstOrCreate(['name' => 'Guest'], ['description' => 'Guest']);
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::create([
            'username' => 'unverified',
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'unverified@example.com',
            'password' => 'password',
            'email_verified_at' => null,
            'role_id' => Role::where('name', 'User')->first()->id,
            'backpack_role_id' => BackpackRole::where('name', 'Guest')->first()->id,
        ]);
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		Event::fake();

		$verificationUrl = URL::temporarySignedRoute("user.verification.verify", now()->addMinutes(60), [
			"id" => $user->id,
			"hash" => sha1($user->email),
		]);

		$response = $this->actingAs($user)->get($verificationUrl);

		Event::assertDispatched(Verified::class);
		$this->assertTrue($user->fresh()->hasVerifiedEmail());
		$response->assertRedirect(config("app.frontend_url") . "/dashboard?verified=1");
	}

<<<<<<< HEAD
	public function test_email_is_not_verified_with_invalid_hash(): void
	{
		$user = User::create([
			"ndg" => "unverified2@example.com",
			"email" => "unverified2@example.com",
			"password" => bcrypt("password"),
			"email_verified_at" => null,
			"role_id" => Role::where("name", "User")->first()->id,
			"backpack_role_id" => BackpackRole::where("name", "Guest")->first()->id,
		]);
=======
    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::create([
            'username' => 'unverified2',
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'unverified2@example.com',
            'password' => 'password',
            'email_verified_at' => null,
            'role_id' => Role::where('name', 'User')->first()->id,
            'backpack_role_id' => BackpackRole::where('name', 'Guest')->first()->id,
        ]);
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		$verificationUrl = URL::temporarySignedRoute("user.verification.verify", now()->addMinutes(60), [
			"id" => $user->id,
			"hash" => sha1("wrong-email"),
		]);

		$this->actingAs($user)->get($verificationUrl);

		$this->assertFalse($user->fresh()->hasVerifiedEmail());
	}
}
