// import FaviconGenerator from "@/components/utilities/metadata-setup"; // File import statement
//
// "use client";

import * as constants from "@/config/constants"; // Global constants shared across the app

// ===============================================
// ## Provides favicon and web app metadata for Next.js pages
// ===============================================

export const FaviconGenerator = {
	icons: {
		// Standard favicon links
		icon: [
			{ rel: "icon", type: "image/svg+xml", url: "/favicon/favicon.svg" },
			{ rel: "icon", type: "image/png", sizes: "96x96", url: "/favicon/favicon-96x96.png" },
		],

		// Apple touch icon for iOS home screen
		apple: [
			{
				rel: "apple-touch-icon",
				type: "image/png",
				sizes: "180x180",
				url: "/favicon/apple-touch-icon.png",
			},
		],

		// Manifest link for PWA support
		other: [{ rel: "manifest", url: "/favicon/site.webmanifest" }],
	},

	// Apple Web App configuration for iOS install behavior
	appleWebApp: {
		title: constants.APP_NAME, // App name shown on iOS home screen
		capable: true, // Allows launch in standalone mode (the app is capable of being installed on the user's device)
		statusBarStyle: "default", // iOS status bar style in standalone mode (default: black)
	},
};
