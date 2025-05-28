// import { getServer } from "@/lib/server"; // File import statement

import { getDeviceInfo } from "@/server/device-info"; // Parse `user-agent` to detect device type
import { getPathInfo } from "@/server/path-info"; // Get structured path info from the current request

// ===============================================
// ## ############################################
// ===============================================

// Provide server-only values to the current component
export async function getServer() {
	return {
		...(await getDeviceInfo()),
		...(await getPathInfo()),
	};
}
