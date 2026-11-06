// import { getServer, serverFetch } from "@/lib/server"; // File import statement

import { cookies, headers } from "next/headers";

import { ROUTES_META } from "@/config/api-routes";
import * as constants from "@/config/constants"; // Global constants shared across the app
import { getDeviceInfo } from "@/lib/server/device-info"; // Parse `user-agent` to detect device type
import { getPathInfo } from "@/lib/server/path-info"; // Get structured path info from the current request
import { getDictionary } from "@/app/dictionaries";
import { buildTranslateBase, resolveTranslateString } from "@/lib/translate/resolveTranslate";

// ===============================================
// ## ############################################
// ===============================================

/**
 * Default timeout for server-side fetch requests (in milliseconds).
 * Prevents hanging requests from blocking SSR rendering.
 */
const SERVER_FETCH_TIMEOUT = 15000;

/**
 * Custom error class for server-side API failures.
 * Mirrors ApiError from providers/Api.jsx for consistent error handling
 * across SSR and CSR contexts.
 */
export class ServerApiError extends Error {
	constructor(message, status, url, data = null) {
		super(message);
		this.name = "ServerApiError";
		this.status = status;
		this.url = url;
		this.data = data;
		this.isTimeout = status === 408;
		this.isServerError = status >= 500;
		this.isClientError = status >= 400 && status < 500;
	}
}

/**
 * Resolve the locale from the current request Accept-Language header.
 * Falls back to DEFAULT_LOCALE constant if the header is absent.
 *
 * @returns {Promise<string>} Two-letter locale code (e.g. "it", "en")
 */
async function resolveLocale() {
	try {
		const requestHeaders = await headers();
		const acceptLanguage = requestHeaders.get("accept-language") ?? "";
		// Extract the first 2-letter code from e.g. "it-IT,it;q=0.9,en;q=0.8"
		const match = acceptLanguage.match(/\b([a-z]{2})\b/i);
		return match?.[1]?.toLowerCase() ?? constants.DEFAULT_LOCALE ?? "it";
	} catch {
		return constants.DEFAULT_LOCALE ?? "it";
	}
}

/**
 * Fetch the authenticated user from the Laravel API.
 *
 * @param {string|undefined} apiToken
 * @returns {Promise<object|undefined>}
 */
async function fetchUser(apiToken) {
	if (!apiToken) return undefined;

	try {
		const userResponse = await fetch(`${constants.BACKEND_URL_SERVER}/api/user`, {
			method: "GET",
			// credentials: "include",
			headers: {
				"Accept": "application/json",
				"Content-Type": "application/json",
				// "Referer": constants.APP_URL,
				// "X-Requested-With": "XMLHttpRequest",
				// "cookie": "php_session=" + phpSession,
				"Authorization": `Bearer ${apiToken}`,
			},
		});

		const responseData = await userResponse.json();

		// Treat unauthenticated as missing user — not a fatal error
		if (!userResponse.ok || responseData.message === "Unauthenticated.") {
			return undefined;
		}

		return responseData;
	} catch (error) {
		console.error("[server] fetchUser error:", error);
		return undefined;
	}
}

/**
 * Map HTTP status codes to user-friendly error messages.
 * Mirrors getErrorMessage() from providers/Api.jsx for consistency.
 *
 * @param {number} status
 * @returns {string}
 */
/**
 * Check if an endpoint requires auth according to ROUTES_META.
 * Matches endpoint (e.g. "/api/tiraggio/modifica/42") against uri patterns (e.g. "/api/tiraggio/modifica/{tiraggio_id}").
 *
 * @param {string} endpoint - Resolved URI (may contain dynamic values)
 * @returns {boolean}
 */
function endpointRequiresAuth(endpoint) {
	if (!endpoint) return false;
	const meta = Object.values(ROUTES_META).find((m) => {
		const pattern = m.uri
			.replace(/\{[^}]+\}/g, "__PARAM__")
			.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")
			.replace(/__PARAM__/g, "[^/]+");
		return new RegExp(`^${pattern}$`).test(endpoint);
	});
	return meta?.auth === true;
}

