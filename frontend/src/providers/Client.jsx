// import { ClientProvider } from "@/providers/Client"; // File import statement
//
"use client"; // marks module for full browser execution

import { createContext, useContext, useEffect, useState } from "react"; // Core imports (React & Next.js)
import * as constants from "@/config/constants"; // Global constants shared across the app
import { useDeviceInfo } from "@/hooks/deviceInfo"; // Track window width and compute device flags
import { usePathInfo } from "@/hooks/pathInfo"; // Extract structured path info from the current URL

// ===============================================
// ## ############################################
// ===============================================

// Create the context
const ClientContext = createContext();

// Fetch the current authenticated user from the Laravel API (with XSRF protection)
async function fetchUser(lang) {
	try {
		// Extract the XSRF token from browser cookies (Session Cookie Authentication)
		const xsrfToken = document.cookie
			.split("; ")
			.find((row) => row.startsWith("XSRF-TOKEN"))
			?.split("=")[1];

		// Extract the API token from browser cookies (API Token Authentication)
		// const apiToken = document.cookie
		// 	.split("; ")
		// 	.find((row) => row.startsWith("apiToken"))
		// 	?.split("=")[1];

		// If no token is found, log a warning and return early
		// if (!apiToken) {
		// 	console.warn("API token is missing");
		// 	return undefined;
		// }

		// Make a secure request to the Laravel API to get the current user credentials
		const userResponse = await fetch(`${constants.BACKEND_URL_CLIENT}/api/user`, {
			method: "GET",
			credentials: "include", // Session Cookie Authentication
			headers: {
				"Accept": "application/json",
				"Content-Type": "application/json",
				"Referer": constants.APP_URL, // Session Cookie Authentication
				"X-XSRF-TOKEN": xsrfToken, // Pass XSRF token for CSRF protection (Session Cookie Authentication)
				"X-Locale": lang, // Pass user locale
				// "Authorization": "Bearer " + apiToken, // API Token Authentication
			},
		});

		// Handle failed response (unauthenticated or other errors)
		if (!userResponse.ok) {
			if (userResponse.status === 401) {
				return undefined; // Not logged in
			}
			console.error(`User fetch failed with status: ${userResponse.statusText}`);
			return undefined;
		}

		// Parse and return the user object from the response
		const responseData = await userResponse.json();
		return responseData;
	} catch (error) {
		// Handle unexpected fetch or parsing errors
		console.error("Error fetching user:", error);
		return undefined;
	}
}

// Provide client-only values to the current component
export function ClientProvider({ children, lang }) {
	// Example states (adjust as needed)
	// const [globalState, setGlobalState] = useState({});
	// const [apiData, setApiData] = useState(null);

	const pathInfo = usePathInfo(); // Extract path info on the client
	const [user, setUser] = useState(null); // Store the current user

	useEffect(() => {
		// Fetch and store the current user when the path changes
		const fetchUserFunction = async () => {
			const user = await fetchUser(lang);
			setUser(user);
		};

		fetchUserFunction();
	}, [pathInfo.pathname, lang]); // Re-run if pathname changes

	const values = {
		// globalState,
		// setGlobalState,
		// apiData,
		// setApiData,
		...useDeviceInfo(),
		...usePathInfo(), // return { pathname, page, id, slug };
		user,
		isLoggedIn: user != undefined ? true : false, // Include user in context
	};

	return <ClientContext.Provider value={values}>{children}</ClientContext.Provider>;
}

// Custom hook to access context values
export function useClient() {
	return useContext(ClientContext);
}
