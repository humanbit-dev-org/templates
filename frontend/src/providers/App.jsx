"use client"; // marks module for full browser execution
//
// import { <Filename><Suffix> } from "@/<alias>/<Filename>"; // File import statement

import { createContext } from "react";

import { ApiProvider } from "@/providers/Api";
import { ClientProvider } from "@/providers/Client";
import { TranslateProvider } from "@/providers/Translate";

// ===============================================
// ## ############################################
// ===============================================

// Root application context (reserved for future global app-level values)
export const AppContext = createContext(null);

// Compose and orchestrate all top-level providers in a single wrapper
export function AppProvider({ children, lang, translates, initialUser = null }) {
	return (
		// Provide optional global app context (currently unused)
		<AppContext.Provider value={null}>
			{/* Client-side state (device info, path info, authenticated user) */}
			<ClientProvider
				lang={lang}
				initialUser={initialUser}>
				{/* Translation context (current language and dictionary) */}
				<TranslateProvider
					lang={lang}
					translates={translates}>
					{/* Centralized API layer (SWR integration, mutations, cache control) */}
					<ApiProvider lang={lang}>{children}</ApiProvider>
				</TranslateProvider>
			</ClientProvider>
		</AppContext.Provider>
	);
}
