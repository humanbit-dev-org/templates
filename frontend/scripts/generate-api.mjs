// ==========================================================
// generate-api-routes.mjs
// ----------------------------------------------------------
// Reads api.php (from /tmp in Docker, or monorepo path in dev),
// parses Laravel API route declarations including nested
// Route::prefix() groups, and writes src/config/api-routes.js.
//
// Named routes (->name('...')) are used as the JS key when present.
// Falls back to URI-derived key when name is missing.
//
// Never served to the browser — consumed only at build time.
// ==========================================================

import { readFileSync, writeFileSync, existsSync, unlinkSync } from "fs";
import { resolve, dirname } from "path";
import { fileURLToPath } from "url";

const __dirname = dirname(fileURLToPath(import.meta.url));

// In Docker: api.php is copied to /tmp by COPY --from=core-prod in the Dockerfile.
// In dev: api.php lives in the monorepo at the relative backend path.
const PHP_ROUTES_PATH_DOCKER = "/tmp/api.php";
const PHP_ROUTES_PATH_DEV = resolve(__dirname, "/var/www/html/codebase/backend/routes/api.php");
const OUTPUT_PATH = resolve(__dirname, "/var/www/html/codebase/frontend/src/config/api-routes.js");

// Resolve source path: prefer /tmp (Docker), fall back to monorepo (dev)
const PHP_ROUTES_PATH = existsSync(PHP_ROUTES_PATH_DOCKER) ? PHP_ROUTES_PATH_DOCKER : PHP_ROUTES_PATH_DEV;

if (!existsSync(PHP_ROUTES_PATH)) {
	console.error(
		"[api-routes] ERROR: api.php not found.\n" +
			`Checked: ${PHP_ROUTES_PATH_DOCKER}\n` +
			`Checked: ${PHP_ROUTES_PATH_DEV}\n` +
			"In Docker: ensure COPY --from=core-prod of routes/api.php is present.\n" +
			"In dev: run from the monorepo root so the relative path resolves correctly."
	);
	process.exit(1);
}

// Commented-out routes are not routes: drop full-line // comments and /* */ blocks.
const content = readFileSync(PHP_ROUTES_PATH, "utf-8")
	.replace(/\/\*[\s\S]*?\*\//g, "")
	.replace(/^\s*\/\/.*$/gm, "");

// ----------------------------------------------------------
// Parse the file character by character, tracking:
// - Current prefix stack (accumulated from Route::prefix() groups)
// - Current auth:sanctum state (from Route::middleware())
// - Brace depth to know when groups open/close
// ----------------------------------------------------------
const routes = [];

/**
 * Token types we care about in the file:
 * - ROUTE_METHOD: Route::get/post/put/patch/delete('uri', ...)
 * - ROUTE_PREFIX: Route::prefix('segment')->group(...)
 * - MIDDLEWARE_AUTH: middleware containing 'auth:sanctum'
 * - BRACE_OPEN: { — opens a group scope
 * - BRACE_CLOSE: } — closes a group scope
 */

// Match Route::METHOD('uri', ...) — captures method and URI
const ROUTE_RE = /Route::(get|post|put|patch|delete)\s*\(\s*['"]([^'"]*)['"]]/g;
// Match Route::prefix('segment') — captures prefix segment
const PREFIX_RE = /Route::prefix\s*\(\s*['"]([^'"]*)['"]\s*\)/g;
// Match Route::middleware([...]) or ->middleware([...]) containing auth:sanctum
// Covers both static group openers (Route::middleware) and chained calls (->middleware).
const MIDDLEWARE_RE = /(?:Route::|->)middleware\s*\(\s*(?:\[([^\]]*)\]|['"]([^'"]*)['"]\s*)\)/g;
// Match ->name('route.name')
const NAME_RE = /->name\s*\(\s*['"]([^'"]+)['"]\s*\)/;

// ----------------------------------------------------------
// Build a positional index of all tokens so we can
// reconstruct the prefix/middleware context for each route.
// ----------------------------------------------------------
const tokens = [];

// Collect all Route::prefix(...) positions
let m;
PREFIX_RE.lastIndex = 0;
while ((m = PREFIX_RE.exec(content)) !== null) {
	tokens.push({ type: "prefix", index: m.index, end: m.index + m[0].length, value: m[1] });
}

// Collect all Route::middleware(...) containing auth:sanctum
MIDDLEWARE_RE.lastIndex = 0;
while ((m = MIDDLEWARE_RE.exec(content)) !== null) {
	const inner = m[1] ?? m[2] ?? "";
	if (inner.includes("auth:sanctum")) {
		tokens.push({ type: "auth", index: m.index, end: m.index + m[0].length });
	}
}

// Collect all brace positions (to track scope depth)
for (let i = 0; i < content.length; i++) {
	if (content[i] === "{") tokens.push({ type: "open", index: i, end: i + 1 });
	if (content[i] === "}") tokens.push({ type: "close", index: i, end: i + 1 });
}

