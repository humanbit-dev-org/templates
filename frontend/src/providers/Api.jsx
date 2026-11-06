"use client"; // marks module for full browser execution
//
// import { useApi, get, post, put, patch, del } from "@/providers/Api"; // File import statement

import { createContext, useCallback, useContext, useMemo, useState } from "react";

import useSWR, { mutate as globalMutate } from "swr";
import useSWRMutation from "swr/mutation";

import { useClient } from "@/providers/Client";
import { useTranslate } from "@/providers/Translate";
import { buildTranslateBase, resolveTranslateString } from "@/lib/translate/resolveTranslate";

import { ROUTES_META } from "@/config/api-routes"; // Auto-generated at build time — do not edit manually
import * as constants from "@/config/constants";
import { clearConsoleListPersistence } from "@/hooks/consoleFilterPersistence";
import { clearDashboardListPersistence } from "@/hooks/dashboardFilterPersistence";
import { clearApiTokenCookie } from "@/utils/apiTokenCookie";

// ===============================================
// ## ############################################
// ===============================================

const ApiContext = createContext(null);

/**
 * Cache profiles for different data freshness needs.
 * Select a profile based on how often the underlying data changes.
 *
 * @property {number} dedupingInterval  - Minimum ms between re-fetches of the same key
 * @property {boolean} revalidateOnFocus      - Re-fetch when the window regains focus
 * @property {boolean} revalidateOnReconnect  - Re-fetch when the browser reconnects
 */
const CACHE_PROFILES = {
	// Frequently-changing data (e.g. live charts, recent activity)
	short: {
		dedupingInterval: 5 * 60 * 1000, // 5 minutes
		revalidateOnFocus: false,
		revalidateOnReconnect: true,
	},

	// Moderately-changing data (e.g. configuration, user preferences)
	standard: {
		dedupingInterval: 15 * 60 * 1000, // 15 minutes
		revalidateOnFocus: false,
		revalidateOnReconnect: true,
	},

	// Rarely-changing data (e.g. static content, reference tables)
	long: {
		dedupingInterval: 30 * 60 * 1000, // 30 minutes
		revalidateOnFocus: false,
		revalidateOnReconnect: false,
	},

	// No caching — always re-fetch (e.g. authenticated user, session data)
	none: {
		dedupingInterval: 0,
		revalidateOnFocus: false,
		revalidateOnReconnect: false,
	},
};

/**
 * Maps ROUTES_META auth flag and URI patterns to cache profiles.
 * Resolution order:
 *   1. Authenticated endpoints → always "none" (user-specific, must never be shared)
 *   2. URI pattern match       → profile defined below
 *   3. Default                 → "none" (safest fallback for unknown endpoints)
 */
const CACHE_PATTERN_MAP = {
	"richieste-atp": "short",
	"credito-outstanding": "short",
	"storico-erogazioni": "short",
	"target-potenziale": "short",
	"dashboard-list": "short",
};

/**
 * Default timeout for CSR fetch requests (in milliseconds).
 */
const DEFAULT_TIMEOUT = 15000;

/**
 * Custom API error class for all CSR request failures.
 * Mirrors ServerApiError from lib/server.js for consistent error handling
 * across SSR and CSR contexts.
 */
export class ApiError extends Error {
	constructor(message, status, url, data = null) {
		super(message);
		this.name = "ApiError";
		this.status = status;
		this.url = url;
		this.data = data;
		this.isTimeout = status === 408;
		this.isRateLimited = status === 429;
		this.isServerError = status >= 500;
		this.isClientError = status >= 400 && status < 500;
	}
}

/**
 * Format a Retry-After duration (seconds) into a human-readable Italian string.
 *
 * @param {number} seconds
 * @returns {string}
 */
export function formatRetryTime(seconds) {
	if (!seconds || seconds < 60) return `${seconds ?? "qualche"} second${seconds === 1 ? "o" : "i"}`;
	const minutes = Math.ceil(seconds / 60);
	return `${minutes} minut${minutes === 1 ? "o" : "i"}`;
}

/**
 * Determine the cache profile for an endpoint URI.
 * Uses ROUTES_META (auto-generated) when available, then falls back to
 * string pattern matching for endpoints not in the generated manifest.
 *
 * @param {string} endpoint - API route URI (e.g. "/api/dashboard-chart/richieste-atp/imk")
 * @returns {object} A cache profile from CACHE_PROFILES
 */
