// Server-Side Rendering (React generates HTML before hydration)
//
// import ComponentsPage from "@/page/components"; // File import statement

import * as constants from "@/config/constants"; // Global constants used throughout the app for API access
import { getDictionary } from "@/app/dictionaries"; // Fetch translation dictionary based on language
import { IntlTelInputComponent } from "@/components/blocks/IntlTelInput"; // International Telephone Input
import { TranslateProvider } from "@/providers/Translate"; // Provides translation context and hook access for `lang` and `translates`
import "./page.scss";

// ===============================================
// ## ############################################
// ===============================================

export default async function ComponentsPage({ params }) {
	// Get the language from route parameters
	const { lang } = await params;

	// Fetch translation dictionary based on language
	const translates = await getDictionary(lang);

	// Fetch data from the API with language header
	// const heroResponse = await fetch(`${constants.BASE_URL}/api/${lang}/components/<section>`, {
	// 	method: "GET",
	// 	credentials: "include",
	// 	headers: {
	// 		"Content-Type": "application/json",
	// 		"locale": lang,
	// 	},
	// });
	// const heroResponseJson = await heroResponse.json();

	return (
		<div className="components_page">
			<div className="page_cont bg_color_fourth vh-100">
				<section className="cont_space_1">
					<div className="cont_mw_1">
						<main>
							{/* <IntlTelInputComponent /> */}

							<div className="block_wrap vert_charts text-center d-flex flex-wrap justify-content-center align-items-center mb-5 w-100">
								<div className="vert_chart mx-2 left" />

								<div className="vert_chart mx-2 ready fx" />
								<div className="vert_chart mx-2 ready fx cascade" />
								<div className="vert_chart mx-2 ready fx delay" />
								<div className="vert_chart mx-2 ready fx delay" />
								<div className="vert_chart mx-2 ready fx cascade delay" />
								<div className="vert_chart mx-2 ready fx cascade" />

								<div className="vert_chart mx-2 wait fx wait" />
								<div className="vert_chart mx-2 wait fx cascade wait" />
								<div className="vert_chart mx-2 wait fx delay wait" />
								<div className="vert_chart mx-2 wait fx delay wait" />
								<div className="vert_chart mx-2 wait fx cascade delay wait" />
								<div className="vert_chart mx-2 wait fx cascade wait" />
							</div>
						</main>
					</div>
				</section>
			</div>
		</div>
	);
}
