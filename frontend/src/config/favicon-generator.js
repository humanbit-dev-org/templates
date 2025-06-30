// import FaviconGenerator from "@/components/utilities/metadata-setup"; // File import statement
//
// "use client";

import * as constants from "@/config/constants"; // Global constants shared across the app

// ===============================================
// ## ############################################
// ===============================================

export const FaviconGenerator = {
	icons: {
		icon: [
			{ rel: "icon", type: "image/svg+xml", url: "/favicon/icon0.svg" },
			{ rel: "icon", type: "image/png", sizes: "96x96", url: "/favicon/icon1.png" },
		],
		shortcut: "/favicon/favicon.ico",
		apple: [
			{
				rel: "apple-touch-icon",
				type: "image/png",
				sizes: "180x180",
				url: "/favicon/apple-icon.png",
			},
		],
		other: [{ rel: "manifest", url: "/favicon/manifest.json" }],
	},
	appleWebApp: {
		title: constants.BASE_APP_NAME,
	},
};