/**
 * Check if an endpoint requires auth according to ROUTES_META.
 * Matches endpoint (e.g. "/api/tiraggio/modifica/42") against uri patterns.
 *
 * @param {string} endpoint - Resolved URI path
 * @returns {boolean}
 */
function endpointRequiresAuth(endpoint) {
	if (!endpoint) return false;
	const endpointPath = endpoint.split("?")[0].split("#")[0];
	const meta = Object.values(ROUTES_META).find((m) => {
		const pattern = m.uri
			.replace(/\{[^}]+\}/g, "__PARAM__")
			.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")
			.replace(/__PARAM__/g, "[^/]+");
		return new RegExp(`^${pattern}$`).test(endpointPath);
	});
	return meta?.auth === true;
}

function getCacheConfig(endpoint) {
	if (!endpoint) return CACHE_PROFILES.none;
	const endpointPath = endpoint.split("?")[0].split("#")[0];

	// Look up the endpoint in the auto-generated ROUTES_META
	const meta = Object.values(ROUTES_META).find((m) => m.uri === endpointPath);

	// Authenticated endpoints must never be cached — user-specific data
	if (meta?.auth) return CACHE_PROFILES.none;

	// Pattern-based fallback for public endpoints
	for (const [pattern, profileName] of Object.entries(CACHE_PATTERN_MAP)) {
		if (endpointPath.includes(pattern)) {
			return CACHE_PROFILES[profileName] ?? CACHE_PROFILES.none;
		}
	}

	return CACHE_PROFILES.none;
}

/**
 * Fetch with timeout using AbortController.
 * Properly cancels the underlying request when the timeout is reached.
 *
 * @param {string} url
 * @param {object} options - Standard fetch options
 * @param {number} [timeout=DEFAULT_TIMEOUT]
 * @returns {Promise<Response>}
 */
async function fetchWithTimeout(url, options = {}, timeout = DEFAULT_TIMEOUT, base = null) {
	const controller = new AbortController();
	const timeoutId = setTimeout(() => controller.abort(), timeout);

	try {
		return await fetch(url, { ...options, signal: controller.signal });
	} catch (error) {
		if (error.name === "AbortError") {
			throw new ApiError(getErrorMessage(408, base), 408, url);
		}
		throw error;
	} finally {
		clearTimeout(timeoutId);
	}
}

/**
 * Parse and validate the API response.
 * Throws ApiError on non-2xx status codes.
 *
 * @param {Response} response
 * @param {string} url
 * @returns {Promise<any>} Parsed JSON or null for empty responses
 */
async function handleApiResponse(response, url, base = null) {
	if (!response.ok) {
		const errorData = await response.json().catch(() => null);

		// 429 Too Many Requests — attach Retry-After seconds for UX messaging
		if (response.status === 429) {
			const raw = response.headers.get("Retry-After");
			const retryAfter = raw ? parseInt(raw, 10) : null;
			throw new ApiError(errorData?.message ?? getErrorMessage(429, base), 429, url, {
				...errorData,
				retryAfter,
			});
		}

		const message = errorData?.message ?? getErrorMessage(response.status, base);
		throw new ApiError(message, response.status, url, errorData);
	}

	// 204 No Content / 205 Reset Content — nothing to parse
	if (response.status === 204 || response.status === 205) {
		return null;
	}

	const contentType = response.headers.get("content-type");
	const contentLength = response.headers.get("content-length");

	if (contentLength === "0" || !contentType?.includes("application/json")) {
		return null;
	}

	return response.json();
}

/**
 * Map HTTP status codes to user-friendly Italian error messages.
 * Mirrors getServerErrorMessage() in lib/server.js for consistency.
 *
 * @param {number} status
 * @returns {string}
 */
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

function getErrorMessage(status, base = null) {
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
		429: "Troppi tentativi. Riprova più tardi.",
		500: "Errore del server",
		502: "Errore del server",
		503: "Servizio non disponibile",
		504: "Timeout del server",
	};
	return messages[status] ?? "Errore nella richiesta";
}

/**
 * Invalidate the SWR cache for a given endpoint after a successful mutation.
 *
 * @param {string} endpoint - API route URI
 * @returns {Promise<void>}
 */
