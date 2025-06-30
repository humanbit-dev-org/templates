// Server-Side Rendering (React generates HTML before hydration)
//
// import BoilerplatePage from "@/page/boilerplate"; // File import statement

// 1. Core imports (React & Next.js)
// import Link from "next/link"; // Client-side routing with automatic pre-fetching
// import { cookies } from "next/headers"; // Access request cookies on the server (e.g., auth tokens, preferences)

// 2. External imports (third-party libraries)
// import axios from "axios"; // Promise-based HTTP client for data fetching (API requests)
// import clsx from "clsx"; // Conditional CSS class name joiner

// 3. Absolute internal (`@/` alias)
// import DefaultExportModule from "@/<path>/DefaultExport";
// import { NamedExportModule } from "@/<path>/NamedExport";
import * as constants from "@/config/constants"; // Global constants shared across the app
import { getDictionary } from "@/app/dictionaries"; // Fetch translation dictionary based on language
import { TranslateProvider } from "@/providers/Translate"; // Provides translation context and hook access for `lang` and `translates`

// 4. Relative internal (same directory)
import "./page.scss";

// ===============================================
// ## ############################################
// ===============================================

export default async function BoilerplatePage({ params }) {
	// Get the language from route parameters
	const { lang } = await params;

	// Fetch translation dictionary based on language
	const translates = await getDictionary(lang);

	// Fetch data from the API with language header
	// const dataResponse = await fetch(`${constants.BASE_URL}/api/${lang}/boilerplate/<section>`, {
	// 	method: "GET",
	// 	credentials: "include",
	// 	headers: {
	// 		"Content-Type": "application/json",
	// 		"locale": lang,
	// 	},
	// });
	// const dataResponseJson = await dataResponse.json();

	return (
		<TranslateProvider lang={lang} translates={translates}>
			<div className="boilerplate_page">
				<div className="page_cont">
					<section className="cont_space_1">
						<div className="cont_mw_1">
							{/* <NamedExportModule idModule="nameModulePage" dataModule={dataModule} /> */}
						</div>
					</section>
				</div>
			</div>
		</TranslateProvider>
	);
}
