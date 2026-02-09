"use client";

import { createContext, useContext, useCallback, useState } from "react";
import { useClient } from "@/providers/Client";
import useSWR, { mutate as globalMutate } from "swr";
import useSWRMutation from "swr/mutation";
import * as constants from "@/config/constants";

const ApiContext = createContext(null);

/**
 * Cache profiles for different data freshness needs.
 * @property {number} dedupingInterval - Refetch interval in ms
 * @property {boolean} revalidateOnFocus - Refetch on window focus
 * @property {boolean} revalidateOnReconnect - Refetch on reconnect
 */
const CACHE_PROFILES = {
	short: {
		dedupingInterval: 5 * 60 * 1000,
		revalidateOnFocus: false,
		revalidateOnReconnect: true,
	},

	standard: {
		dedupingInterval: 15 * 60 * 1000,
		revalidateOnFocus: false,
		revalidateOnReconnect: true,
	},

	long: {
		dedupingInterval: 30 * 60 * 1000,
		revalidateOnFocus: false,
		revalidateOnReconnect: false,
	},

	none: {
		dedupingInterval: 0,
		revalidateOnFocus: false,
		revalidateOnReconnect: false,
	},
};

/**
 * Maps endpoint patterns to cache profiles.
 * Pattern matching uses string.includes().
 */
const CACHE_CATEGORIES = {
	"richieste-atp": "short",
	"credito-outstanding": "short",
	"storico-erogazioni": "short",
	"target-potenziale": "short",
	"user": "none",
};

/**
 * Default timeout for requests (15 seconds)
 */
const DEFAULT_TIMEOUT = 15000;

/**
 * Custom API Error class for all request failures
 */
export class ApiError extends Error {
	constructor(message, status, url, data = null) {
		super(message);
		this.name = "ApiError";
		this.status = status;
		this.url = url;
		this.data = data;
		this.isTimeout = status === 408;
		this.isServerError = status >= 500;
		this.isClientError = status >= 400 && status < 500;
	}
}

/**
 * Centralized API endpoint registry.
 * Organize endpoints by feature/module for maintainability.
 *
 * @example
 * import { API_ENDPOINTS } from "@/providers/API";
 * const { data } = get(API_ENDPOINTS.module.endpoint);
 */
export const API_ENDPOINTS = {
	dashboard: {
		imk: "/api/dashboard-chart/richieste-atp/imk",
		ateco: "/api/dashboard-chart/richieste-atp/ateco",
		importo: "/api/dashboard-chart/richieste-atp/importo",
		creditoForecastBaseline: "/api/dashboard-chart/credito-outstanding/credito-forecast-baseline",
		erogazioniCreditoOutstanding: "/api/dashboard-chart/credito-outstanding/erogazioni-credito-outstanding",
		erogazioniForecastBaseline: "/api/dashboard-chart/credito-outstanding/erogazioni-forecast-baseline",
		esitoRimborso: "/api/dashboard-chart/storico-erogazioni/esito-rimborso",
		timeToCash: "/api/dashboard-chart/storico-erogazioni/time-to-cash",
		incidenzaRichieste: "/api/dashboard-chart/storico-erogazioni/incidenza-richieste",
		targetAteco: "/api/dashboard-chart/target-potenziale/ateco",
		targetImk: "/api/dashboard-chart/target-potenziale/imk",
	},

	user: {
		login: "/api/auth-dashboard", // POST request
		logout: "/api/logout", // POST request
	},
};

/**
 * Determines cache profile for an endpoint by matching patterns
 * Returns CACHE_PROFILES.none if no match is found
 */
function getCacheConfig(endpoint) {
	if (!endpoint) {
		return CACHE_PROFILES.none;
	}

	for (const [category, profileName] of Object.entries(CACHE_CATEGORIES)) {
		if (endpoint.includes(category)) {
			return CACHE_PROFILES[profileName] || CACHE_PROFILES.none;
		}
	}

	return CACHE_PROFILES.none;
}

/**
 * Fetch with timeout using AbortController
 * Properly cancels the request when timeout is reached
 */
