// Server-Side Rendering (React generates HTML before hydration)
//
// import ProfileLayout from "@/layout/profile"; // File import statement

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
import { cookies } from "next/headers";
import { ToastsComponent } from "@/components/alerts/events/Toasts";

// 4. Relative internal (same directory)
import "./layout.scss";

// ===============================================
// ## ############################################
// ===============================================

export default async function ProfileLayout({ children, params }) {
	// Get the language from route parameters
	const { lang } = await params;
	const cookiesStore = await cookies();
	const phpSession = cookiesStore.get("php_session")?.value;
	const invitesResponse = await fetch(`${constants.BACKEND_URL_SERVER}/api/invites`, {
		method: "GET",
		credentials: "include",
		headers: {
			"Accept": "application/json",
			"Referer": constants.APP_URL,
			"X-Requested-With": "XMLHttpRequest",
			"Content-Type": "application/json",
			"cookie": "php_session=" + phpSession,
			"locale": lang,
		},
	});
	const invites = await invitesResponse.json();

	return <ToastsComponent />;
}
