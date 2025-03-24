// Server-Side Rendering (React generates HTML before hydration)

// File import statements:
// import BoilerplatePage from "@/page/boilerplate";

// 1. Core imports (React & Next.js)
// import React, { createContext, useCallback, useContext, useEffect, useMemo, useReducer, useRef, useState } from "react";

// 2. External imports (third-party libraries)
// import axios from "axios";
// import clsx from "clsx";
// import useSWR from "swr";
// import { AnimatePresence, motion } from "framer-motion";
// import { signIn, signOut, useSession } from "next-auth/react";

// 3. Absolute internal (`@/` alias)
// import DefaultExportModule from "@/<path>/DefaultExports";
// import { NamedExportModule } from "@/<path>/NamedExports";

// 4. Relative internal (same directory)
import "./page.scss";

// ===============================================
// ## ############################################
// ===============================================

// Get the base URL for assets from environment variables (publicly exposed)
const baseUrl = process.env.NEXT_PUBLIC_ASSETS_URL;

export default async function BoilerplatePage({ params }) {
	// Get the language from route parameters
	const { lang } = await params;

	// Fetch translation dictionary based on language
	const translates = await getDictionary(lang);

	// Fetch hero section data from the API
	const heroResponse = await fetch(`${baseUrl}/api/la-nostra-storia/hero`, {
		method: "GET",
		credentials: "include",
		headers: {
			"Content-Type": "application/json",
		},
	});
	const heroData = await heroResponse.json();

	return (
		<TranslateProvider lang={lang} translates={translates}>
			<div className="boilerplate_page">
				<div className="page_cont">
					<section className="cont_space_1">
						<div className="cont_mw_1">
							{/* <NamedExportModule idModule="pageModule" dataModule={dataModule} /> */}
						</div>
					</section>
				</div>
			</div>
		</TranslateProvider>
	);
}
