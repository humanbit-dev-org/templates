// File import statements:
// import { TranslateProvider } from "@/providers/Translate";

"use client";

// 1. React & Next.js core imports
import { createContext, useContext } from "react";
// 2. External third-party libraries
// 3. Absolute internal imports (from `@/` alias)
// 4. Relative internal imports (from the same directory)

const TranslateContext = createContext();

export function useTranslateContext() {
	return useContext(TranslateContext);
}

export function TranslateProvider({ lang, translates, children }) {
	return <TranslateContext.Provider value={{ lang, translates }}>{children}</TranslateContext.Provider>;
}

export const useTranslate = () => useContext(TranslateContext);
