// GENERAL ENTRY POINT

// Server-Side Rendering (React generates HTML before hydration)
//
// import RootLayout from "@/layout/root"; // File import statement

// EXTERNAL DEPENDENCIES
// import { cookies } from "next/headers";
// import { GoogleAnalytics } from "@next/third-parties/google";

// PROJECT UTILITIES (metadata | context | translates | cookies)
import * as constants from "@/config/constants";
import MetadataSetup, { FontsLoader, GlobalScripts } from "@/config/metadata-setup";
import { ClientProvider } from "@/providers/Client";
import { getDictionary } from "@/app/dictionaries"; // TODO: Keep this here?
import { getServer } from "@/lib/server";
import { klaroConfig } from "@/config/klaro-config"; // cookie configuration
import { KlaroCookieConsent } from "@/config/klaro-cookie-consent"; // cookie handling
import { TranslateProvider } from "@/providers/Translate"; // Provides translation context and hook access for `lang` and `translates`
import { ApiProvider } from "@/providers/API"; // Provides API context and hook access for `api`

// USER PROMPTS (modals | toasts)
import { ForgotPasswordComponent } from "@/components/dialogs/ForgotPassword";
import { PasswordResetComponent } from "@/components/dialogs/PasswordReset";
import { RegisterComponent } from "@/components/dialogs/Register";
import { SignInComponent } from "@/components/dialogs/SignIn";

// INTERNAL RESOURCES
// import { NavSlideTopComponent } from "@/navbars/NavSlideTop";
import { NavSideBurgerComponent } from "@/navbars/Navbar";
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
	userScalable: true,
	minimumScale: 1.0,
	maximumScale: 5.0,
	shrinkToFit: false,
	interactiveWidget: "resizes-visual", // Also supported but less commonly used
};

// Next.js 13's App Router `generateMetadata` object
//
// https://nextjs.org/docs/app/api-reference/functions/generate-metadata
export async function generateMetadata({ params }) {
	const { lang } = await params; // Get language from route params
	const ssr = await getServer(); // Get server-side context

	// Control SEO generation:
	// Set to `true` to skip SEO (for intranet or non-public builds)
	// Set to `false` to enable full SEO metadata fetching (default)
	if (false) {
		// Skip SEO, return only favicon and core metadata
		return await MetadataSetup(lang, {}, { seoMetadata: false });
	}

	const url = `${constants.BACKEND_URL_SERVER}/api/${lang}/${ssr.page}/seo`; // Construct the URL for the SEO metadata API

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

		// Exit early if any request fails
		if (!response.ok) {
			console.error("SEO fetch failed:", response.status, url); // Log the failure
			return await MetadataSetup(lang, {}, { seoMetadata: false }); // Return empty metadata
		}

		// Parse the JSON response and format it
		const rawMetadata = await response.json(); // Raw API response
		const metadataJson = await MetadataSetup(lang, rawMetadata, { seoMetadata: true }); // Structured SEO metadata

		// Inject the processed metadata and append additional headers
		return {
			...metadataJson, // Finalized metadata object
			acceptCH: ["viewport-width"], // Enables responsive layout via Client Hints
		};
	} catch (error) {
		console.error("SEO fetch error:", error); // Log unexpected fetch or parsing errors
		return await MetadataSetup(lang, {}, { seoMetadata: false }); // Return empty metadata on failure
	}
}

// ===============================================
// ## ############################################
// ===============================================

// Main layout function for wrapping page content with children and dynamic parameters
export default async function RootLayout({ children, params }) {
	// Get the language from route params
	const { lang } = (await params) || {};

	// Dynamically gather all font variables from `fonts-loader.js`
	const fontClasses = Object.values(FontsLoader)
		.map((font) => font.variable)
		.join(" ");

	// Fetch translation dictionary based on language
	const translates = await getDictionary(lang);

	return (
		<html lang={lang} className={fontClasses} data-scroll-behavior="smooth">
			{/* <body className="bg_color_white fx_load"> */}
			<body className="bg_color_white">
				{/* USER AND LOCALE CONTEXT (navbar | footer) */}
				<ClientProvider lang={lang} dict={translates}>
					<TranslateProvider lang={lang} translates={translates}>
						<ApiProvider lang={lang}>
							<div className="root_layout container_structure container-fluid row flex-column mx-auto w-100 min-vh-100 position-relative">
								<div className="grid_cont navbar row justify-content-center position-sticky top-0 start-0">
									{/* <NavSideBurgerComponent menu={menuResponseJson} /> */}
									{/* <NavSlideTopComponent /> */}
								</div>

								<div className="grid_cont content">{children}</div>

								{/* <div className="grid_cont footer">
									<FooterComponent />
								</div> */}
							</div>

							{/* PROJECT UTILITIES (scripts | cookies) */}
							<GlobalScripts />
							<KlaroCookieConsent config={klaroConfig} />

							{/* USER PROMPTS (modals | toasts) */}
							<ForgotPasswordComponent lang={lang} />
							<PasswordResetComponent lang={lang} />
							<RegisterComponent lang={lang} />
							<SignInComponent lang={lang} />
						</ApiProvider>
					</TranslateProvider>
				</ClientProvider>
			</body>

			{/* <GoogleAnalytics gaId="G-**********" /> */}
		</html>
	);
}