const ERROR_STATUS_CODES = {
	401: "error_unauthorized",
	403: "error_forbidden",
	404: "error_not_found",
	408: "error_request_expired",
	422: "error_validation",
	429: "error_too_many",
	500: "error_server",
	502: "error_server",
	503: "error_unavailable",
	504: "error_timeout",
};

function getServerErrorMessage(status, base = null) {
	if (base?.dict) {
		const title = resolveTranslateString({ ...base, code: ERROR_STATUS_CODES[status] ?? "error_request" }).title;
		if (title) return title;
	}
	const messages = {
		401: "Non autorizzato",
		403: "Accesso negato",
		404: "Risorsa non trovata",
		408: "Richiesta scaduta",
		422: "Errore di validazione",
		500: "Errore del server",
		502: "Errore del server",
		503: "Servizio non disponibile",
		504: "Timeout del server",
	};
	return messages[status] ?? "Errore nella richiesta";
}

/**
 * Build a translate `base` scoped to `all` for SSR error fallbacks.
 * Loads the dictionary lazily — callers invoke this only on error paths.
 *
 * @param {string} locale
 * @returns {Promise<object>}
 */
async function buildServerErrorBase(locale) {
	const dict = await getDictionary(locale);
	return buildTranslateBase(null, { dict, lang: locale });
}

/**
 * Resolve @font-face format and preload type from a font file path.
 * Extracts the extension and maps it to the correct CSS format and MIME type.
 *
 * @param {string} fontPath - e.g. "fonts/GT-America.otf"
 * @returns {{ format: string, type: string }}
 */
function getFontFormatAndType(fontPath) {
	const ext = (fontPath || "").toLowerCase().split(".").pop();
	if (ext === "woff2") return { format: "woff2", type: "font/woff2" };
	if (ext === "woff") return { format: "woff", type: "font/woff" };
	// .otf and .ttf use opentype
	if (ext === "otf" || ext === "ttf") return { format: "opentype", type: "font/otf" };
	return { format: "opentype", type: "font/otf" };
}

/**
 * Build tenant branding assets and CSS from the authenticated user's bank interface.
 * Includes fonts (custom or preset), colors, and optional shimmer.
 *
 * Font priority:
 *   1. Custom font files (primary, secondary, tertiary) from backend
 *   2. Preset font families (Google Fonts)
 *
 * Asset URLs use BACKEND_URL_CLIENT (public URL) because they are rendered
 * into the browser DOM — the internal Docker URL (BACKEND_URL_SERVER) is
 * not reachable from the browser.
 *
 * @param {object|undefined} user - Authenticated user object with banca.interfaccia
 * @returns {{
 *   preloadUrlPrimary: string|null,
 *   preloadUrlSecondary: string|null,
 *   preloadUrlTertiary: string|null,
 *   preloadFontTypePrimary: string|null,
 *   preloadFontTypeSecondary: string|null,
 *   preloadFontTypeTertiary: string|null,
 *   fontFamilyStylesheetUrl: string|null,
 *   logoUrl: string|null,
 *   css: string,
 * }}
 */
/**
 * A font family name that is safe to write into the tenant stylesheet.
 *
 * The stylesheet is rendered server-side into a <style> tag through
 * dangerouslySetInnerHTML, so whatever lands here is concatenated into the
 * document: a value carrying "</style>" closes the element early and everything
 * after it is parsed as HTML. Escaping quotes, which is all this used to do, does
 * not help against that.
 *
 * The backend validates these against Interfaccia::FONT_PRESETS, so this is the
 * second lock rather than the only one — but it is the one standing where the
 * string actually becomes markup, and it holds even if a row was written by
 * something other than the validated form. Real font names are letters, digits,
 * spaces and hyphens; anything else is dropped rather than escaped.
 *
 * @param {string|null|undefined} value
 * @returns {string|undefined} the trimmed name, or undefined when unusable
 */
function safeFontFamily(value) {
	const trimmed = typeof value === "string" ? value.trim() : "";

	if (trimmed === "" || !/^[A-Za-z0-9 -]+$/.test(trimmed)) {
		return undefined;
	}

	return trimmed;
}

