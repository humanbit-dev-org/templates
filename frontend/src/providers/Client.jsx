"use client"; // marks module for full browser execution
//
// import { <Filename><Suffix> } from "@/<alias>/<Filename>"; // File import statement

import { createContext, useContext, useEffect, useState } from "react";

import { useDeviceInfo } from "@/hooks/client/deviceInfo";
import { usePathInfo } from "@/hooks/client/pathInfo";

// ===============================================
// ## ############################################
// ===============================================

// Create the context
const ClientContext = createContext();

// Fetch the current authenticated user from the Laravel API (with XSRF protection)
// async function fetchUser(lang, apiToken) {
// 	try {
// 		// Extract the XSRF token from browser cookies (Session Cookie Authentication)
// 		// const xsrfToken = document.cookie
// 		// 	.split("; ")
// 		// 	.find((row) => row.startsWith("XSRF-TOKEN"))
// 		// 	?.split("=")[1];

// 		// Extract the API token from browser cookies (API Token Authentication)

// 		// Make a secure request to the Laravel API to get the current user credentials
// 		const userResponse = await fetch(`${constants.BACKEND_URL_CLIENT}/api/user`, {
// 			method: "GET",
// 			// same-origin means that the request is only sent to the same origin as the current page
// 			// include means that the request is sent with the cookies of the current page
// 			// credentials: "same-origin|include",
// 			headers: {
// 				"Accept": "application/json",
// 				"Content-Type": "application/json",
// 				//"Referer": constants.APP_URL, // session-based authentication
// 				//"X-XSRF-TOKEN": xsrfToken, // session-based authentication
// 				"Authorization": "Bearer " + apiToken, // API token authentication
// 				"X-Locale": lang,
// 			},
// 		});

// 		// Handle failed response (unauthenticated or other errors)
// 		if (!userResponse.ok) {
// 			if (userResponse.status === 401) {
// 				return undefined; // Not logged in
// 			}
// 			console.error(`User fetch failed with status: ${userResponse.statusText}`);
// 			return undefined;
// 		}

// 		// Parse and return the user object from the response
// 		const responseData = await userResponse.json();
// 		return responseData;
// 	} catch (error) {
// 		// Handle unexpected fetch or parsing errors
// 		console.error("Error fetching user:", error);
// 		return undefined;
// 	}
// }

// Provide client-only values to the current component
export function ClientProvider({ children, lang, initialUser = null }) {
	// Example states (adjust as needed)
	// const [globalState, setGlobalState] = useState({});
	// const [apiData, setApiData] = useState(null);

	const pathInfo = usePathInfo(); // Extract path info on the client
	const [user, setUser] = useState(initialUser); // Store the current user
	const [apiToken, setApiToken] = useState(initialUser?.apiToken ?? null);
	const [isEmbedded, setIsEmbedded] = useState(false);

	useEffect(() => {
		setIsEmbedded(window.self !== window.top);
	}, []);

	useEffect(() => {
		// Extract apiToken from cookies
		const token = document.cookie
			.split("; ")
			.find((row) => row.startsWith("apiToken"))
			?.split("=")[1];
		setApiToken(token);
		if (!token) {
			console.warn("API token is missing");
		}
	}, []);

	const values = {
		// globalState,
		// setGlobalState,
		// apiData,
		// setApiData,
		...useDeviceInfo(),
		...usePathInfo(), // return { pathname, page, id, slug };
		user,
		isLoggedIn: user != undefined ? true : false, // Include user in context
		apiToken, // Include api token in context
		isEmbedded, // Include isEmbedded in context
	};

	return <ClientContext.Provider value={values}>{children}</ClientContext.Provider>;
}

// Custom hook to access context values
export function useClient() {
	return useContext(ClientContext);
}
