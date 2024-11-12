import { NextResponse } from "next/server";

let locales = ["en", "it"]; // Supported locales
const defaultLocale = "en"; // Default locale

function getLocaleFromCookie(request) {
	return request.cookies.get("locale")?.value;
}

function getLocaleFromHeader(request) {
	const acceptLanguage = request.headers.get("accept-language");
	if (acceptLanguage) {
		for (const locale of locales) {
			if (acceptLanguage.includes(locale)) {
				return locale;
			}
		}
	}
	return null;
}

export function middleware(request) {
	const { pathname, searchParams } = request.nextUrl;

	// Check if the pathname already contains a locale
	const pathnameHasLocale = locales.some((locale) => pathname.startsWith(`/${locale}/`) || pathname === `/${locale}`);

	// Handle requests for .css.map files to prevent 404 errors
	if (pathname.endsWith(".css.map")) {
		return NextResponse.next(); // Simply allow the request to continue (or redirect if needed)
	}

	// Step 1: Determine the preferred locale
	let locale = getLocaleFromCookie(request); // Cookie has priority
	if (!locale) {
		locale = getLocaleFromHeader(request) || defaultLocale; // Fallback to header or default
	}

	// Step 2: Handle paths with a locale
	if (pathnameHasLocale) {
		const matchedLocale = locales.find((locale) => pathname.startsWith(`/${locale}`));
		if (matchedLocale && matchedLocale !== locale) {
			// Update the cookie if the locale in the path differs from the cookie value
			const response = NextResponse.next();
			response.cookies.set("locale", matchedLocale, { path: "/", maxAge: 60 * 60 * 24 * 30 });
			return response;
		}

		// Proceed normally, ensuring the cookie is set
		const response = NextResponse.next();
		response.cookies.set("locale", locale, { path: "/", maxAge: 60 * 60 * 24 * 30 });
		return response;
	}

	// Step 3: Handle paths without a locale
	const response = NextResponse.redirect(
		new URL(`/${locale}${pathname}${searchParams ? `?${searchParams}` : ""}`, request.url)
	);
	response.cookies.set("locale", locale, { path: "/", maxAge: 60 * 60 * 24 * 30 }); // Set the cookie
	return response;
}

export const config = {
	matcher: [
		// Apply middleware to all paths except those under _next (internal paths), public folder and API routes
		"/((?!_next|api|favicon|fonts|images|videos).*)",
	],
};
