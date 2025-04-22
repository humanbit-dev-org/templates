// File import statements:
// import { getServer } from "@/server/path-info-ssr";

// 1. Absolute imports (project utilities)
import { getPathInfo } from "@/server/path-info";

// Server-side utility to aggregate and expose SSR-only values
// Returns structured server-only values for use in SSR components
export async function getServer() {
	return {
		...(await getPathInfo()), // Get structured path info from the current request
	};
}
