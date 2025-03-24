// File import statements:
// import { useCurrentPathname } from "@/components/blocks/currentPathname";

"use client";

import { useMemo } from "react";
import { usePathname } from "next/navigation";

export function useCurrentPathname() {
	const pathname = usePathname()?.split("/")[2]?.replace(/\/$/, ""); // Extract page name and remove trailing slash

	// Handle the case where only the language exists (e.g., `/it`)
	const formattedKey = !pathname
		? "Home"
		: pathname
				// Convert the pathname to a PascalCase key (e.g., "page-example" → "PageExample")
				.replace(/-/g, " ") // Replace dashes with spaces
				.replace(/\b\w/g, (char) => char.toUpperCase()) // Capitalize each word
				.replace(/\s/g, ""); // Remove spaces

	// Memoize the result to avoid unnecessary re-renders
	return useMemo(() => (formattedKey ? { [formattedKey]: true } : {}), [formattedKey]);
}
