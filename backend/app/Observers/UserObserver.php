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

<<<<<<< HEAD
	public function updated(User $user): void
	{
		// Detect filiale transfer: invalidate old filiale scope BEFORE the new one
		if ($user->wasChanged("filiale_id") && !empty($user->getOriginal("filiale_id"))) {
			$oldFilialeId = $user->getOriginal("filiale_id");
			$oldFilialeScopeKey = CacheKeyService::scopeKey([
				"scope" => "filiale",
				"filiale_id" => $oldFilialeId,
			]);

			foreach (CacheKeyService::allDashboardKeys($oldFilialeScopeKey) as $key) {
				Cache::forget($key);
			}

			Cache::forget(CacheKeyService::filterFiliali($oldFilialeScopeKey));
			BusinessConsoleListCache::forgetForUserScope($user->banca_abi, $oldFilialeId);

			Log::debug("UserObserver: old filiale scope invalidated", [
				"user_id" => $user->getKey(),
				"old_filiale_id" => $oldFilialeId,
			]);
		}

		$this->invalidate($user);
	}
=======
    public function updated(User $user): void
    {
        $this->invalidate($user);
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

	public function deleted(User $user): void
	{
		$this->invalidate($user);
	}

	private function invalidate(User $user): void
	{
		if (CacheInvalidationContext::isSuppressed()) {
			return;
		}

<<<<<<< HEAD
		// Admin users (backpack_role_id set) never appear in dashboard charts
		if (!empty($user->backpack_role_id)) {
			return;
		}

		if (empty($user->banca_abi)) {
			return;
		}

		$keys = CacheKeyService::allDashboardKeysForBanca($user->banca_abi, $user->filiale_id);
=======
        $keys = CacheKeyService::forUser($user);
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		foreach ($keys as $key) {
			Cache::forget($key);
		}

<<<<<<< HEAD
		BusinessConsoleListCache::forgetForUserScope($user->banca_abi, $user->filiale_id);

		Log::debug("UserObserver: invalidated", [
			"user_id" => $user->getKey(),
			"keys" => count($keys),
		]);
	}
=======
        if ($keys !== []) {
            Log::debug('UserObserver: invalidated', [
                'user' => $user->getKey(),
                'keys' => count($keys),
            ]);
        }
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
}
