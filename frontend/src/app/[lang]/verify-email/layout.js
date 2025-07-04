// Server-Side Rendering (React generates HTML before hydration)
//
// import VerifyEmailLayout from "@/layout/verify_email"; // File import statement

// 1. Core imports (React & Next.js)
// import Link from "next/link"; // Client-side routing with automatic pre-fetching
// import { cookies } from "next/headers"; // Access request cookies on the server (e.g., auth tokens, preferences)

// 2. External imports (third-party libraries)
// import axios from "axios"; // Promise-based HTTP client for data fetching (API requests)
// import clsx from "clsx"; // Conditional CSS class name joiner

// 3. Absolute internal (`@/` alias)
// import DefaultExportModule from "@/<path>/DefaultExports";
// import { NamedExportModule } from "@/<path>/NamedExports";
import * as constants from "@/config/constants"; // Global constants shared across the app

// 4. Relative internal (same directory)
import "./layout.scss";

// ===============================================
// ## ############################################
// ===============================================

export default async function VerifyEmailLayout({ children, params }) {
	// Get the language from route parameters
	const { lang } = await params;

	// Fetch data from the API with language header
	// const dataResponse = await fetch(`${constants.APP_URL}/api/${lang}/<route>`, {
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
			<div className="verify_email_layout grid_cont content">{children}</div>
		</>
	);
}
