// import { usePathInfo } from "@/hooks/pathInfo"; // File import statement
//
"use client"; // marks module for full browser execution

import { usePathname } from "next/navigation";

// ===============================================
// ## ############################################
// ===============================================

// Extracts structured path info (pathname, page, id, slug) from the current URL on the client
export function usePathInfo() {
	const pathname = usePathname() || "";

	// Break the pathname into segments
	const segments = pathname.split("/").filter(Boolean); // Remove empty strings

	let page = null; // Holds the static page name (e.g., "page-example")
	let id = null; // Holds dynamic numeric ID from the URL (if present)
	let slug = null; // Holds dynamic text slug from the URL (if present)

	// Expecting format: /[lang]/[page]/[id]/[slug]
	if (segments.length >= 1 && /^[a-z]{2}$/.test(segments[0])) {
		page = segments[1] || "home"; // default to `home` if not present

		// Extract optional ID and slug (if available)
		if (/^\d+$/.test(segments[2])) {
			id = segments[2];
		}
		if (segments[3]) {
			slug = segments[3];
		}
	}

	return { pathname, page, id, slug };
}
