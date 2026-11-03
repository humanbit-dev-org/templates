<?php

namespace App\Services\Logging;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;

/**
 * Persists SAML simulation audit entries for the restricted simulator UI.
 */
class SamlSimulationLogService
{
    public const PER_PAGE = 10;

    public function path(): string
    {
        return storage_path('app/restricted/saml-simulation-log.json');
    }

    /**
     * @return LengthAwarePaginator<int, array{
     *     simulated_user_id: string,
     *     scenario: string,
     *     scenario_label: string,
     *     operator_user_id: string|null,
     *     operator_email: string|null,
     *     operator_role: string|null,
     *     created_at: string
     * }>
     */
    public function paginate(Request $request, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $entries = $this->readAll();
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * $perPage;

        return (new LengthAwarePaginator(
            array_slice($entries, $offset, $perPage),
            count($entries),
            $perPage,
            $page,
            ['path' => route('auth.sim.form')],
        ))->appends($request->except('page'));
    }

    /**
     * @param  array{
     *     simulated_user_id: string,
     *     scenario: string,
     *     scenario_label: string,
     *     operator_user_id?: string|null,
     *     operator_email?: string|null,
     *     operator_role?: string|null
     * }  $entry
     */
    public function append(array $entry): void
    {
        $entries = $this->readAll();

        array_unshift($entries, [
            'simulated_user_id' => $entry['simulated_user_id'],
            'scenario' => $entry['scenario'],
            'scenario_label' => $entry['scenario_label'],
            'operator_user_id' => $entry['operator_user_id'] ?? null,
            'operator_email' => $entry['operator_email'] ?? null,
            'operator_role' => $entry['operator_role'] ?? null,
            'created_at' => now()->toIso8601String(),
        ]);

        $this->writeAll($entries);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readAll(): array
    {
        $path = $this->path();

        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function writeAll(array $entries): void
    {
        $path = $this->path();
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put(
            $path,
            json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
        );
    }
}