function invalidateCache(endpoint) {
	return globalMutate((key) => typeof key === "string" && key.startsWith(constants.BACKEND_URL_CLIENT), undefined, {
		revalidate: true,
	});
}

// -----------------------------------------------
// Provider
// -----------------------------------------------

export function ApiProvider({ children, lang }) {
	const { apiToken, user } = useClient();
	const { dict } = useTranslate() ?? {};
	const [pendingRequests, setPendingRequests] = useState(0);

	// Translate base scoped to `all` (no page context at provider level)
	const base = useMemo(
		() => buildTranslateBase(null, { dict, lang, bancaAbi: user?.banca?.banca_abi }),
		[dict, lang, user]
	);

	/**
	 * Build the standard request headers for all CSR API calls.
	 * Adds Authorization only when endpoint requires auth (ROUTES_META.auth === true).
	 * Mirrors serverFetch() header structure in lib/server.js.
	 *
	 * @param {string} endpoint - API route URI (e.g. "/api/tiraggio")
	 */
	const buildHeaders = useCallback(
		(endpoint, { omitContentType = false } = {}) => {
			const addAuth = endpointRequiresAuth(endpoint) && apiToken;
			const headers = {
				"Authorization": addAuth ? `Bearer ${apiToken}` : "",
				"Accept": "application/json",
				"X-Locale": lang,
			};

			if (!omitContentType) {
				headers["Content-Type"] = "application/json";
			}

			return headers;
		},
		[apiToken, lang]
	);

	/**
	 * Wrap a fetch promise to track in-flight request count.
	 * Exposed via context as isLoading / pendingRequests.
	 */
	const trackRequest = useCallback((promise) => {
		setPendingRequests((prev) => prev + 1);
		return promise.finally(() => setPendingRequests((prev) => prev - 1));
	}, []);

	/**
	 * Handle 401 Unauthorized responses by clearing the token cookie
	 * and redirecting the user to the home page.
	 */
	const handleUnauthorized = useCallback(() => {
		clearDashboardListPersistence();
		clearConsoleListPersistence();
		clearApiTokenCookie();
		window.location.href = window.location.pathname;
	}, []);

	/**
	 * SWR fetcher — used exclusively by the get() hook.
	 * Handles 401 → redirect, other errors → ApiError.
	 */
	const fetcher = useCallback(
		async (url) => {
			const endpointWithQuery = url.startsWith(constants.BACKEND_URL_CLIENT)
				? url.slice(constants.BACKEND_URL_CLIENT.length)
				: new URL(url).pathname;
			const endpoint = endpointWithQuery.split("?")[0].split("#")[0];
			const response = await trackRequest(
				fetchWithTimeout(
					url,
					{
						method: "GET",
						headers: buildHeaders(endpoint),
					},
					DEFAULT_TIMEOUT,
					base
				)
			);

			if (response.status === 401) {
				handleUnauthorized();
				throw new ApiError(getErrorMessage(401, base), 401, url);
			}

			return handleApiResponse(response, url, base);
		},
		[buildHeaders, trackRequest, handleUnauthorized, base]
	);

	/**
	 * Internal mutation handler — used by post(), put(), patch(), del().
	 *
	 * @param {string} endpoint  - API route URI
	 * @param {object|null} data - Request body
	 * @param {string} method    - HTTP method
	 * @returns {Promise<any>}
	 */
	const mutate = useCallback(
		async (endpoint, data = null, method = "POST", options = {}) => {
			const url = `${constants.BACKEND_URL_CLIENT}${endpoint}`;
			const isFormData = typeof FormData !== "undefined" && data instanceof FormData;
			const response = await trackRequest(
				fetchWithTimeout(
					url,
					{
						method,
						headers: buildHeaders(endpoint, { omitContentType: isFormData }),
						body: isFormData ? data : data ? JSON.stringify(data) : undefined,
					},
					options.timeout,
					base
				)
			);

			if (response.status === 401) {
				handleUnauthorized();
				throw new ApiError(getErrorMessage(401, base), 401, url);
			}

			const result = await handleApiResponse(response, url, base);
			const shouldInvalidateCache =
				!options.skipCacheInvalidation && !endpoint.endsWith("/export") && !endpoint.endsWith("/export-xlsx");

			if (shouldInvalidateCache) {
				await invalidateCache(endpoint);
			}

			return result;
		},
		[buildHeaders, trackRequest, handleUnauthorized, base]
	);

	/**
	 * Open a file from an authenticated endpoint in a new browser tab.
	 *
	 * A plain <a href target="_blank"> cannot send the Bearer token, so the
	 * backend would try to redirect the guest to the non-existent 'login'
	 * route. This fetches the file with the standard auth headers and shows
	 * it inline (browser PDF viewer) via a temporary object URL.
	 *
	 * The tab is opened synchronously — before any await — so popup blockers
	 * treat it as user-initiated; it is closed again if the fetch fails.
	 *
	 * @param {string} endpoint - API route URI (e.g. "/api/documenti/1")
	 * @returns {Promise<void>}
	 */
	const download = useCallback(
		async (endpoint) => {
			const newTab = window.open("", "_blank");

			try {
				const url = `${constants.BACKEND_URL_CLIENT}${endpoint}`;
				const response = await trackRequest(
					fetchWithTimeout(
						url,
						{
							method: "GET",
							headers: buildHeaders(endpoint, { omitContentType: true }),
						},
						60000,
						base
					)
				);

				if (response.status === 401) {
					handleUnauthorized();
					throw new ApiError(getErrorMessage(401, base), 401, url);
				}

				if (!response.ok) {
					// Delegate error parsing/throwing to the shared handler
					await handleApiResponse(response, url, base);
					return;
				}

				const blob = await response.blob();
				const objectUrl = window.URL.createObjectURL(blob);

				if (newTab) {
					newTab.location = objectUrl;
				} else {
					// Popup blocked: fall back to navigating the current tab
					window.location.assign(objectUrl);
				}

				// Revoke later: the new tab needs the object URL to load the PDF
				setTimeout(() => window.URL.revokeObjectURL(objectUrl), 60000);
			} catch (error) {
				newTab?.close();
				throw error;
			}
		},
		[buildHeaders, trackRequest, handleUnauthorized, base]
	);

	// Convenience wrappers — mirror REST semantics
	const post = useCallback((endpoint, data, options) => mutate(endpoint, data, "POST", options), [mutate]);
	const put = useCallback((endpoint, data, options) => mutate(endpoint, data, "PUT", options), [mutate]);
	const patch = useCallback((endpoint, data, options) => mutate(endpoint, data, "PATCH", options), [mutate]);
	const del = useCallback((endpoint, options) => mutate(endpoint, null, "DELETE", options), [mutate]);

	const value = {
		fetcher,
		baseUrl: constants.BACKEND_URL_CLIENT,
		getCacheConfig,
		mutate,
		post,
		put,
		patch,
		del,
		download,
		isLoading: pendingRequests > 0,
		pendingRequests,
	};

	return <ApiContext.Provider value={value}>{children}</ApiContext.Provider>;
}

