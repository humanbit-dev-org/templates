// File import statements:
// import { ClientProvider } from "@/providers/Client";

"use client";

// 1. React & Next.js core imports
import { createContext, useContext, useState } from "react";

// 2. External third-party libraries

// 3. Absolute internal imports (from `@/` alias)
import { usePathInfo } from "@/hooks/pathInfo";

// 4. Relative internal imports (from the same directory)

// Create the context
const ClientContext = createContext();

// Provides global client-only values to child components
export function ClientProvider({ children }) {
	// Example states (adjust as needed)
	// const [globalState, setGlobalState] = useState({});
	// const [apiData, setApiData] = useState(null);

	const values = {
		// globalState,
		// setGlobalState,
		// apiData,
		// setApiData,
		...usePathInfo(), // Get structured path info from the current URL
	};

	return <ClientContext.Provider value={values}>{children}</ClientContext.Provider>;
}

// Custom hook to access context values
export function useClient() {
	return useContext(ClientContext);
}
