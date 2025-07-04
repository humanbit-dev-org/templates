// Server-Side Rendering (React generates HTML before hydration)
//
// import HomePage from "@/page/home"; // File import statement

import * as constants from "@/config/constants"; // Global constants shared across the app
import { getDictionary } from "@/app/dictionaries"; // Fetch translation dictionary based on language
import { TranslateProvider } from "@/providers/Translate"; // Provides translation context and hook access for `lang` and `translates`
import "./page.scss";

// ===============================================
// ## ############################################
// ===============================================

export default async function HomePage({ params }) {
	// Get the language from route parameters
	const { lang } = await params;

	// Fetch translation dictionary based on language
	const translates = await getDictionary(lang);

	// Fetch data from the API with language header
	// const heroResponse = await fetch(`${constants.BASE_URL}/api/${lang}/home/<section>`, {
	// 	method: "GET",
	// 	credentials: "include",
	// 	headers: {
	// 		"Content-Type": "application/json",
	// 		"locale": lang,
	// 	},
	// });
	// const heroResponseJson = await heroResponse.json();

	return (
		<div className="home_page">
			<div className="page_cont">
				<section className="cont_space_1">
					<div className="cont_mw_1">
						{/* <NamedExportModule idModule="nameModulePage" dataModule={dataModule} /> */}
					</div>
				</section>
			</div>
		</div>
	);
}
