// `src/app/[lang]/layout` -> GENERAL ENTRY POINT

// EXTERNAL DEPENDENCIES
import { cookies } from "next/headers";

// PROJECT UTILITIES (metadata | context | translates | cookies)
import MetadataSetup, { FontsLoader, GlobalScripts } from "components/utilities/MetadataSetup";
import { AuthHelper } from "components/utilities/AuthHelper";
import { getDictionary } from "app/dictionaries";
import { klaroConfig } from "public/js/klaroConfig"; // configuration
import { KlaroCookieConsent } from "components/utilities/KlaroCookieConsent"; // handling

// USER PROMPTS (modals | toasts)
import { ForgotPasswordComponent } from "components/dialogs/ForgotPasswordComponent";
import { PasswordResetComponent } from "components/dialogs/PasswordResetComponent";
import { RegisterComponent } from "components/dialogs/RegisterComponent";
import { SignInComponent } from "components/dialogs/SignInComponent";

// Backend base URL
const baseUrl = process.env.NEXT_PUBLIC_BACKEND_URL_SERVER;

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
	const slug = params?.slug || []; // Handle undefined or nested segments
	const currentPage = slug.length > 0 ? slug.join("/") : "index"; // Combine slug if it's an array

	// Construct absolute URL
	const baseUrl = process.env.NEXT_PUBLIC_BACKEND_URL_SERVER; // Ensure this env variable is set
	const apiUrl = `${baseUrl}/api/${lang}/seo`;

	// Fetch metadata from the API
	const response = await fetch(apiUrl, {
		method: "GET",
		credentials: "include",
		headers: {
			"Content-Type": "application/json",
			"locale": lang,
		},
	});

	// Handle errors
	if (!response.ok) {
		throw new Error(`Failed to fetch metadata: ${response.statusText}`);
	}

	// Parse the response
	const data = await response.json();

	// Pass the fetched data to MetadataSetup
	return await MetadataSetup(data.pageSlug || currentPage, lang);

	// Call MetadataSetup
	// const metadataSetup = await MetadataSetup(data.pageSlug || currentPage, lang);

	// return {
	// 	...metadataSetup,
	// };
}

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
	// Dynamically gather all font variables
	const fontClasses = Object.values(FontsLoader)
		.map((font) => font.variable)
		.join(" ");

	const { lang } = (await params) || {};

	// Fetch the dictionary
	const dict = await getDictionary(lang);

	const cookiesStore = await cookies();
	const laravelSession = cookiesStore.get("laravel_session")?.value;

	const user = await fetchUser(laravelSession);
	// console.log(user);

	return (
		<html lang={lang} className={fontClasses}>
			<body className="bg_color_first fx_load">
				{/* <div className="preloader" id="preloader">
					<div className="loader">
						<hr className="hr_preloader" />
					</div>
				</div> */}

				<div className="container_humanbit_overflow scrollbar_spacing" id="page">
					{/* <button id="add-webapp" className="btnBgP text-center font-weight-bold mb-4 py-1 px-3 d-lg-none" style="top: 15%; position: fixed;">Scarica l'App!</button> */}

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
				<SignInComponent lang={lang} />
			</body>
		</html>
	);
}
