// GENERAL ENTRY POINT

// Server-Side Rendering (React generates HTML before hydration)
//
// import RootLayout from "@/layout/root"; // File import statement

// EXTERNAL DEPENDENCIES
import { cookies } from "next/headers";

// PROJECT UTILITIES (metadata | context | translates | cookies)
import * as constants from "@/config/constants";
import MetadataSetup, { FontsLoader, GlobalScripts } from "@/config/metadata-setup";
import { ClientProvider } from "@/providers/Client";
import { getDictionary } from "@/app/dictionaries"; // TODO: Keep this here?
import { getServer } from "@/lib/server";
import { klaroConfig } from "@/config/klaro-config"; // cookie configuration
import { KlaroCookieConsent } from "@/config/klaro-cookie-consent"; // cookie handling

// USER PROMPTS (modals | toasts)
import { ForgotPasswordComponent } from "@/components/dialogs/ForgotPasswordComponent";
import { PasswordResetComponent } from "@/components/dialogs/PasswordResetComponent";
import { RegisterComponent } from "@/components/dialogs/RegisterComponent";
import { SignInComponent } from "@/components/dialogs/SignInComponent";

// INTERNAL RESOURCES
import { NavSideBurgerComponent } from "@/navbars/NavSideBurger";
// import { NavSlideTopComponent } from "@/navbars/NavSlideTop";
import "./globals.scss";
import "./layout.scss";

// ===============================================
// ## ############################################
// ===============================================

// Next.js 13's App Router `viewport` object
//
// https://nextjs.org/docs/app/api-reference/functions/generate-viewport#the-viewport-object
export const viewport = {
	width: "device-width, shrink-to-fit=no",
	initialScale: 1.0,
	userScalable: false,
	minimumScale: 1.0,
	maximumScale: 1.0,
	shrinkToFit: false,
	interactiveWidget: "resizes-visual", // Also supported but less commonly used
};

// Next.js 13's App Router `generateMetadata` object
//
// https://nextjs.org/docs/app/api-reference/functions/generate-metadata
export async function generateMetadata({ params }) {
	const ssr = await getServer(); // Get server-side context
	const { lang } = await params; // Get language from route params
	const url = `${constants.BASE_URL_SERVER}/api/${lang}/${ssr.page}/seo`; // Construct the URL for the SEO metadata API

	try {
		// Get metadata from the backend
		const response = await fetch(url, {
			method: "GET",
			credentials: "include",
			headers: {
				"Content-Type": "application/json",
				"locale": lang,
			},
		});

		// Exit early if the request fails
		if (!response.ok) {
			console.error("SEO fetch failed:", response.status, url);
			return {};
		}

		// Parse the JSON response and format it
		const rawMetadata = await response.json(); // Raw API response
		const metadataJson = await MetadataSetup(rawMetadata, lang); // Structured SEO metadata

		// Inject the processed metadata and append additional headers
		return {
			...metadataJson, // Finalized metadata object
			acceptCH: ["viewport-width"], // Enables responsive layout via Client Hints
		};
	} catch (error) {
		console.error("SEO fetch error:", error); // Log unexpected fetch or parsing errors
		return {}; // Return empty metadata on failure
	}
}

// ===============================================
// ## ############################################
// ===============================================

// Main layout function for wrapping page content with children and dynamic parameters
export default async function RootLayout({ children, params }) {
	// Get the language from route params
	const { lang } = (await params) || {};

	// Dynamically gather all font variables from the font loader
	const fontClasses = Object.values(FontsLoader)
		.map((font) => font.variable)
		.join(" ");

	// Fetch translation dictionary based on language
	const translates = await getDictionary(lang);

	// // Get cookies from the request
	// const cookiesStore = await cookies();

	// // Extract the Laravel session ID
	// const laravelSession = cookiesStore.get("laravel_session")?.value;

	// // Fetch authenticated user data from Laravel Sanctum API
	// const userResponse = await fetch(`${constants.BASE_URL}/api/user`, {
	// 	method: "GET",
	// 	credentials: "include",
	// 	headers: {
	// 		"Accept": "application/json",
	// 		"Content-Type": "application/json",
	// 		"X-Requested-With": "XMLHttpRequest",
	// 		"Referer": process.env.APP_URL,
	// 		"cookie": "laravel_session=" + laravelSession,
	// 	},
	// });

	// // Parse user data response
	// const userResponseJson = await userResponse.json();

	// // Fetch menu/page data from the API
	// const menuResponse = await fetch(`${constants.BASE_URL}/api/pages`, {
	// 	method: "GET",
	// 	credentials: "include",
	// 	headers: {
	// 		"Content-Type": "application/json",
	// 	},
	// });

	// // Parse menu data response
	// const menuResponseJson = await menuResponse.json();

	return (
		<html lang={lang} className={fontClasses}>
			{/* <body className="bg_color_white fx_load"> */}
			<body className="bg_color_white">
				{/* USER AND LOCALE CONTEXT (navbar | footer) */}
				<ClientProvider lang={lang} dict={translates}>
					<div className="root_layout container_structure container-fluid">
						<div className="grid_cont navbar row justify-content-center position-sticky">
							{/* <NavSideBurgerComponent menu={menuResponseJson} /> */}
							{/* <NavSlideTopComponent /> */}
						</div>

						{children}
					</div>

					{/* PROJECT UTILITIES (scripts | cookies) */}
					<GlobalScripts />
					<KlaroCookieConsent config={klaroConfig} />

					{/* USER PROMPTS (modals | toasts) */}
					<ForgotPasswordComponent lang={lang} />
					<PasswordResetComponent lang={lang} />
					<RegisterComponent lang={lang} />
					<SignInComponent lang={lang} />
				</ClientProvider>
			</body>
		</html>
	);
}
