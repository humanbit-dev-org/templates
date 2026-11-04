<?php

namespace Tests\Feature\Portal;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The API portal token exchange (POST /api/v1/token): a 'portal-refresh:*'
 * credential is traded for a short-lived 'portal-access' token, gated by the
 * configurable portal roles (config/portal.php 'roles').
 */
class PortalTokenExchangeTest extends TestCase
{
	use RefreshDatabase;

	private User $client;

	protected function setUp(): void
	{
		parent::setUp();

		$this->seed(RoleSeeder::class);

		$this->client = User::query()->create([
			"username" => "acme",
			"email" => "api@acme.test",
			"name" => "Acme",
			"surname" => "Client",
			"role_id" => Role::query()->where("name", "User")->value("id"),
			"password" => "secret-password",
		]);
	}

	public function test_refresh_token_is_exchanged_for_an_access_token(): void
	{
		$refresh = $this->client->createToken(config("portal.refresh_token_prefix") . ":abcd1234", ["portal:auth"]);

		$response = $this->withToken($refresh->plainTextToken)->postJson("/api/v1/token");

		$response->assertCreated()->assertJsonStructure(["token", "expires_at"]);
		$this->assertDatabaseHas("personal_access_tokens", [
			"tokenable_id" => "acme",
			"name" => config("portal.api_token_name"),
		]);
	}

	public function test_a_non_refresh_token_cannot_mint_access_tokens(): void
	{
		$plain = $this->client->createToken("api-token")->plainTextToken;

		$this->withToken($plain)->postJson("/api/v1/token")->assertForbidden();
	}

	public function test_portal_roles_restrict_who_may_hold_credentials(): void
	{
		$this->assertTrue($this->client->canUsePortal());

		config(["portal.roles" => ["Partner"]]);

		$this->assertFalse($this->client->fresh()->canUsePortal());

		$refresh = $this->client->createToken(config("portal.refresh_token_prefix") . ":abcd1234", ["portal:auth"]);
		$this->withToken($refresh->plainTextToken)->postJson("/api/v1/token")->assertForbidden();
	}
}
