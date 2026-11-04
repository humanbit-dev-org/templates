<?php

/**
 * Cache TTL configuration — application-level cache lifetimes.
 *
 * All values are in seconds. Override via environment variables
 * when a specific environment requires different cache lifetimes
 * (e.g., shorter TTLs in staging to observe invalidation behaviour).
 *
 * Access via: config('cache_ttl.dashboard_chart')
 * Never use env() directly in application code — always go through config().
 *
 * Registered automatically by Laravel's config loader (no ServiceProvider needed).
 */

return [
	// =========================================================
	// Dashboard chart data
	// ---------------------------------------------------------
	// Charts aggregate tiraggi, trattenute, proiezioni over 12 months.
	// Data changes only when an import job runs or when a single
	// tiraggio status changes via controller. 15 minutes is a safe
	// balance between freshness and DB query reduction.
	// =========================================================

	"dashboard_chart" => (int) env("CACHE_TTL_DASHBOARD_CHART", 900),

	// =========================================================
	// Target / distribution charts
	// ---------------------------------------------------------
	// targetAteco and targetImk aggregate user attributes (ateco,
	// segmento_appartenenza). These change only on user imports.
	// Longer TTL than charts: 30 minutes.
	// =========================================================

	"dashboard_target" => (int) env("CACHE_TTL_DASHBOARD_TARGET", 1800),

	// =========================================================
	// Dashboard paginated lists (unfiltered views)
	// ---------------------------------------------------------
	// Same invalidation path as dashboard charts via CacheFlush.
	// =========================================================

	"dashboard_list" => (int) env("CACHE_TTL_DASHBOARD_LIST", 900),

	// =========================================================
	// Filter dropdowns
	// ---------------------------------------------------------
	// Province and filiale dropdowns. Change when users or filiali
	// are added/removed. 1 hour.
	// =========================================================

	"filter" => (int) env("CACHE_TTL_FILTER", 3600),

	// =========================================================
	// Filiali list per banca page
	// ---------------------------------------------------------
	// Paginated filiali list. Almost never changes outside of
	// explicit admin operations. 1 hour.
	// =========================================================

	"filiali" => (int) env("CACHE_TTL_FILIALI", 3600),

	// =========================================================
	// Business console — eleggibili list (unfiltered)
	// ---------------------------------------------------------
	// Changes on eleggibile patch/import, delibera and product rules.
	// Observers + CacheFlush invalidate eagerly.
	// =========================================================

	"eleggibili" => (int) env("CACHE_TTL_ELEGGIBILI", 300),

	// =========================================================
	// Business console — crediti attivi (tiraggi accepted)
	// =========================================================

	"tiraggi_accepted" => (int) env("CACHE_TTL_TIRAGGI_ACCEPTED", 300),

	// =========================================================
	// Business console — crediti richiesti (tiraggi requested)
	// =========================================================

	"tiraggi_requested" => (int) env("CACHE_TTL_TIRAGGI_REQUESTED", 300),

	// =========================================================
	// Business console — accessi list
	// ---------------------------------------------------------
	// Admin users per banca. Changes on account create/update.
	// =========================================================

	"accessi" => (int) env("CACHE_TTL_ACCESSI", 3600),

	// =========================================================
	// Tenant configuration
	// ---------------------------------------------------------
	// Interfaccia (branding: logo, colors, font) and Prodotto
	// (ATP limits, percentuali, mesi). Changes only via Backpack
	// admin on explicit bank request. 24 hours.
	// =========================================================

	"tenant_config" => (int) env("CACHE_TTL_TENANT_CONFIG", 86400),

	// =========================================================
	// SEO metadata
	// ---------------------------------------------------------
	// Page title, description, image. Changes only via Backpack
	// admin. 24 hours.
	// =========================================================

	"seo" => (int) env("CACHE_TTL_SEO", 86400),

	// =========================================================
	// Role ID lookup
	// ---------------------------------------------------------
	// Role table is immutable at runtime — no UI to change roles.
	// 24 hours; effectively permanent within a deploy lifecycle.
	// =========================================================

	"role" => (int) env("CACHE_TTL_ROLE", 86400),

	// =========================================================
	// Assistant availability per banca
	// ---------------------------------------------------------
	// Result of AssistantGate: kill switch, environment, deployment
	// tenant and bank allowlist. All four are configuration, so they
	// only change on deploy or on an explicit env update — but the
	// check runs on every chat message, so caching it keeps the gate
	// off the hot path.
	//
	// Short TTL on purpose: this value gates an emergency kill switch.
	// 60 seconds bounds how long a disabled assistant can keep
	// answering from a warm cache. Do not raise it without also
	// wiring an explicit flush into the kill switch path.
	// =========================================================

	"assistant_availability" => (int) env("CACHE_TTL_ASSISTANT_AVAILABILITY", 60),
];
