// Server-Side Rendering (React generates HTML before hydration)

// GENERAL ENTRY POINT
//
// File import statements:
// import RootLayout from "@/layout/root";

// EXTERNAL DEPENDENCIES
import { cookies } from "next/headers";

// PROJECT UTILITIES (metadata | context | translates | cookies)
import MetadataSetup, { FontsLoader, GlobalScripts } from "@/config/MetadataSetup";
// import { AuthHelper } from "@/config/AuthHelper";
import { GlobalProvider } from "@/providers/Globals";
import { klaroConfig } from "public/js/klaroConfig"; // cookie configuration
import { KlaroCookieConsent } from "@/config/KlaroCookieConsent"; // cookie handling

// USER PROMPTS (modals | toasts)
// import { ForgotPasswordComponent } from "@/components/dialogs/ForgotPasswordComponent";
// import { PasswordResetComponent } from "@/components/dialogs/PasswordResetComponent";
// import { RegisterComponent } from "@/components/dialogs/RegisterComponent";
// import { SignInComponent } from "@/components/dialogs/SignInComponent";

// INTERNAL RESOURCES
import { NavSideBurgerComponent } from "@/components/navbars/NavSideBurger";
import "./layout.scss";

// ===============================================
// ## ############################################
// ===============================================

// Backend base URL
const baseUrl = process.env.NEXT_PUBLIC_ASSETS_URL;

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
	const baseUrl = process.env.NEXT_PUBLIC_ASSETS_URL;
	const url = `${baseUrl}/api/${lang}/seo`;

	// Pass the fetched data to MetadataSetup
	return await MetadataSetup(url, lang);

	// Call MetadataSetup
	// const metadataSetup = await MetadataSetup(data.pageSlug || currentPage, lang);

	// return {
	// 	...metadataSetup,
	// };
}

// ===============================================
// ## ############################################
// ===============================================

// Fetches user data from the Laravel API with session credentials
async function fetchUser(laravelSession) {
	try {
		const fetchPath = baseUrl + "/api/user";

		const userResponse = await fetch(fetchPath, {
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

// Main layout function for wrapping page content with children and dynamic parameters
export default async function RootLayout({ children, params }) {
	// Get the language from params (likely from the URL)
	const { lang } = (await params) || {};

	// Get cookies and extract the Laravel session ID
	const cookiesStore = await cookies();
	const laravelSession = cookiesStore.get("laravel_session")?.value;

	// Dynamically gather all font variables from the font loader
	const fontClasses = Object.values(FontsLoader)
		.map((font) => font.variable)
		.join(" ");

	// Load the language dictionary for i18n
	const dict = await getDictionary(lang);

	// Fetch user data using the Laravel session
	const user = await fetchUser(laravelSession);

	// Fetch menu data from the API
	const apiUrl = `${baseUrl}/api/pages`;
	const response = await fetch(apiUrl, {
		method: "GET",
		credentials: "include",
		headers: {
			"Content-Type": "application/json",
		},
	});
	const menu = await response.json();

	return (
		<html lang={lang} className={fontClasses}>
			<body className="bg_color_white fx_load">
				<GlobalProvider>
					<div className="root_layout container_structure d-flex flex-wrap container-fluid position-relative position-xl-static">
						<div className="grid_cont navbar row justify-content-center position-sticky position-xl-relative top-0 order-0 order-xl-2">
							{/* <NavSideBurgerComponent menu={menu} /> */}
						</div>

						{/* USER AND LOCALE CONTEXT (navbar | footer) */}
						<AuthHelper lang={lang} dict={dict} user={user}>
							{children}
						</AuthHelper>
					</div>

					{/* PROJECT UTILITIES (scripts | cookies) */}
					<GlobalScripts />
					<KlaroCookieConsent config={klaroConfig} />

					{/* USER PROMPTS (modals | toasts) */}
					<ForgotPasswordComponent lang={lang} />
					<PasswordResetComponent lang={lang} />
					<RegisterComponent lang={lang} />
					<SignInComponent lang={ lang } />
				</GlobalProvider>
			</body>
		</html>
	);
}