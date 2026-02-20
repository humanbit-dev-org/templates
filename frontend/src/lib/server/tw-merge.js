// See https://github.com/dcastil/tailwind-merge/blob/v2.1.0/docs/configuration.md

import { createTailwindMerge, getDefaultConfig } from "tailwind-merge";
import { clsx } from "clsx";

// Custom Tailwind-Merge configuration
//
// See https://github.com/dcastil/tailwind-merge/blob/v2.1.0/docs/api-reference.md#createtailwindmerge
const customTwMerge = createTailwindMerge(() => {
	const defaultConfig = getDefaultConfig();

	return {
		...defaultConfig,
		prefix: "tw:", // Use the same prefix as in your Tailwind CSS config
	};
});

export function cn(...inputs) {
	// Merge classes using custom `twMerge` instance
	return customTwMerge(clsx(inputs));
}
