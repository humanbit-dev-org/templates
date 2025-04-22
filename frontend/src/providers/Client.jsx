// File import statements:
// import { ClientProvider } from "@/providers/Client";

"use client";

// 1. React & Next.js core imports
import { createContext, useContext, useState, useEffect } from "react";

// 2. External third-party libraries

// 3. Absolute internal imports (from `@/` alias)
import { usePathInfo } from "@/hooks/pathInfo";

// 4. Relative internal imports (from the same directory)

const BASE_URL = process.env.NEXT_PUBLIC_BACKEND_URL_CLIENT;

// Create the context
const ClientContext = createContext();

async function fetchUser(lang) {
	try {
		// Get the XSRF token from the cookie
		const xsrfToken = document.cookie
			.split("; ")
			.find((row) => row.startsWith("XSRF-TOKEN"))
			?.split("=")[1];

		// If the XSRF token is missing, return undefined
		if (!xsrfToken) {
			console.warn("XSRF token is missing");
			return undefined;
		}

		// Fetch user data from the Laravel API with session credentials
		const userResponse = await fetch(`${BASE_URL}/api/user`, {
			method: "GET",
			credentials: "include",
			headers: {
				"Accept": "application/json",
				"Referer": process.env.APP_URL,
				"X-Requested-With": "XMLHttpRequest",
				"Content-Type": "application/json",
				"X-XSRF-TOKEN": xsrfToken,
				"locale": lang,
			},
		});

		// If the user data fetch fails, return undefined
		if (!userResponse.ok) {
			if (userResponse.status === 401) {
				return undefined;
			}
			console.error(`User fetch failed with status: ${userResponse.statusText}`);
			return undefined;
		}

		// Parse the user data from the response
		const responseData = await userResponse.json();
		return responseData.user;
	} catch (error) {
		console.error("Error fetching user:", error);
		return undefined;
	}
}

// Provides global client-only values to child components
export function ClientProvider({ children, lang }) {
	// Example states (adjust as needed)
	// const [globalState, setGlobalState] = useState({});
	// const [apiData, setApiData] = useState(null);

	const [user, setUser] = useState(null);
	const pathInfo = usePathInfo();

	useEffect(() => {
		const fetchUserFunction = async () => {
			const user = await fetchUser(lang);
			setUser(user);
		};
		fetchUserFunction();
	}, [pathInfo.pathname]);

	const values = {
		// globalState,
		// setGlobalState,
		// apiData,
		// setApiData,
		user,
		...usePathInfo(), // Get structured path info from the current URL
	};

	return <ClientContext.Provider value={values}>{children}</ClientContext.Provider>;
}

// Custom hook to access context values
export function useClient() {
	return useContext(ClientContext);
}
