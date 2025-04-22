// Server-Side Rendering (React generates HTML before hydration)

// GENERAL ENTRY POINT
//
// File import statements:
// import RootLayout from "@/layout/root";

// EXTERNAL DEPENDENCIES
import { cookies } from "next/headers";

// PROJECT UTILITIES (metadata | context | translates | cookies)
import * as constants from "@/config/constants";
import MetadataSetup, { FontsLoader, GlobalScripts } from "@/config/metadata-setup";
// import { AuthProvider } from "@/providers/Auth"; // TODO: Keep this here?
import { getDictionary } from "@/app/dictionaries"; // TODO: Keep this here?
import { getServer } from "@/lib/server";
import { ClientProvider } from "@/providers/Client";
import { klaroConfig } from "@/config/klaro-config"; // cookie configuration
import { KlaroCookieConsent } from "@/config/klaro-cookie-consent"; // cookie handling

// USER PROMPTS (modals | toasts)
import { ForgotPasswordComponent } from "@/components/dialogs/ForgotPasswordComponent";
import { PasswordResetComponent } from "@/components/dialogs/PasswordResetComponent";
import { RegisterComponent } from "@/components/dialogs/RegisterComponent";
import { SignInComponent } from "@/components/dialogs/SignInComponent";

// INTERNAL RESOURCES
import { NavSideBurgerComponent } from "@/navbars/NavSideBurger";
import "./globals.scss";
import "./layout.scss";

import { BoilerplateComponent } from "@/components/blocks/Boilerplate";

// ===============================================
// ## ############################################
// ===============================================

// Backend base URL
const BASE_URL = process.env.NEXT_PUBLIC_BACKEND_URL_SERVER;

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
	const { lang } = await params;
	const ssr = await getServer();
	const url = `${BASE_URL}/api/${lang}/${ssr.page}/seo`;

	const metadataResponse = await fetch(url, {
		method: "GET",
		credentials: "include",
		headers: {
			"Content-Type": "application/json",
			"locale": lang,
		},
	});

	const metadataJson = await metadataResponse.json();
	// Pass the fetched data to MetadataSetup
	return await MetadataSetup(metadataJson, lang);

	// Call MetadataSetup
	// const metadataSetup = await MetadataSetup(data.pageSlug || currentPage, lang);

	// return {
	// 	...metadataSetup,
	// };
}

// Fetches user data from the Laravel API with session credentials
async function fetchUser(laravelSession) {
	try {
		const userResponse = await fetch(`${BASE_URL}/api/user`, {
			method: "GET",
			credentials: "include",
			headers: {
				"Accept": "application/json",
				"Referer": process.env.APP_URL,
				"X-Requested-With": "XMLHttpRequest",
				"Content-Type": "application/json",
				"cookie": "laravel_session=" + laravelSession,
			},
		});

		if (!userResponse.ok) {
			if (userResponse.status === 401) {
				return undefined;
			}
			console.error(`User fetch failed with status: ${userResponse.statusText}`);
		}

		return await userResponse.json();
	} catch (error) {
		console.error("Error fetching user:", error);

		return undefined;
	}
}

// ===============================================
// ## ############################################
// ===============================================

// Main layout function for wrapping page content with children and dynamic parameters
export default async function RootLayout({ children, params }) {
	// Get the language from route params
	const { lang } = (await params) || {};

	// Get structured path info from the current URL
	const ssr = await getServer();

	// Get cookies and extract the Laravel session ID
	const cookiesStore = await cookies();
	const laravelSession = cookiesStore.get("laravel_session")?.value;

	// Dynamically gather all font variables from the font loader
	const fontClasses = Object.values(FontsLoader)
		.map((font) => font.variable)
		.join(" ");

	// Fetch translation dictionary based on language
	const translates = await getDictionary(lang);

	// Fetch user data using the Laravel session
	const userResponseJson = await fetchUser(laravelSession);

	// Fetch data from the API with language header
	// const menuResponse = await fetch(`${BASE_URL}/api/pages`, {
	// 	method: "GET",
	// 	credentials: "include",
	// 	headers: {
	// 		"Content-Type": "application/json",
	// 	},
	// });
	// const menuResponseJson = await menuResponse.json();

	return (
		<html lang={lang} className={fontClasses}>
			{/* <body className="bg_color_white fx_load"> */}
			<body className="bg_color_white">
				<ClientProvider>
					<div className="root_layout container_structure container-fluid">
						<div className="grid_cont navbar row justify-content-center position-sticky">
							{/* <NavSideBurgerComponent menu={menuResponseJson} /> */}
						</div>

						{ssr.page === "home" && (
							<>
								<div>You're on the home SSR.</div>
								<BoilerplateComponent />
							</>
						)}
						{ssr.page === "components" && (
							<>
								<div>You're on the components SSR.</div>
								<BoilerplateComponent />
							</>
						)}

						{/* USER AND LOCALE CONTEXT (navbar | footer) */}
						{/* <AuthProvider lang={lang} translates={translates} user={userResponseJson}> */}
						{children}
						{/* </AuthProvider> */}
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
