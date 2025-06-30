// Server-Side Rendering (React generates HTML before hydration)
//
// import TestsLayout from "@/layout/tests"; // File import statement

import * as constants from "@/config/constants"; // Global constants shared across the app
import "./layout.scss";

// ===============================================
// ## ############################################
// ===============================================

export default async function TestsLayout({ children, params }) {
	// Get the language from route parameters
	const { lang } = await params;

	// Fetch data from the API with language header
	// const dataResponse = await fetch(`${constants.BASE_URL}/api/${lang}/<route>`, {
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
			<div className="tests_layout grid_cont footer order-2 order-xl-0">{/* content */}</div>

			<div className="tests_layout grid_cont content order-1">{children}</div>
		</>
	);
}
