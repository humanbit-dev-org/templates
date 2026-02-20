// import { getPathInfo } from "@/lib/server/path-info"; // File import statement

// ===============================================
// ## ############################################
// ===============================================

// Extracts structured path info from the current URL on the server
export async function getPathInfo() {
	// Import and call the `headers()` function from the Next.js server module
	const headersList = await import("next/headers").then((m) => m.headers());

	// Extract the pathname from custom request headers
	const pathname = headersList.get("x-pathname");

	const queryParams = headersList.get("x-query-params");
	if (queryParams) {
		queryParams = Object.fromEntries(new URLSearchParams(queryParams));
	}

	// Break the pathname into segments
	const segments = pathname?.split("/").filter(Boolean) || []; // Remove empty strings

	let page = null; // Holds the static page name (e.g., "page-example")
	let id = null; // Holds dynamic numeric ID from the URL (if present)
	let slug = null; // Holds dynamic text slug from the URL (if present)

	// Expecting format: `[lang]/[page]/[id]/[slug]`
	if (segments.length >= 2 && /^[a-z]{2}$/.test(segments[0])) {
		page = segments[1];

		// Extract ID (if available)
		if (/^\d+$/.test(segments[2])) {
			id = segments[2];
		}
		// Extract slug (if available)
		if (segments[3]) {
			slug = segments[3];
		}
	}

	return { pathname, page, id, slug, queryParams };
}
