import { NextResponse } from "next/server";

const defaultLocale = "it";

// Get locale from user's cookie
function getLocaleFromCookie(request) {
	return request.cookies.get("locale")?.value;
}

// Get locale from browser header (returns first 2-letter match)
function getLocaleFromHeader(request) {
	const acceptLanguage = request.headers.get("accept-language");
	if (acceptLanguage) {
		const match = acceptLanguage.match(/\b[a-z]{2}\b/);
		return match?.[0] || null;
	}
	return null;
}

// Extract the locale from the URL if it's a 2-letter lowercase segment
function extractLocaleFromPath(pathname) {
	const match = pathname.match(/^\/([a-z]{2})(\/|$)/);
	return match?.[1] || null;
}

export function middleware(request) {
	const { pathname, searchParams } = request.nextUrl;

	// Determine the preferred locale
	let locale = getLocaleFromCookie(request);
	if (!locale) {
		locale = getLocaleFromHeader(request) || defaultLocale;
	}

	const pathLocale = extractLocaleFromPath(pathname);
	const hasLocale = !!pathLocale;

	// Allow CSS map files to load normally
	if (pathname.endsWith(".css.map")) {
		return NextResponse.next();
	}

	// Redirect "/[locale]/home" to "/[locale]"
	if (pathname === `/${pathLocale}/home`) {
		return NextResponse.redirect(new URL(`/${pathLocale}`, request.url));
	}

	// Rewrite "/[locale]" to serve "/[locale]/home"
	if (pathname === `/${pathLocale}`) {
		return NextResponse.rewrite(new URL(`/${pathLocale}/home`, request.url));
	}

	// Handle requests that already have a valid locale in the path
	if (hasLocale) {
		const response = NextResponse.next();

		// Update cookie if needed
		if (pathLocale !== locale) {
			response.cookies.set("locale", pathLocale, { path: "/", maxAge: 60 * 60 * 24 * 30 });
		} else {
			response.cookies.set("locale", locale, { path: "/", maxAge: 60 * 60 * 24 * 30 });
		}
		return response;
	}

	// Redirect URLs without a locale to include it
	const response = NextResponse.redirect(
		new URL(`/${locale}${pathname}${searchParams ? `?${searchParams}` : ""}`, request.url)
	);
	response.cookies.set("locale", locale, { path: "/", maxAge: 60 * 60 * 24 * 30 });
	return response;
}

export const config = {
	matcher: ["/((?!_next|api|favicon|fonts|images|js|videos).*)"],
};
