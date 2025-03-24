// Server-Side Rendering (React generates HTML before hydration)

// File import statements:
// import BoilerplateLayout from "@/layout/boilerplate";

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
import "./layout.scss";

// ===============================================
// ## ############################################
// ===============================================

// Get the base URL for assets from environment variables (publicly exposed)
const baseUrl = process.env.NEXT_PUBLIC_ASSETS_URL;

export async function generateMetadata({ params }) {
	const { lang } = await params;
	const url = `${baseUrl}/api/${lang}/la-nostra-storia/seo`;
	return await MetadataSetup(url, lang);
}

export default async function BoilerplateLayout({ children, params }) {
	// Get the language from route parameters
	const { lang } = await params;

	// Build the API URL for localized chapters
	const apiUrl = `${baseUrl}/api/${lang}/chapters`;

	// Fetch the chapters from the API with language header
	const response = await fetch(apiUrl, {
		method: "GET",
		credentials: "include",
		headers: {
			"Content-Type": "application/json",
			"locale": lang,
		},
	});
	const chapters = await response.json();

	return (
		<>
			<div className="boilerplate_layout grid_cont footer order-2 order-xl-0">{/* content */}</div>

			<div className="boilerplate_layout grid_cont content order-1">{/* content */}</div>
		</>
	);
}
