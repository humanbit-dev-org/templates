// Server-Side Rendering (React generates HTML before hydration)

// File import statements:
// import HomeLayout from "@/layout/home";

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
import MetadataSetup from "@/config/metadata-setup";

// 4. Relative internal (same directory)
import "./layout.scss";

// ===============================================
// ## ############################################
// ===============================================

// Get the base URL for assets from environment variables (publicly exposed)
const BASE_URL = process.env.NEXT_PUBLIC_BACKEND_URL_SERVER;

// export async function generateMetadata({ params }) {
// 	const { lang } = await params;
// 	const url = `${BASE_URL}/api/${lang}/home/seo`;
// 	return await MetadataSetup(url, lang);
// }

export default async function HomeLayout({ children, params }) {
	// Get the language from route parameters
	const { lang } = await params;

	// Fetch data from the API with language header
	// const dataResponse = await fetch(`${BASE_URL}/api/${lang}/<route>`, {
	// 	method: "GET",
	// 	credentials: "include",
	// 	headers: {
	// 		"Content-Type": "application/json",
	// 		"locale": lang,
	// 	},
	// });
	// const dataResponseJson = await dataResponse.json();

	return (
		<>
			<div className="boilerplate_layout grid_cont footer order-2 order-xl-0">{/* content */}</div>

			<div className="boilerplate_layout grid_cont content order-1">{children}</div>
		</>
	);
}
