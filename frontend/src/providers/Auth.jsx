"use client"; // marks module for full browser execution
//
// import { <Filename><Suffix> } from "@/<alias>/<Filename>"; // File import statement

import { createContext, useContext } from "react";

// ===============================================
// ## ############################################
// ===============================================

// Creiamo il contesto UserContext
const UserContext = createContext(null);
const LocaleContext = createContext(null);

export const AuthProvider = function ({ children, lang, dict, user }) {
	return (
		<>
			<LocaleContext.Provider value={{ lang, dict }}>
				<UserContext.Provider value={{ user }}>
					<div className="cont_structure container-fluid">{children}</div>
				</UserContext.Provider>
			</LocaleContext.Provider>
		</>
	);
};

export const useUser = () => useContext(UserContext);
export const useLocale = () => useContext(LocaleContext);
