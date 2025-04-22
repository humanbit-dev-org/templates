import { NextResponse } from "next/server";

const locales = ["it"]; // Defined supported 2-letter locales
const defaultLocale = "it";

// Read locale from user's cookie (if present)
function getLocaleFromCookie(request) {
	return request.cookies.get("locale")?.value;
}

// Read locale from Accept-Language header (if matched)
function getLocaleFromHeader(request) {
	const acceptLanguage = request.headers.get("accept-language");
	if (!acceptLanguage) return null;

	const match = acceptLanguage.match(/\b[a-z]{2}\b/g);
	return match?.find((code) => locales.includes(code)) || null;
}

// Extract locale from URL path (must be the first segment)
function extractLocaleFromPath(pathname) {
	const match = pathname.match(/^\/([a-z]{2})(\/|$)/);
	const code = match?.[1];
	return locales.includes(code) ? code : null;
}

// Main middleware logic for locale handling
export function middleware(request) {
	const { pathname, searchParams } = request.nextUrl;

	// Determine the preferred locale
	let locale = getLocaleFromCookie(request) || getLocaleFromHeader(request) || defaultLocale;

	const pathLocale = extractLocaleFromPath(pathname);

	// Allow `.css.map` files to load normally
	if (pathname.endsWith(".css.map")) {
		return NextResponse.next();
	}

	// Redirect `/[locale]/home` to `/[locale]` to handle `/home` route
	if (pathLocale && pathname === `/${pathLocale}/home`) {
		return NextResponse.redirect(new URL(`/${pathLocale}`, request.url));
	}

	// Rewrite `/[locale]` as `/[locale]/home` for default entry point
	if (pathLocale && pathname === `/${pathLocale}`) {
		const response = NextResponse.rewrite(new URL(`/${pathLocale}/home`, request.url));

		// Set custom header with rewritten pathname
		response.headers.set("x-pathname", `/${pathLocale}/home`);

		return response;
	}

	// Locale is present in path → update cookie and continue
	if (pathLocale) {
		const response = NextResponse.next();

		// Set custom header with current pathname
		response.headers.set("x-pathname", pathname);

		// Update cookie if locale in path differs from stored value
		response.cookies.set("locale", pathLocale, {
			path: "/",
			maxAge: 60 * 60 * 24 * 30,
		});

		return response;
	}

	// Locale is missing → redirect to `/${locale}/*` and set cookie
	const response = NextResponse.redirect(
		new URL(`/${locale}${pathname}${searchParams ? `?${searchParams}` : ""}`, request.url)
	);

	// Store resolved locale in cookie
	response.cookies.set("locale", locale, {
		path: "/",
		maxAge: 60 * 60 * 24 * 30,
	});

	return response;
}

// Apply middleware to all paths except static and special folders
export const config = {
	matcher: ["/((?!_next|api|favicon|fonts|images|js|videos).*)"],
};