// -----------------------------------------------
// Context hook
// -----------------------------------------------

/**
 * Access the API context inside any Client Component wrapped by ApiProvider.
 *
 * @throws {Error} If used outside ApiProvider
 */
export function useApi() {
	const context = useContext(ApiContext);
	if (!context) {
		throw new Error("useApi must be used within ApiProvider");
	}
	return context;
}

// -----------------------------------------------
// Public data-fetching hooks
// -----------------------------------------------

/**
 * Hook for GET requests with intelligent caching.
 * Uses SWR under the hood — data is cached per URL key.
 *
 * Pass a URI from ROUTES (auto-generated) — never hardcode endpoint strings.
 *
 * @param {string|null} endpoint    - API route URI (e.g. ROUTES.dashboard_chart_richieste_atp_imk)
 *                                    Pass null to disable the request (conditional fetching).
 * @param {object}     [options]
 * @param {boolean}    [options.enabled=true]       - Disable the request when false
 * @param {string}     [options.cacheProfile]       - Override cache profile: "short" | "standard" | "long" | "none"
 * @returns {{ data: any, error: ApiError, isLoading: boolean, mutate: Function }}
 *
 * @example
 * // Auto-cached GET using generated ROUTES
 * import { ROUTES } from "@/config/api-routes";
 * import { get } from "@/providers/Api";
 *
 * const { data, error, isLoading } = get(ROUTES.dashboard_chart_richieste_atp_imk);
 *
 * @example
 * // Conditional fetch — only runs when userId is set
 * const { data } = get(userId ? ROUTES.user_anagrafica : null);
 *
 * @example
 * // Override cache profile
 * const { data } = get(ROUTES.prodotti_index, { cacheProfile: "long" });
 */
