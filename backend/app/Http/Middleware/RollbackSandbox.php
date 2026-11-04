<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wraps every sandbox call in a transaction that is always rolled back.
 *
 * The sandbox has no state: requests execute normally against the demo data
 * and are then undone, so the same test IDs keep working forever. afterCommit
 * callbacks never fire, and Sanctum's last_used_at still persists because it
 * is written by the auth middleware before this transaction begins.
 */
class RollbackSandbox
{
    public function handle(Request $request, Closure $next): Response
    {
        $db = DB::connection('sandbox');
        $level = $db->transactionLevel();
        $db->beginTransaction();

        try {
            return $next($request);
        } finally {
            $db->rollBack($level);
        }
    }
}
