<?php

namespace Tests\Feature\Auth;

use App\Models\BackpackRole;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\SamlSignatureVerifier;
use App\Services\Logging\SamlSimulationLogService;
use App\Services\Restricted\SamlSimulationScenarioService;
use Database\Seeders\BackpackRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * End-to-end check of the Auth Simulation page: the operator picks a user,
 * the page builds a SAMLResponse, and the simulation twin of the SAML
 * endpoint logs that user in by handing the frontend an apiToken cookie.
 */
class SamlSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, BackpackRoleSeeder::class]);

        // Keep the audit log of these simulations out of the real storage folder.
        $logPath = tempnam(sys_get_temp_dir(), 'saml-sim-log');
        $this->app->instance(SamlSimulationLogService::class, new class($logPath) extends SamlSimulationLogService
        {
            public function __construct(private string $logPath) {}

            public function path(): string
            {
                return $this->logPath;
            }
        });

        // The simulation pages sit behind shield:auth_sim (Backpack Developer/Admin).
        $operator = User::query()->create([
            'username' => 'operator',
            'email' => 'operator@example.com',
            'name' => 'Op',
            'surname' => 'Erator',
            'role_id' => Role::query()->where('name', 'User')->value('id'),
            'backpack_role_id' => BackpackRole::query()->where('name', 'Developer')->value('id'),
            'password' => 'secret-password',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($operator, backpack_guard_name());
    }

    public function test_simulation_page_renders_the_generic_scenarios(): void
    {
        $this->get(route('auth.sim.form'))
            ->assertOk()
            ->assertSee(SamlSimulationScenarioService::SCENARIOS['verified']['label'])
            ->assertSee(SamlSimulationScenarioService::ERROR_SCENARIOS['unknown-user']['label']);
    }

    public function test_manual_username_logs_the_user_in_through_the_simulated_assertion(): void
    {
        // An SSO user: its password is the username itself, HKDF-derived by the
        // argon2id driver (what auth:rehash-passwords writes).
        User::query()->create([
            'username' => 'mrossi',
            'email' => 'mrossi@example.com',
            'name' => 'Mario',
            'surname' => 'Rossi',
            'role_id' => Role::query()->where('name', 'User')->value('id'),
            'password' => 'mrossi',
            'email_verified_at' => now(),
        ]);

        $samlResponse = $this->buildSamlResponseFor('mrossi');

        $this->assertStringContainsString('<saml:AttributeValue>mrossi</saml:AttributeValue>', base64_decode($samlResponse));

        $response = $this->post(route('auth.sim.saml'), ['SAMLResponse' => $samlResponse]);

        $response->assertRedirect(rtrim(config('app.frontend_url'), '/'));
        $this->assertNotEmpty($response->getCookie('apiToken', false)?->getValue());
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => 'mrossi', 'name' => 'api-token']);
    }

    public function test_unknown_username_clears_the_token_cookie(): void
    {
        $response = $this->post(route('auth.sim.saml'), [
            'SAMLResponse' => $this->buildSamlResponseFor('nobody'),
        ]);

        $response->assertRedirect(rtrim(config('app.frontend_url'), '/'));
        $this->assertSame('', (string) $response->getCookie('apiToken', false)?->getValue());
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_scenario_without_candidates_returns_to_the_form_with_an_error(): void
    {
        // The only frontend-eligible users would be non-Backpack ones: there are none.
        $this->post(route('auth.sim.post'), ['scenario' => 'verified'])
            ->assertRedirect(route('auth.sim.form'))
            ->assertSessionHasErrors('scenario');
    }

    public function test_missing_saml_response_is_rejected(): void
    {
        $this->post(route('auth.sim.saml'))->assertStatus(400);
    }

    public function test_signature_verification_fails_closed_without_an_idp_certificate(): void
    {
        config(['saml.idp_certificate' => '']);

        $this->expectException(RuntimeException::class);

        app(SamlSignatureVerifier::class)->verifiedNodes(base64_decode($this->buildSamlResponseFor('mrossi')));
    }

    /**
     * Run the page's auto-post step and read the SAMLResponse it would submit.
     */
    private function buildSamlResponseFor(string $username): string
    {
        $view = $this->post(route('auth.sim.post'), ['username' => $username, 'scenario' => 'manual'])
            ->assertOk()
            ->viewData('samlResponse');

        $this->assertIsString($view);

        return $view;
    }
}
