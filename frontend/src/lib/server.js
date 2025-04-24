// import { getServer } from "@/server/path-info-ssr"; // File import statement

import { getPathInfo } from "@/server/path-info";

// ===============================================
// ## ############################################
// ===============================================

// Provide server-only values to the current component
export async function getServer() {
	return {
		...(await getPathInfo()), // Get structured path info from the current request
	};
}