// Sort all tokens by position in the file
tokens.sort((a, b) => a.index - b.index);

// ----------------------------------------------------------
// Walk tokens to build a scope tree:
// Each scope carries the prefix segments and auth state
// accumulated from its ancestors.
// ----------------------------------------------------------

/**
 * Scope stack entry:
 * { prefixes: string[], auth: bool }
 */
const scopeStack = [{ prefixes: [], auth: false }];

// Pending state: a prefix or middleware token seen before its group opens
let pendingPrefix = null;
let pendingAuth = false;

for (const token of tokens) {
	if (token.type === "prefix") {
		pendingPrefix = token.value;
	} else if (token.type === "auth") {
		pendingAuth = true;
	} else if (token.type === "open") {
		// A new scope opens: inherit from current top and apply pending state
		const parent = scopeStack[scopeStack.length - 1];
		const newPrefixes = pendingPrefix !== null ? [...parent.prefixes, pendingPrefix] : [...parent.prefixes];
		const newAuth = pendingAuth || parent.auth;
		scopeStack.push({ prefixes: newPrefixes, auth: newAuth });
		pendingPrefix = null;
		pendingAuth = false;
	} else if (token.type === "close") {
		if (scopeStack.length > 1) scopeStack.pop();
	}
}

// ----------------------------------------------------------
// Now parse all Route::METHOD(...) declarations.
// For each one, reconstruct scope by replaying the token walk
// up to that position.
// ----------------------------------------------------------

/**
 * For a given position in the file, compute the active scope
 * (prefix stack + auth state) by replaying tokens up to that point.
 *
 * @param {number} position
 * @returns {{ prefixes: string[], auth: boolean }}
 */
function getScopeAt(position) {
	const stack = [{ prefixes: [], auth: false }];
	let pPrefix = null;
	let pAuth = false;

	for (const token of tokens) {
		if (token.index >= position) break;

		if (token.type === "prefix") {
			pPrefix = token.value;
		} else if (token.type === "auth") {
			pAuth = true;
		} else if (token.type === "open") {
			const parent = stack[stack.length - 1];
			stack.push({
				prefixes: pPrefix !== null ? [...parent.prefixes, pPrefix] : [...parent.prefixes],
				auth: pAuth || parent.auth,
			});
			pPrefix = null;
			pAuth = false;
		} else if (token.type === "close") {
			if (stack.length > 1) stack.pop();
		}
	}

	return stack[stack.length - 1];
}