/**
 * An asset URL that is safe to write into the tenant stylesheet.
 *
 * The font URLs are interpolated into `url("…")` inside the same server-rendered
 * <style> tag, so a value carrying a quote could close the url() and one carrying
 * "</style>" could close the element.
 *
 * Today they cannot: MediaStorage reduces every stored filename to
 * [A-Za-z0-9-_.] and each path segment to [A-Za-z0-9-_], so the column only ever
 * holds a machine-built path. That guarantee lives three layers away though, and
 * nothing here would notice if an import or a relaxed sanitiser ever wrote the
 * column directly. Signed S3 URLs and CDN links pass unchanged — they carry
 * ? & = % : / but never a quote, an angle bracket or whitespace.
 *
 * @param {string|null|undefined} url
 * @returns {string|null} the url, or null when it could escape its context
 */
function safeAssetUrl(url) {
	if (typeof url !== "string" || url === "") {
		return null;
	}

	return /["'<>\\\s]/.test(url) ? null : url;
}

function buildTenantBranding(user) {
	// Tenant interface config (null when user is not authenticated)
	const interfaccia = user?.banca?.interfaccia ?? null;

	// Theme colors from tenant config (injected as CSS variables)
	// const colorPrimary = interfaccia?.color_primary ?? null;
	// const colorSecondary = interfaccia?.color_secondary ?? null;

	// The API resolves these against the actual storage layout (storage symlink
	// locally, CloudFront on S3). Never rebuild them from *_path here: that
	// hardcodes the local convention and breaks as soon as media lives on S3.
	// The legacy fallback covers responses from a backend not yet exposing *_url.
	const assetUrl = (url, path) =>
		safeAssetUrl(url ?? (path ? `${constants.BACKEND_URL_CLIENT}/storage/uploads/${path}` : null));

	const logoUrl = assetUrl(interfaccia?.logo_url, interfaccia?.logo_path);

	// Font file URLs (exposed to the browser for preloading)
	const preloadUrlPrimary = assetUrl(interfaccia?.font_url_primary, interfaccia?.font_path_primary);
	const preloadUrlSecondary = assetUrl(interfaccia?.font_url_secondary, interfaccia?.font_path_secondary);
	const preloadUrlTertiary = assetUrl(interfaccia?.font_url_tertiary, interfaccia?.font_path_tertiary);

	// Resolve font MIME types for preload links
	const { type: preloadFontTypePrimary } = preloadUrlPrimary
		? getFontFormatAndType(interfaccia.font_path_primary)
		: { type: null };
	const { type: preloadFontTypeSecondary } = preloadUrlSecondary
		? getFontFormatAndType(preloadUrlSecondary)
		: { type: null };
	const { type: preloadFontTypeTertiary } = preloadUrlTertiary
		? getFontFormatAndType(interfaccia.font_path_tertiary)
		: { type: null };

	// When preset font families are provided (instead of custom files),
	// load them via Google Fonts
	const fontFamilyPrimary = safeFontFamily(interfaccia?.font_family_primary);
	const fontFamilySecondary = safeFontFamily(interfaccia?.font_family_secondary);
	const fontFamilyTertiary = safeFontFamily(interfaccia?.font_family_tertiary);

	// Collect preset font families to load via Google Fonts (skip roles with custom files)
	const families = [];

	// Add primary preset font if no custom file is provided
	if (!preloadUrlPrimary && fontFamilyPrimary) {
		families.push(fontFamilyPrimary);
	}

	// Add secondary preset font if no custom file is provided
	if (!preloadUrlSecondary && fontFamilySecondary) {
		families.push(fontFamilySecondary);
	}

	// Add tertiary preset font if no custom file is provided
	if (!preloadUrlTertiary && fontFamilyTertiary) {
		families.push(fontFamilyTertiary);
	}

	// Build Google Fonts request (single URL with multiple families)
	const fontFamilyStylesheetUrl = families.length
		? `https://fonts.googleapis.com/css2?${families
				.map((f) => `family=${encodeURIComponent(f).replace(/%20/g, "+")}`)
				.join("&")}&display=block`
		: null;

	// Font family assignments used later in CSS variables
	let fontPrimary = null;
	let fontSecondary = null;
	let fontTertiary = null;

	// Build @font-face rules for custom fonts (one per role)
	const fontFaceBlocks = [];

	// Register primary custom font family
	if (preloadUrlPrimary) {
		fontPrimary = '"Tenant Font Primary"';
		fontFaceBlocks.push(
			// prettier-ignore
			`@font-face {
	font-family: "Tenant Font Primary";
	src: url("${preloadUrlPrimary}") format("${getFontFormatAndType(interfaccia.font_path_primary).format}");
	font-display: block;
}`
		);
	}

	// Register secondary custom font family
	if (preloadUrlSecondary) {
		fontSecondary = '"Tenant Font Secondary"';
		fontFaceBlocks.push(
			// prettier-ignore
			`@font-face {
	font-family: "Tenant Font Secondary";
	src: url("${preloadUrlSecondary}") format("${getFontFormatAndType(interfaccia.font_path_secondary).format}");
	font-display: block;
}`
		);
	}

	// Register tertiary custom font family
	if (preloadUrlTertiary) {
		fontTertiary = '"Tenant Font Tertiary"';
		fontFaceBlocks.push(
			// prettier-ignore
			`@font-face {
	font-family: "Tenant Font Tertiary";
	src: url("${preloadUrlTertiary}") format("${getFontFormatAndType(interfaccia.font_path_tertiary).format}");
	font-display: block;
}`
		);
	}

	// Assign primary preset font family
	if (!preloadUrlPrimary && fontFamilyPrimary) {
		fontPrimary = `'${fontFamilyPrimary.replace(/'/g, "\\'")}'`;
	}

	// Assign secondary preset font family
	if (!preloadUrlSecondary && fontFamilySecondary) {
		fontSecondary = `'${fontFamilySecondary.replace(/'/g, "\\'")}'`;
	}

	// Assign tertiary preset font family
	if (!preloadUrlTertiary && fontFamilyTertiary) {
		fontTertiary = `'${fontFamilyTertiary.replace(/'/g, "\\'")}'`;
	}

	// CSS variables injected into :root (colors + font families)
	const cssVariables = [
		// colorPrimary ? `--color-primary:   ${colorPrimary};` : null,
		// colorSecondary ? `--color-secondary: ${colorSecondary};` : null,
		fontPrimary ? `--font-primary:    ${fontPrimary};` : null,
		fontSecondary ? `--font-secondary:  ${fontSecondary};` : null,
		fontTertiary ? `--font-tertiary:   ${fontTertiary};` : null,
	].filter(Boolean);

	// Shimmer effect while fonts are loading (text hidden until ready)
	const shimmerBlock =
		(preloadUrlPrimary || preloadUrlSecondary || preloadUrlTertiary) &&
		// prettier-ignore
		`@keyframes tenant-font-shimmer {
	0% { background-position: 200% 0; }
	100% { background-position: -200% 0; }
}

[data-tenant-font-loading] .root_layout .grid_cont > :not(.loading_component) h1,
[data-tenant-font-loading] .root_layout .grid_cont > :not(.loading_component) h2,
[data-tenant-font-loading] .root_layout .grid_cont > :not(.loading_component) h3,
[data-tenant-font-loading] .root_layout .grid_cont > :not(.loading_component) h4,
[data-tenant-font-loading] .root_layout .grid_cont > :not(.loading_component) h5,
[data-tenant-font-loading] .root_layout .grid_cont > :not(.loading_component) h6,
[data-tenant-font-loading] .root_layout .grid_cont > :not(.loading_component) p,
[data-tenant-font-loading] .root_layout .grid_cont > :not(.loading_component) span,
[data-tenant-font-loading] .root_layout .grid_cont > :not(.loading_component) a,
[data-tenant-font-loading] .root_layout .grid_cont > :not(.loading_component) button,
[data-tenant-font-loading] .root_layout .grid_cont > :not(.loading_component) li,
[data-tenant-font-loading] .root_layout .grid_cont > :not(.loading_component) td,
[data-tenant-font-loading] .root_layout .grid_cont > :not(.loading_component) th,
[data-tenant-font-loading] .root_layout .grid_cont > :not(.loading_component) label,
[data-tenant-font-loading] .root_layout .grid_cont > :not(.loading_component) .el_btn,
[data-tenant-font-loading] .root_layout .grid_cont > :not(.loading_component) .el_label,
[data-tenant-font-loading] .root_layout .grid_cont > :not(.loading_component) .el_link,
[data-tenant-font-loading] .root_layout .grid_cont > :not(.loading_component) .el_txt {
	-webkit-text-fill-color: transparent !important;
	color: transparent !important;
	background-image: linear-gradient(90deg, #e0e0e0 0%, #e8e8e8 20%, #f5f5f5 50%, #e8e8e8 80%, #e0e0e0 100%) !important;
	-webkit-background-clip: text !important;
	background-clip: text !important;
	background-size: 200% 100% !important;
	will-change: background-position !important;
	animation: tenant-font-shimmer 1.5s ease-in-out infinite !important;
}`.trim();

	// Assemble final CSS output in logical sections
	const cssParts = [];

	// Add custom font definitions
	if (fontFaceBlocks.length) {
		cssParts.push(fontFaceBlocks.join("\n\n"));
	}

	// Add CSS variables
	if (cssVariables.length) {
		cssParts.push(`:root {\n\t${cssVariables.join("\n\t")}\n}`.trim());
	}

	// Add shimmer styles (if needed)
	if (shimmerBlock) {
		cssParts.push(shimmerBlock);
	}

	// Final CSS string injected into <style> (or null if nothing to apply)
	const css = cssParts.length ? cssParts.join("\n\n") : null;

	// Final branding payload used in <head> (preloads, fonts, styles)
	return {
		preloadUrlPrimary,
		preloadUrlSecondary,
		preloadUrlTertiary,
		preloadFontTypePrimary,
		preloadFontTypeSecondary,
		preloadFontTypeTertiary,
		fontFamilyStylesheetUrl,
		logoUrl,
		css,
	};
}

// -----------------------------------------------
// Public API
// -----------------------------------------------

/**
 * Provide server-only context values to layouts and pages.
 * Reads cookies, resolves device + path info, and fetches the current user.
 *
 * @param {object} [searchParams] - Optional search params from the page/layout
 * @returns {Promise<{
 *   apiToken: string|undefined,
 *   user: object|undefined,
 *   isLoggedIn: boolean,
 *   ...deviceInfo,
 *   ...pathInfo,
 * }>}
 *
 * @example
 * const ssr = await getServer();
 * if (!ssr.isLoggedIn) redirect("/");
 */
export async function getServer(searchParams) {
	const cookiesStore = await cookies();
	const headersList = await headers();
	const isEmbeddedHeader = headersList.get("sec-fetch-dest") === "iframe";
	const apiToken = cookiesStore.get("apiToken")?.value;
	const user = await fetchUser(apiToken);

	return {
		...(await getDeviceInfo()),
		...(await getPathInfo()),
		user,
		isLoggedIn: user !== undefined,
		apiToken,
		isEmbedded: isEmbeddedHeader,
		// Tenant branding — resolved server-side, ready to inject into <head>
		branding: buildTenantBranding(user),
	};
}

/**
 * Centralised server-side fetch wrapper for Server Components (layouts, pages).
 *
 * Automatically attaches:
 *   - Authorization: Bearer <apiToken>
 *   - Content-Type / Accept: application/json
 *   - X-Locale: resolved from request Accept-Language header
 *
 * Uses BACKEND_URL_SERVER (internal Docker network) — never exposed to the browser.
 * Mirrors the CSR fetch logic in providers/Api.jsx for a consistent developer experience.
 *
 * @param {string} endpoint          - API route URI from ROUTES (e.g. ROUTES.tiraggio_index → "/api/tiraggio")
 * @param {object} [options]
 * @param {string}  [options.method="GET"]       - HTTP method
 * @param {object}  [options.body]               - Request body (auto-serialised to JSON)
 * @param {string}  [options.apiToken]           - Bearer token; if omitted and route has auth:true in ROUTES_META, read from cookies
 * @param {string}  [options.lang]               - Locale override; defaults to request header
 * @param {number}  [options.timeout=15000]      - Request timeout in ms
 * @param {boolean} [options.throwOnError=true]  - Throw ServerApiError on non-2xx; set false to return null instead
 * @returns {Promise<any>}           Parsed JSON, or null for 204 / empty responses
 * @throws  {ServerApiError}         On non-2xx when throwOnError is true
 *
 * @example
 * // Single GET — apiToken auto from cookies
 * import { ROUTES } from "@/config/api-routes";
 * import { getServer, serverFetch } from "@/lib/server";
 *
 * const { lang } = await params;
 * const data = await serverFetch(ROUTES.tiraggio_index, { lang });
 *
 * @example
 * // Multiple parallel fetches — recommended pattern for layouts
 * const opts = { lang };
 * const [delibera, previsionale, tiraggio] = await Promise.all([
 *     serverFetch(ROUTES.delibera_index,       opts),
 *     serverFetch(ROUTES.previsionale, opts),
 *     serverFetch(ROUTES.tiraggio_index,        opts),
 * ]);
 *
 * @example
 * // Graceful 401 handling — redirect without throwing
 * const user = await serverFetch(ROUTES.user_anagrafica, {
 *     lang,
 *     throwOnError: false,
 * });
 * if (!user) redirect("/");
 *
 * @example
 * // POST with body
 * const result = await serverFetch(ROUTES.contatto_store, {
 *     method: "POST",
 *     body:   { nome: "Mario", messaggio: "Ciao" },
 *     lang,
 * });
 */
export async function serverFetch(endpoint, options = {}) {
	const {
		method = "GET",
		body = undefined,
		apiToken: apiTokenOpt = undefined,
		lang = undefined,
		timeout = SERVER_FETCH_TIMEOUT,
		throwOnError = true,
	} = options;

	// apiToken: explicit option > request cookies (only when route requires auth per ROUTES_META)
	const needsAuth = endpointRequiresAuth(endpoint);
	const apiToken =
		apiTokenOpt !== undefined ? apiTokenOpt : needsAuth ? (await cookies()).get("apiToken")?.value : undefined;

	// Locale: explicit option > Accept-Language header > constant fallback
	const locale = lang ?? (await resolveLocale());

	const url = `${constants.BACKEND_URL_SERVER}${endpoint}`;

	// Build headers — identical structure to CSR Api.jsx buildHeaders()
	const requestHeaders = {
		"Accept": "application/json",
		"Content-Type": "application/json",
		"X-Locale": locale,
		...(apiToken ? { Authorization: `Bearer ${apiToken}` } : {}),
	};

	// Timeout via AbortController — mirrors CSR fetchWithTimeout()
	const controller = new AbortController();
	const timeoutId = setTimeout(() => controller.abort(), timeout);

	try {
		const response = await fetch(url, {
			method,
			headers: requestHeaders,
			body: body ? JSON.stringify(body) : undefined,
			signal: controller.signal,
		});

		// Handle non-2xx
		if (!response.ok) {
			const errorData = await response.json().catch(() => null);
			const message = errorData?.message ?? getServerErrorMessage(response.status, await buildServerErrorBase(locale));

			if (throwOnError) {
				throw new ServerApiError(message, response.status, url, errorData);
			}

			console.warn(`[serverFetch] ${response.status} ${method} ${endpoint}: ${message}`);
			return null;
		}

		// 204 No Content / 205 Reset Content — no body to parse
		if (response.status === 204 || response.status === 205) {
			return null;
		}

		const contentType = response.headers.get("content-type");
		const contentLength = response.headers.get("content-length");

		if (contentLength === "0" || !contentType?.includes("application/json")) {
			return null;
		}

		return await response.json();
	} catch (error) {
		clearTimeout(timeoutId);

		// AbortError → typed timeout error
		if (error.name === "AbortError") {
			const timeoutError = new ServerApiError(
				getServerErrorMessage(408, await buildServerErrorBase(locale)),
				408,
				url
			);
			if (throwOnError) throw timeoutError;
			console.warn(`[serverFetch] timeout ${method} ${endpoint}`);
			return null;
		}

		// Already typed — re-throw as-is
		if (error instanceof ServerApiError) throw error;

		// Unexpected network error
		const networkError = new ServerApiError(
			error.message ?? getServerErrorMessage(0, await buildServerErrorBase(locale)),
			0,
			url
		);
		if (throwOnError) throw networkError;

		console.error(`[serverFetch] network error ${method} ${endpoint}:`, error);
		return null;
	} finally {
		clearTimeout(timeoutId);
	}
}
