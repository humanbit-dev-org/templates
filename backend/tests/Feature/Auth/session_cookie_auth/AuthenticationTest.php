<?php

namespace Tests\Feature\Auth;

use App\Models\BackpackRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create required roles if they don't exist
        Role::firstOrCreate(['name' => 'User'], ['description' => 'User']);
        BackpackRole::firstOrCreate(['name' => 'Guest'], ['description' => 'Guest']);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::create([
            'ndg' => 'test@example.com',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role_id' => Role::where('name', 'User')->first()->id,
            'backpack_role_id' => BackpackRole::where('name', 'Guest')->first()->id,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertNoContent();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::create([
            'ndg' => 'test2@example.com',
            'email' => 'test2@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role_id' => Role::where('name', 'User')->first()->id,
            'backpack_role_id' => BackpackRole::where('name', 'Guest')->first()->id,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::create([
            'ndg' => 'test3@example.com',
            'email' => 'test3@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role_id' => Role::where('name', 'User')->first()->id,
            'backpack_role_id' => BackpackRole::where('name', 'Guest')->first()->id,
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertNoContent();
    }
}
