<?php

namespace Tests\Feature\Auth;

use App\Models\BackpackRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create required roles if they don't exist
        Role::firstOrCreate(['name' => 'User'], ['description' => 'User']);
        BackpackRole::firstOrCreate(['name' => 'Guest'], ['description' => 'Guest']);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::create([
            'ndg' => 'reset@example.com',
            'email' => 'reset@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role_id' => Role::where('name', 'User')->first()->id,
            'backpack_role_id' => BackpackRole::where('name', 'Guest')->first()->id,
        ]);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::create([
            'ndg' => 'reset2@example.com',
            'email' => 'reset2@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role_id' => Role::where('name', 'User')->first()->id,
            'backpack_role_id' => BackpackRole::where('name', 'Guest')->first()->id,
        ]);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (object $notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response->assertSessionHasNoErrors()->assertStatus(200);

            return true;
        });
    }
}
