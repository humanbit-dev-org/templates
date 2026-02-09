<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;

class RollbackDocs
{
	/**
	 * Handle an incoming request.
	 *
	 * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
	 */
	public function handle(Request $request, Closure $next): Response
	{
		$mutating = in_array($request->method(), ["POST", "PUT", "PATCH", "DELETE"], true);
		$fromDocs = $request->headers->get("X-Docs-Request") === "1";

		if (!$mutating || !$fromDocs) {
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