// Parse all Route::METHOD declarations
// Updated regex: removed erroneous ] at end of character class
const ROUTE_RE_FIXED = /Route::(get|post|put|patch|delete)\s*\(\s*['"]([^'"]*)['"]/g;
ROUTE_RE_FIXED.lastIndex = 0;
while ((m = ROUTE_RE_FIXED.exec(content)) !== null) {
	const method = m[1].toUpperCase();
	const rawUri = m[2];
	const matchEnd = m.index + m[0].length;

	// Get active scope at this position
	const scope = getScopeAt(m.index);

	// Build full URI: /api + accumulated prefixes + route segment
	const prefixPath = scope.prefixes
		.map((p) => p.replace(/^\/|\/$/g, "")) // trim slashes
		.filter(Boolean)
		.join("/");

	const routeSegment = rawUri.replace(/^\//, "");
	const uri = "/api/" + [prefixPath, routeSegment].filter(Boolean).join("/");

	// Look for ->name('...') in the 300 chars after the declaration
	const lookahead = content.substring(matchEnd, matchEnd + 300);
	const nameMatch = NAME_RE.exec(lookahead);
	const name = nameMatch ? nameMatch[1] : null;

	// Derive JS key: prefer named route, fall back to URI
	const key = name ? nameToKey(name) : uriToKey(uri);

	routes.push({ key, method, uri, auth: scope.auth, name });
}

if (routes.length === 0) {
	console.error("[api-routes] ERROR: No routes parsed from api.php. Check the file format.");
	process.exit(1);
}

// ----------------------------------------------------------
// Deduplication safety net.
// Routes with the same key must have the same URI and method —
// otherwise it means a naming collision in api.php that needs
// to be fixed at the source.
// First occurrence wins; duplicates are logged and skipped.
// ----------------------------------------------------------
const seenKeys = new Map(); // key → first route entry
const dedupedRoutes = [];
let duplicateCount = 0;

for (const route of routes) {
	if (!seenKeys.has(route.key)) {
		// First time we see this key — keep it
		seenKeys.set(route.key, route);
		dedupedRoutes.push(route);
	} else {
		// Duplicate key detected
		duplicateCount++;
		const existing = seenKeys.get(route.key);

		if (existing.uri !== route.uri || existing.method !== route.method) {
			// Different URI or method under the same key — this is a real collision
			// in api.php that must be resolved by renaming one of the routes.
			console.warn(
				`[api-routes] COLLISION on key "${route.key}":\n` +
					`  kept:    ${existing.method} ${existing.uri} (name: ${existing.name ?? "n/a"})\n` +
					`  skipped: ${route.method} ${route.uri} (name: ${route.name ?? "n/a"})\n` +
					`  Fix: give these routes distinct ->name() values in api.php.`
			);
		} else {
			// Exact duplicate (same key, URI, method) — silently skip
			console.warn(`[api-routes] duplicate skipped: "${route.key}" → ${route.uri} (${route.method})`);
		}
	}
}

if (duplicateCount > 0) {
	console.warn(`[api-routes] ${duplicateCount} duplicate(s) removed. See warnings above.`);
}

// ----------------------------------------------------------
// Build output file content
// ----------------------------------------------------------
const generatedAt = new Date().toISOString();

const flatEntries = dedupedRoutes
	.map((r) => {
		const nameSuffix = r.name ? ` (${r.name})` : "";
		const authSuffix = r.auth ? " [auth]" : "";
		return `    ${r.key}: "${r.uri}",   // ${r.method}${authSuffix}${nameSuffix}`;
	})
	.join("\n");

const metaEntries = dedupedRoutes
	.map((r) => `    ${r.key}: { method: "${r.method}", uri: "${r.uri}", auth: ${r.auth} },`)
	.join("\n");

const output = `// ==========================================================
// AUTO-GENERATED — DO NOT EDIT MANUALLY
// Generated: ${generatedAt}
// Source:    codebase/backend/routes/api.php
// Script:    frontend/scripts/generate-api.mjs
// ==========================================================

/**
 * Flat map of route key → URI.
 * Keys are derived from ->name() when present (dot notation → underscore),
 * or from the full URI path as fallback.
 *
 * Use with the get(), post(), patch() hooks from Api.jsx.
 *
 * @example
 * import { ROUTES } from "@/config/api-routes";
 * const { data } = get(ROUTES.dashboard_chart_richieste_atp_imk);
 *
 * @example
 * import { ROUTES, route } from "@/config/api-routes";
 * const { trigger } = patch(route(ROUTES.tiraggio_modifica, { tiraggio_id: 42 }));
 */
export const ROUTES = {
${flatEntries}
};

/**
 * Full metadata map: method, uri, whether Sanctum auth is required.
 * Useful for building typed wrappers or internal tooling.
 */
export const ROUTES_META = {
${metaEntries}
};

/**
 * Build a URI by replacing {param} placeholders.
 *
 * @param {string} uri    - Route URI with optional {param} segments
 * @param {object} params - Key-value pairs to substitute
 * @returns {string}      - Resolved URI
 *
 * @example
 * route(ROUTES.tiraggio_modifica, { tiraggio_id: 42 })
 * // → "/api/tiraggio/modifica/42"
 *
 * @example
 * route(ROUTES.dashboard_detail, { user_id: 99 })
 * // → "/api/dashboard-detail/99"
 */
export function route(uri, params = {}) {
    return Object.entries(params).reduce(
        (resolved, [key, value]) => resolved.replace(\`{\${key}}\`, String(value)),
        uri
    );
}
`;

writeFileSync(OUTPUT_PATH, output, "utf-8");
// console.log(`[api-routes] Generated ${dedupedRoutes.length} routes → ${OUTPUT_PATH}`);

// Clean up /tmp/api.php after generation (Docker only).
// In dev the file lives in the monorepo and must never be deleted.
// if (PHP_ROUTES_PATH === PHP_ROUTES_PATH_DOCKER) {
// 	unlinkSync(PHP_ROUTES_PATH_DOCKER);
// 	console.log("[api-routes] Cleaned up /tmp/api.php");
// }

// ----------------------------------------------------------
// Helpers
// ----------------------------------------------------------

/**
 * Convert a Laravel dot-notation route name to a flat JS-friendly key.
 *
 * @example
 * nameToKey('dashboard.chart.richieste_atp.imk') → 'dashboard_chart_richieste_atp_imk'
 * nameToKey('tiraggio.modifica')                 → 'tiraggio_modifica'
 * nameToKey('tiraggio.perfeziona.direct')        → 'tiraggio_perfeziona_direct'
 */
function nameToKey(name) {
	return name
		.replace(/\./g, "_")
		.replace(/-/g, "_")
		.replace(/[^a-zA-Z0-9_]/g, "_")
		.replace(/__+/g, "_")
		.replace(/^_|_$/g, "");
}

/**
 * Derive a flat JS-friendly key from a full URI (fallback, no ->name() present).
 *
 * @example
 * uriToKey('/api/dashboard-chart/richieste-atp/imk') → 'dashboard_chart_richieste_atp_imk'
 */
function uriToKey(uri) {
	return uri
		.replace(/^\/api\//, "")
		.replace(/[\/-]/g, "_")
		.replace(/[{}]/g, "")
		.replace(/__+/g, "_")
		.replace(/^_|_$/g, "");
}
