// Server-Side Rendering (React generates HTML before hydration)
//
// import TestsPage from "@/page/tests"; // File import statement

import * as constants from "@/config/constants"; // Global constants shared across the app
import { getDictionary } from "@/app/dictionaries"; // Fetch translation dictionary based on language
import { IntlTelInputComponent } from "@/components/blocks/IntlTelInput"; // International Telephone Input
import { TranslateProvider } from "@/providers/Translate"; // Provides translation context and hook access for `lang` and `translates`
import "./page.scss";

// ===============================================
// ## ############################################
// ===============================================

export default async function TestsPage({ params }) {
	// Get the language from route parameters
	const { lang } = await params;

	// Fetch translation dictionary based on language
	const translates = await getDictionary(lang);

	// Fetch data from the API with language header
	// const heroResponse = await fetch(`${constants.BASE_URL}/api/${lang}/tests/<section>`, {
	// 	method: "GET",
	// 	credentials: "include",
	// 	headers: {
	// 		"Content-Type": "application/json",
	// 		"locale": lang,
	// 	},
	// });
	// const heroResponseJson = await heroResponse.json();

	return (
		<div className="tests_page">
			<div className="page_cont bg_color_fourth vh-100">
				<section className="cont_space_1">
					<div className="cont_mw_1">
						<main>
							{/* <IntlTelInputComponent /> */}

							<h1 className="tw:p-12">TYPOGRAPHY TEST</h1>
							<h1 className="tw:p-5">TYPOGRAPHY TEST</h1>
							<h1 className="tw:text-9xl">TYPOGRAPHY TEST</h1>
							<h1 className="tw:text-2xl">TYPOGRAPHY TEST</h1>

							{/* <div className="el_body big"> */}
							<div className="big tw:text-red-500">
								Lorem ipsum dolor, <span className="fw-700">fw-700</span> sit amet consectetur adipisicing
								elit. Ad quasi asperiores voluptatum odit, molestiae repellendus fugiat nihil at ullam! Quis
								veniam enim, excepturi vitae consectetur eos. Voluptate ut accusamus fugiat.
							</div>

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
