<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wraps mutating API requests from Swagger Try It in a transaction that is always rolled back.
 *
 * When X-Docs-Request: 1 or X-Api-Lab-Request: 1 is present, POST/PUT/PATCH/DELETE operations
 * run inside a transaction that is rolled back in a finally block. This ensures that API testing
 * from Swagger or the Backpack API Lab never persists real data changes.
 *
 * @see backend/resources/views/vendor/scramble/docs.blade.php (fetch interceptor adds X-Docs-Request)
 * @see App\Services\ApiLab\ApiLab (adds X-Api-Lab-Request)
 */
class RollbackDocs
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $mutating = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $fromDocs = $request->headers->get('X-Docs-Request') === '1';
        $fromApiLab = $request->headers->get('X-Api-Lab-Request') === '1';

        if (! $mutating || (! $fromDocs && ! $fromApiLab)) {
            return $next($request);
        }

        DB::beginTransaction();
        try {
            $response = $next($request);
        } finally {
            DB::rollBack();
        }

        return $response;
    }
}
