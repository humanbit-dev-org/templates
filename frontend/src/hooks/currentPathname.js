// File import statements:
// import { useCurrentPathname } from "@/hooks/currentPathname";

"use client";

import { useMemo } from "react";
import { usePathname } from "next/navigation";

export function useCurrentPathname() {
	const pathname = usePathname() || "";

	// Break the pathname into segments (e.g., `/en/article-list/25/title` → ["en", "article-list", "25", "title"])
	const segments = pathname.split("/").filter(Boolean); // Remove empty strings

	let page = null; // Will hold the static page name (e.g., "article-list")
	const data = {}; // Will hold dynamic values like id or slug

	for (let i = 0; i < segments.length; i++) {
		const seg = segments[i];

		// Skip the first segment if it's a 2-letter lowercase code (e.g., "en", "it") — assumed to be the locale
		if (i === 0 && /^[a-z]{2}$/.test(seg)) continue;

		// Skip visual group folders or dynamic folders (e.g., "(admin)", "[id]")
		if (seg.startsWith("(") || seg.startsWith("[")) continue;

		if (!page) {
			// First valid folder after locale and group/dynamic — treated as the page name
			page = seg;
		} else if (/^\d+$/.test(seg)) {
			// If it's a number, assume it's an ID
			data.id = seg;
		} else {
			// Otherwise, treat as a slug (e.g., "today-in-town")
			data.slug = seg;
		}
	}

	// Build the returned object with custom behavior:
	// - Acts like a string when compared (e.g., currentPathname === "article-list")
	// - Still allows access to values like currentPathname.id or currentPathname.slug
	const currentPath = {
		...data,
		toString: () => page,
		valueOf: () => page,
		[Symbol.toPrimitive]: () => page,
	};

	// Memoize to avoid unnecessary re-renders
	return useMemo(() => currentPath, [pathname]);
}
