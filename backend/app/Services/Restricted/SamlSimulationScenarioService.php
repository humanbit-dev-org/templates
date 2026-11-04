<?php

namespace App\Services\Restricted;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Scenarios of the Auth Simulation page: each one picks a random user in a
 * given state, so an operator can log in as "a user like that" without
 * knowing any username.
 *
 * Only frontend users are candidates (backpack_role_id empty): Backpack
 * operators log into the admin panel, not through SSO.
 *
 * Adding a scenario: add an entry to SCENARIOS (or ERROR_SCENARIOS) and the
 * matching constraint in applyScenario().
 */
class SamlSimulationScenarioService
{
    /** The manual entry form: the operator types the username. */
    public const SCENARIO_MANUAL = 'manual';

    /**
     * Login scenarios: key => [label, status badge].
     *
     * @var array<string, array{label: string, status: string}>
     */
    public const SCENARIOS = [
        'verified' => ['label' => 'Utente con email verificata', 'status' => 'ok'],
        'unverified' => ['label' => 'Utente con email non verificata', 'status' => 'pending'],
    ];

    /**
     * Scenarios that must fail, to exercise the error paths of the login:
     * key => [label, translate code shown as badge].
     *
     * @var array<string, array{label: string, translate_code: string}>
     */
    public const ERROR_SCENARIOS = [
        'unknown-user' => ['label' => 'Username inesistente', 'translate_code' => 'user_not_found'],
    ];

    /**
     * Login scenarios with their candidate counts, for the page.
     *
     * @return array<string, array{key: string, label: string, status: string, count: int, available: bool}>
     */
    public function catalog(): array
    {
        $catalog = [];

        foreach (self::SCENARIOS as $key => $scenario) {
            $count = $this->candidates($key)->count();
            $catalog[$key] = [
                'key' => $key,
                'label' => $scenario['label'],
                'status' => $scenario['status'],
                'count' => $count,
                'available' => $count > 0,
            ];
        }

        return $catalog;
    }

    /**
     * Error scenarios, always available: they need no existing user.
     *
     * @return array<string, array{key: string, label: string, translate_code: string, count: int, available: bool}>
     */
    public function errorCatalog(): array
    {
        $catalog = [];

        foreach (self::ERROR_SCENARIOS as $key => $scenario) {
            $catalog[$key] = [
                'key' => $key,
                'label' => $scenario['label'],
                'translate_code' => $scenario['translate_code'],
                'count' => 1,
                'available' => true,
            ];
        }

        return $catalog;
    }

    public function labelFor(string $scenario): string
    {
        return self::SCENARIOS[$scenario]['label']
            ?? self::ERROR_SCENARIOS[$scenario]['label']
            ?? 'Inserimento manuale';
    }

    public function isKnownScenario(string $scenario): bool
    {
        return $scenario === self::SCENARIO_MANUAL
            || isset(self::SCENARIOS[$scenario])
            || isset(self::ERROR_SCENARIOS[$scenario]);
    }

    /**
     * Username to log in with for a scenario: a random candidate, or a
     * username guaranteed not to exist for the "unknown-user" error scenario.
     * Null when the scenario has no candidate.
     */
    public function randomUsernameForScenario(string $scenario): ?string
    {
        if ($scenario === 'unknown-user') {
            return 'sim-unknown-'.Str::lower(Str::random(10));
        }

        if (! isset(self::SCENARIOS[$scenario])) {
            return null;
        }

        return $this->candidates($scenario)->inRandomOrder()->value('username');
    }

    /**
     * The Backpack operator running the simulation, for the page header and
     * the audit log.
     *
     * @return array{user_id: string|null, email: string|null, role: string|null}
     */
    public function operatorContext(): array
    {
        $operator = backpack_auth()->user();

        return [
            'user_id' => $operator?->getKey(),
            'email' => $operator?->email,
            'role' => $operator?->backpackRole?->name,
        ];
    }

    /**
     * Frontend users matching a login scenario.
     */
    private function candidates(string $scenario): Builder
    {
        $query = User::query()->whereNull('backpack_role_id');

        return $this->applyScenario($query, $scenario);
    }

    private function applyScenario(Builder $query, string $scenario): Builder
    {
        return match ($scenario) {
            'verified' => $query->whereNotNull('email_verified_at'),
            'unverified' => $query->whereNull('email_verified_at'),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
