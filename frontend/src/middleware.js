import { NextResponse } from "next/server";

const locales = ["it"];
const defaultLocale = "it";

// Get locale from user's cookie
function getLocaleFromCookie(request) {
	return request.cookies.get("locale")?.value;
}

// Get locale from browser header
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

	// Step 1: Determine the preferred locale
	let locale = getLocaleFromCookie(request);
	if (!locale) {
		locale = getLocaleFromHeader(request) || defaultLocale;
	}

	// Check if URL contains a supported locale
	const pathnameHasLocale = locales.some((loc) => pathname.startsWith(`/${loc}/`) || pathname === `/${loc}`);

	// Allow CSS map files to load normally
	if (pathname.endsWith(".css.map")) {
		return NextResponse.next();
	}

	// Redirect "/[locale]/home" to the default home URL "/[locale]"
	for (const loc of locales) {
		if (pathname === `/${loc}/home`) {
			return NextResponse.redirect(new URL(`/${loc}`, request.url));
		}

		// Internally rewrite "/[locale]" to serve the page from "/[locale]/home"
		if (pathname === `/${loc}`) {
			return NextResponse.rewrite(new URL(`/${loc}/home`, request.url));
		}
	}

	// Handle URLs with a locale prefix normally
	if (pathnameHasLocale) {
		const matchedLocale = locales.find((loc) => pathname.startsWith(`/${loc}`));
		const response = NextResponse.next();

		// Update cookie if locale in URL differs from cookie
		if (matchedLocale && matchedLocale !== locale) {
			response.cookies.set("locale", matchedLocale, { path: "/", maxAge: 60 * 60 * 24 * 30 });
		} else {
			response.cookies.set("locale", locale, { path: "/", maxAge: 60 * 60 * 24 * 30 });
		}
		return response;
	}

	// Redirect URLs without locale to the localized version
	const response = NextResponse.redirect(
		new URL(`/${locale}${pathname}${searchParams ? `?${searchParams}` : ""}`, request.url)
	);
	response.cookies.set("locale", locale, { path: "/", maxAge: 60 * 60 * 24 * 30 });
	return response;
}

export const config = {
	matcher: ["/((?!_next|api|favicon|fonts|images|videos).*)"],
};
