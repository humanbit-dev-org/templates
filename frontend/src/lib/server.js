// import { getServer } from "@/lib/server"; // File import statement

import { cookies } from "next/headers";
import * as constants from "@/config/constants"; // Global constants shared across the app
import { getDeviceInfo } from "@/server/device-info"; // Parse `user-agent` to detect device type
import { getPathInfo } from "@/server/path-info"; // Get structured path info from the current request

// ===============================================
// ## ############################################
// ===============================================

async function fetchUser() {
	try {
		const cookiesStore = await cookies();
		const phpSession = cookiesStore.get("php_session")?.value;
		const userResponse = await fetch(`${constants.BACKEND_URL_SERVER}/api/user`, {
			method: "GET",
			credentials: "include",
			headers: {
				"Accept": "application/json",
				"Content-Type": "application/json",
				"Referer": constants.APP_URL,
				"X-Requested-With": "XMLHttpRequest",
				"cookie": "php_session=" + phpSession,
				// "Authorization": "Bearer " + apiToken,
			},
		});
		const responseData = await userResponse.json();
		if (responseData.message === "Unauthenticated.") {
			return undefined;
		}
		return responseData;
	} catch (error) {
		console.error("Error fetching:", error);
	}
}

// Provide server-only values to the current component
export async function getServer() {
	const user = await fetchUser();
	return {
		...(await getDeviceInfo()),
		...(await getPathInfo()),
		user: await fetchUser(),
		isLoggedIn: user !== undefined,
	};
}
