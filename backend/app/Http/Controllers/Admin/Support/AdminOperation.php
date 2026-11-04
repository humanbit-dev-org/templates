<?php

namespace App\Http\Controllers\Admin\Support;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * JSON endpoints behind the Backpack dashboard widgets.
 *
 * Read-only helpers the admin UI calls over AJAX (see routes/backpack/custom.php).
 */
class AdminOperation extends Controller
{
    /**
     * Search users for the dashboard widgets.
     *
     * - ?username=… returns that single user with its roles;
     * - ?q=… (at least 2 chars) returns up to ?limit=… matches on username,
     *   name, surname or email.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        try {
            $query = (string) $request->get('q', '');
            $username = (string) $request->get('username', '');
            $limit = min((int) $request->get('limit', 10), 50);

            if ($username !== '') {
                $user = User::query()->with(['role', 'backpackRole'])->find($username);

                return $user
                    ? response()->json(['user' => $user])
                    : response()->json(['error' => 'Utente non trovato'], 404);
            }

            if (mb_strlen($query) < 2) {
                return response()->json(['users' => []]);
            }

            $users = User::query()
                ->where(function ($q) use ($query) {
                    foreach (['username', 'name', 'surname', 'email'] as $column) {
                        $q->orWhere($column, 'LIKE', "%{$query}%");
                    }
                })
                ->select('username', 'name', 'surname', 'email')
                ->limit($limit)
                ->get();

            return response()->json(['users' => $users->toArray()]);
        } catch (\Exception $e) {
            Log::error('Search users error: '.$e->getMessage());

            return response()->json(['error' => 'Errore interno del server'], 500);
        }
    }

    /**
     * Full detail of a user with its frontend and Backpack roles.
     */
    public function getUserDetails(string $username): JsonResponse
    {
        $user = User::query()->with(['role', 'backpackRole'])->findOrFail($username);

        return response()->json(['user' => $user]);
    }
}
