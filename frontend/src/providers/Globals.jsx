// File import statements:
// import { GlobalProvider } from "@/providers/Globals";

"use client";

// 1. React & Next.js core imports
import { createContext, useContext, useState } from "react";
// 2. External third-party libraries
// 3. Absolute internal imports (from `@/` alias)
// 4. Relative internal imports (from the same directory)

// Create the context
const GlobalContext = createContext();

// Provider component
export function GlobalProvider({ children }) {
	// Example states (adjust as needed)
	const [globalState, setGlobalState] = useState({});
	const [apiData, setApiData] = useState(null);

	return (
		<GlobalContext.Provider value={{ globalState, setGlobalState, apiData, setApiData }}>
			{children}
		</GlobalContext.Provider>
	);
}

// Custom hook for easy access
export function useGlobalContext() {
	return useContext(GlobalContext);
}