export function get(endpoint, options = {}) {
	const api = useApi();
	const { enabled = true, cacheProfile } = options;

	const cacheConfig = cacheProfile ? (CACHE_PROFILES[cacheProfile] ?? CACHE_PROFILES.none) : api.getCacheConfig(endpoint);

	const swrConfig = {
		...cacheConfig,
		// Only retry on 5xx server errors — not on client errors (4xx)
		shouldRetryOnError: (error) => error instanceof ApiError && error.isServerError,
		onError: (error) => {
			if (error instanceof ApiError && error.status === 401) {
				console.warn("[API] Unauthorized GET — token may have expired");
			}
		},
	};

	const url = enabled && endpoint ? `${api.baseUrl}${endpoint}` : null;

	return useSWR(url, api.fetcher, swrConfig);
}

/**
 * Hook for POST requests with SWR mutation state management.
 * Cache for the endpoint is automatically invalidated after a successful call.
 *
 * @param {string} endpoint - API route URI (e.g. ROUTES.contatto_store)
 * @returns {{ trigger: Function, isMutating: boolean, error: ApiError, data: any, reset: Function }}
 *
 * @example
 * import { ROUTES } from "@/config/api-routes";
 * import { post } from "@/providers/Api";
 *
 * const { trigger, isMutating, error } = post(ROUTES.contatto_store);
 * await trigger({ nome: "Mario", messaggio: "Ciao" });
 */
export function post(endpoint) {
	const { post } = useApi();
	return useSWRMutation(endpoint, async (_, { arg }) => post(endpoint, arg));
}

/**
 * Hook for PUT requests with SWR mutation state management.
 * Use for full resource replacements (sends the entire resource representation).
 *
 * @param {string} endpoint - API route URI
 * @returns {{ trigger: Function, isMutating: boolean, error: ApiError, data: any, reset: Function }}
 *
 * @example
 * import { ROUTES, route } from "@/config/api-routes";
 * import { put } from "@/providers/Api";
 *
 * const { trigger } = put(route(ROUTES.user_update, { user_id: 42 }));
 * await trigger({ nome: "Mario", email: "mario@example.com" });
 */
export function put(endpoint) {
	const { put } = useApi();
	return useSWRMutation(endpoint, async (_, { arg }) => put(endpoint, arg));
}

/**
 * Hook for PATCH requests with SWR mutation state management.
 * Use for partial resource updates (sends only changed fields).
 *
 * @param {string} endpoint - API route URI
 * @returns {{ trigger: Function, isMutating: boolean, error: ApiError, data: any, reset: Function }}
 *
 * @example
 * import { ROUTES, route } from "@/config/api-routes";
 * import { patch } from "@/providers/Api";
 *
 * // With dynamic route parameter resolved via route() helper
 * const { trigger, isMutating } = patch(
 *     route(ROUTES.tiraggio_modifica, { tiraggio_id: tiraggioResponseJson.tiraggio_id })
 * );
 * await trigger({ importo_richiesto: 5000, percentuale: 20, mesi: 6 });
 */
export function patch(endpoint) {
	const { patch } = useApi();
	return useSWRMutation(endpoint, async (_, { arg }) => patch(endpoint, arg));
}

/**
 * Hook for DELETE requests with SWR mutation state management.
 * Cache for the endpoint is automatically invalidated after a successful call.
 *
 * @param {string} endpoint - API route URI
 * @returns {{ trigger: Function, isMutating: boolean, error: ApiError, data: any, reset: Function }}
 *
 * @example
 * import { ROUTES, route } from "@/config/api-routes";
 * import { del } from "@/providers/Api";
 *
 * const { trigger, isMutating } = del(
 *     route(ROUTES.documento_destroy, { documento_id: id })
 * );
 * await trigger();
 */
export function del(endpoint) {
	const { del } = useApi();
	return useSWRMutation(endpoint, async () => del(endpoint));
}
