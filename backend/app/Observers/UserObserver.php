<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Cache\BusinessConsoleListCache;
use App\Services\Cache\CacheKeyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Invalidates dashboard and filter cache on User Eloquent lifecycle events.
 *
 * COVERAGE:
 * - Backpack admin: single-row create/update/delete (Eloquent active) —
 *   this includes daily fixes to user fields, filiale transfers, suspensions
 * - Business Console (product_owner): User create/update via frontend API —
 *   BancaController / UserController use Eloquent model operations
 *
 * NOT COVERED (intentional):
 * - ImportJobService User import uses User::create() (Eloquent) but also
 *   User::where()->update() in CsvTransformer (bypass). The ImportCsvAction
 *   (Backpack UI import) uses Eloquent create/update — Observer fires correctly.
 *   For job imports, post-import flush handles it.
 *
 * SPECIAL CASE — filiale transfer:
 * When a user moves to a different filiale, both the OLD and NEW filiale scope
 * must be invalidated. $user->getOriginal('filiale_id') captures the previous value
 * before Eloquent commits the change.
 *
 * BACKPACK ADMIN USERS:
 * Users with backpack_role_id set are admin users — they never appear in dashboard
 * charts. Skip invalidation for them to avoid unnecessary cache churn.
 */
class UserObserver
{
    public function created(User $user): void
    {
        $this->invalidate($user);
    }

    public function updated(User $user): void
    {
        // Detect filiale transfer: invalidate old filiale scope BEFORE the new one
        if ($user->wasChanged('filiale_id') && ! empty($user->getOriginal('filiale_id'))) {
            $oldFilialeId = $user->getOriginal('filiale_id');
            $oldFilialeScopeKey = CacheKeyService::scopeKey([
                'scope' => 'filiale',
                'filiale_id' => $oldFilialeId,
            ]);

            foreach (CacheKeyService::allDashboardKeys($oldFilialeScopeKey) as $key) {
                Cache::forget($key);
            }

            Cache::forget(CacheKeyService::filterFiliali($oldFilialeScopeKey));
            BusinessConsoleListCache::forgetForUserScope($user->banca_abi, $oldFilialeId);

            Log::debug('UserObserver: old filiale scope invalidated', [
                'user_id' => $user->getKey(),
                'old_filiale_id' => $oldFilialeId,
            ]);
        }

        $this->invalidate($user);
    }

    public function deleted(User $user): void
    {
        $this->invalidate($user);
    }

    private function invalidate(User $user): void
    {
        if (CacheInvalidationContext::isSuppressed()) {
            return;
        }

        // Admin users (backpack_role_id set) never appear in dashboard charts
        if (! empty($user->backpack_role_id)) {
            return;
        }

        if (empty($user->banca_abi)) {
            return;
        }

        $keys = CacheKeyService::allDashboardKeysForBanca(
            $user->banca_abi,
            $user->filiale_id
        );

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        BusinessConsoleListCache::forgetForUserScope($user->banca_abi, $user->filiale_id);

        Log::debug('UserObserver: invalidated', [
            'user_id' => $user->getKey(),
            'keys' => count($keys),
        ]);
    }
}
