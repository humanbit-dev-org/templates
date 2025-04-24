// import { TranslateProvider } from "@/providers/Translate"; // File import statement
//
"use client";

import { createContext, useContext } from "react";

const TranslateContext = createContext();

export function useTranslateContext() {
	return useContext(TranslateContext);
}

export function TranslateProvider({ lang, translates, children }) {
	return <TranslateContext.Provider value={{ lang, translates }}>{children}</TranslateContext.Provider>;
}

export const useTranslate = () => useContext(TranslateContext);
