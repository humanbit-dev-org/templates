<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Cache\CacheKeyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Invalidates the user-dependent cache on User Eloquent lifecycle events.
 *
 * COVERAGE:
 * - Backpack admin: single-row create/update/delete (Eloquent active)
 * - Frontend API: User create/update through Eloquent model operations
 *
 * NOT COVERED (intentional):
 * - Bulk imports that bypass Eloquent or suppress Observers through
 *   CacheInvalidationContext: CacheFlush::afterImport() runs once at the end.
 *
 * Which keys are dropped is decided by CacheKeyService::forUser().
 */
class UserObserver
{
    public function created(User $user): void
    {
        $this->invalidate($user);
    }

    public function updated(User $user): void
    {
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

        $keys = CacheKeyService::forUser($user);

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        if ($keys !== []) {
            Log::debug('UserObserver: invalidated', [
                'user' => $user->getKey(),
                'keys' => count($keys),
            ]);
        }
    }
}