async function fetchWithTimeout(url, options = {}, timeout = DEFAULT_TIMEOUT) {
	const controller = new AbortController();
	const timeoutId = setTimeout(() => controller.abort(), timeout);

	try {
		return await fetch(url, { ...options, signal: controller.signal });
	} catch (error) {
		if (error.name === "AbortError") {
			throw new ApiError("Request timeout", 408, url);
		}
		throw error;
	} finally {
		clearTimeout(timeoutId);
	}
}

/**
 * Handle API response and errors
 */
async function handleApiResponse(response, url) {
	if (!response.ok) {
		const errorData = await response.json().catch(() => null);
		const message = errorData?.message || getErrorMessage(response.status);
		throw new ApiError(message, response.status, url, errorData);
	}

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
 * Get user-friendly error message based on status code
 */
function getErrorMessage(status) {
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
	return messages[status] || "Errore nella richiesta";
}

/**
 * Invalidate cache after mutation
 */
function invalidateCache(endpoint) {
	return globalMutate(`${constants.BACKEND_URL_CLIENT}${endpoint}`);
}

export function ApiProvider({ children, lang }) {
	const { apiToken } = useClient();
	const [pendingRequests, setPendingRequests] = useState(0);

	const buildHeaders = useCallback(
		() => ({
			"Authorization": apiToken ? `Bearer ${apiToken}` : "",
			"Content-Type": "application/json",
			"Accept": "application/json",
			"X-Locale": lang,
		}),
		[apiToken, lang]
	);

	const trackRequest = useCallback((promise) => {
		setPendingRequests((prev) => prev + 1);
		return promise.finally(() => setPendingRequests((prev) => prev - 1));
	}, []);

	const fetcher = useCallback(
		async (url) => {
			const response = await trackRequest(
				fetchWithTimeout(url, {
					method: "GET",
					headers: buildHeaders(),
				})
			);
			return handleApiResponse(response, url);
		},
		[buildHeaders, trackRequest]
	);

	const mutate = useCallback(
		async (endpoint, data = null, method = "POST") => {
			const url = `${constants.BACKEND_URL_CLIENT}${endpoint}`;
			const response = await trackRequest(
				fetchWithTimeout(url, {
					method,
					headers: buildHeaders(),
					body: data ? JSON.stringify(data) : undefined,
				})
			);

			const result = await handleApiResponse(response, url);
			await invalidateCache(endpoint);
			return result;
		},
		[buildHeaders, trackRequest]
	);

	const post = useCallback((endpoint, data) => mutate(endpoint, data, "POST"), [mutate]);

	const put = useCallback((endpoint, data) => mutate(endpoint, data, "PUT"), [mutate]);

	const patch = useCallback((endpoint, data) => mutate(endpoint, data, "PATCH"), [mutate]);

	const del = useCallback((endpoint) => mutate(endpoint, null, "DELETE"), [mutate]);

	const value = {
		fetcher,
		baseUrl: constants.BACKEND_URL_CLIENT,
		getCacheConfig,
		mutate,
		post,
		put,
		patch,
		del,
		endpoints: API_ENDPOINTS,
		isLoading: pendingRequests > 0,
		pendingRequests,
	};

	return <ApiContext.Provider value={value}>{children}</ApiContext.Provider>;
}

/**
 * Hook to access API context.
 * @throws {Error} If used outside ApiProvider
 */
export function useApi() {
	const context = useContext(ApiContext);
	if (!context) {
		throw new Error("useApi must be used within ApiProvider");
	}
	return context;
}

/**
 * Hook for GET requests with intelligent caching.
 *
 * @param {string} endpoint - API endpoint path
 * @param {object} [options] - Configuration options
 * @param {boolean} [options.enabled=true] - Enable/disable request
 * @param {string} [options.cacheProfile] - Override cache profile ('short', 'standard', 'long', 'none')
 * @returns {{ data: any, error: Error, isLoading: boolean, mutate: Function }}
 *
 * @example
 * Basic usage with auto-caching
 * const { data, error, isLoading } = get(API_ENDPOINTS.dashboard.imk);
 *
 * @example
 * With custom cache profile
 * const { data } = get('/api/user/settings', { cacheProfile: 'long' });
 *
 * @example
 * Conditional fetching (disabled when id is null)
 * const { data } = get(id ? `/api/resource/${id}` : null);
 */
export function get(endpoint, options = {}) {
	const api = useApi();
	const { enabled = true, cacheProfile } = options;

	const cacheConfig = cacheProfile ? CACHE_PROFILES[cacheProfile] : api.getCacheConfig(endpoint);

	const swrConfig = {
		...cacheConfig,
		shouldRetryOnError: (error) => error instanceof ApiError && error.isServerError,
		onError: (error) => {
			if (error instanceof ApiError && error.status === 401) {
				console.warn("[API] Unauthorized request - consider implementing token refresh");
			}
		},
	};

	const url = enabled && endpoint ? `${api.baseUrl}${endpoint}` : null;

	return useSWR(url, api.fetcher, swrConfig);
}

/**
 * Hook for POST requests with state management.
 * Automatically invalidates cache for the endpoint after successful mutation.
 *
 * @param {string} endpoint - API endpoint path
 * @returns {{ trigger: Function, isMutating: boolean, error: Error, data: any, reset: Function }}
 *
 * @example
 * Basic usage
 * const { trigger, isMutating, error } = post('/api/richieste');
 * await trigger({ importo: 5000, ateco: 'A01' });
 *
 * @example
 * With error handling
 * const { trigger, isMutating, error } = post(API_ENDPOINTS.user.settings);
 * try {
 *   const result = await trigger({ theme: 'dark' });
 *   console.log('Success:', result);
 * } catch (err) {
 *   console.error('Failed:', err.message);
 * }
 */
export function post(endpoint) {
	const { post } = useApi();
	return useSWRMutation(endpoint, async (_, { arg }) => post(endpoint, arg));
}

/**
 * Hook for PUT requests with state management.
 * Automatically invalidates cache for the endpoint after successful mutation.
 * Use for full resource updates (replaces entire resource).
 *
 * @param {string} endpoint - API endpoint path
 * @returns {{ trigger: Function, isMutating: boolean, error: Error, data: any, reset: Function }}
 *
 * @example
 * Update entire user profile
 * const { trigger, isMutating } = put('/api/user/profile');
 * await trigger({
 *   name: 'Mario Rossi',
 *   email: 'mario@example.com',
 *   phone: '+39 123 456 7890'
 * });
 *
 * @example
 * Update resource with ID
 * const { trigger } = put(`/api/richieste/${id}`);
 * await trigger({ status: 'approved', amount: 10000 });
 */
export function put(endpoint) {
	const { put } = useApi();
	return useSWRMutation(endpoint, async (_, { arg }) => put(endpoint, arg));
}

/**
 * Hook for PATCH requests with state management.
 * Automatically invalidates cache for the endpoint after successful mutation.
 * Use for partial resource updates (modifies only specified fields).
 *
 * @param {string} endpoint - API endpoint path
 * @returns {{ trigger: Function, isMutating: boolean, error: Error, data: any, reset: Function }}
 *
 * @example
 * Update only specific fields
 * const { trigger, isMutating } = patch('/api/user/preferences');
 * await trigger({ theme: 'dark', language: 'it' });
 *
 * @example
 * Toggle a single field
 * const { trigger } = patch(`/api/richieste/${id}`);
 * await trigger({ status: 'in_review' });
 */
export function patch(endpoint) {
	const { patch } = useApi();
	return useSWRMutation(endpoint, async (_, { arg }) => patch(endpoint, arg));
}

/**
 * Hook for DELETE requests with state management.
 * Automatically invalidates cache for the endpoint after successful mutation.
 *
 * @param {string} endpoint - API endpoint path
 * @returns {{ trigger: Function, isMutating: boolean, error: Error, data: any, reset: Function }}
 *
 * @example
 * Delete a resource by ID
 * const { trigger, isMutating } = del(`/api/richieste/${id}`);
 * await trigger(); // No arguments needed for DELETE
 *
 * @example
 * With confirmation dialog
 * const { trigger, isMutating } = del(`/api/user/account`);
 * const handleDelete = async () => {
 *   if (confirm('Sei sicuro?')) {
 *     await trigger();
 *   }
 * };
 */
export function del(endpoint) {
	const { del } = useApi();
	return useSWRMutation(endpoint, async () => del(endpoint));
}
