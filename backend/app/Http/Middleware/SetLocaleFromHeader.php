<?php

namespace App\Http\Middleware;

use App\Support\Locale\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the X-Locale header the frontend sends on every API call (Api.jsx
 * buildHeaders, server.js serverFetch), so __('api.*') messages come back in
 * the language the user picked. Without the header (Swagger, API Lab, portal
 * clients) or with an unsupported one, the app default stays.
 */
class SetLocaleFromHeader
{
	public function handle(Request $request, Closure $next): Response
	{
		$locale = $request->header("X-Locale");

		if (in_array($locale, Locales::SUPPORTED, true)) {
			App::setLocale($locale);
		}

		return $next($request);
	}
}
