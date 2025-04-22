// Server-Side Rendering (React generates HTML before hydration)

// File import statements:
// import HomePage from "@/page/home";

// 1. Core imports (React & Next.js)
// import React, { createContext, useCallback, useContext, useEffect, useMemo, useReducer, useRef, useState } from "react";
import Link from "next/link";

// 2. External imports (third-party libraries)
// import axios from "axios";
// import clsx from "clsx";
// import useSWR from "swr";
// import { AnimatePresence, motion } from "framer-motion";
// import { signIn, signOut, useSession } from "next-auth/react";

// 3. Absolute internal (`@/` alias)
import * as constants from "@/config/constants";
// import DefaultExportModule from "@/<path>/DefaultExport";
// import { NamedExportModule } from "@/<path>/NamedExport";
import { getDictionary } from "@/app/dictionaries";
import { TranslateProvider } from "@/providers/Translate";
import { BoilerplateComponent } from "@/components/blocks/Boilerplate";
// 4. Relative internal (same directory)
import "./page.scss";

// ===============================================
// ## ############################################
// ===============================================

// Get the base URL for assets from environment variables (publicly exposed)
// const BASE_URL = process.env.NEXT_PUBLIC_BACKEND_URL_SERVER;

export default async function HomePage({ params }) {
	// Get the language from route parameters
	const { lang } = await params;

	// Fetch translation dictionary based on language
	const translates = await getDictionary(lang);

	// Fetch data from the API with language header
	// const heroResponse = await fetch(`${BASE_URL}/api/${lang}/home/hero`, {
	// 	method: "GET",
	// 	credentials: "include",
	// 	headers: {
	// 		"Content-Type": "application/json",
	// 		"locale": lang,
	// 	},
	// });
	// const heroResponseJson = await heroResponse.json();

	return (
		<TranslateProvider lang={lang} translates={translates}>
			<div className="home_page">
				<div className="page_cont">
					<section className="cont_space_1">
						<div className="cont_mw_1">
							{/* <NamedExportModule idModule="nameModulePage" dataModule={dataModule} /> */}
							<Link href="/components">Components</Link>
							<BoilerplateComponent />
						</div>
					</section>
				</div>
			</div>
		</TranslateProvider>
	);
}
