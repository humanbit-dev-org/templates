// File import statements:
// import { ContentProvider } from "@/providers/Contents";

"use client";

// 1. React & Next.js core imports
import { createContext, useContext } from "react";
// 2. External third-party libraries
// 3. Absolute internal imports (from `@/` alias)
// 4. Relative internal imports (from the same directory)

const ContentContext = createContext();

export function useContentContext() {
	return useContext(ContentContext);
}

export function ContentProvider({ contents, children }) {
	return <ContentContext.Provider value={contents}>{children}</ContentContext.Provider>;
}

export const useContent = () => useContext(ContentContext);
