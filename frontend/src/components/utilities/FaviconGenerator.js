// import FaviconGenerator from "components/utilities/MetadataSetup"; // File import statement
//
// "use client";

export default function FaviconGenerator() {
	return {
		icons: {
			icon: "/favicon/favicon-32x32.png",
			shortcut: "/favicon/favicon.ico",
			apple: [
				{ rel: "apple-touch-icon", type: "image/png", sizes: "57x57", url: "/favicon/apple-icon-57x57.png" },
				{ rel: "apple-touch-icon", type: "image/png", sizes: "60x60", url: "/favicon/apple-icon-60x60.png" },
				{ rel: "apple-touch-icon", type: "image/png", sizes: "72x72", url: "/favicon/apple-icon-72x72.png" },
				{ rel: "apple-touch-icon", type: "image/png", sizes: "76x76", url: "/favicon/apple-icon-76x76.png" },
				{ rel: "apple-touch-icon", type: "image/png", sizes: "114x114", url: "/favicon/apple-icon-114x114.png" },
				{ rel: "apple-touch-icon", type: "image/png", sizes: "120x120", url: "/favicon/apple-icon-120x120.png" },
				{ rel: "apple-touch-icon", type: "image/png", sizes: "144x144", url: "/favicon/apple-icon-144x144.png" },
				{ rel: "apple-touch-icon", type: "image/png", sizes: "152x152", url: "/favicon/apple-icon-152x152.png" },
				{ rel: "apple-touch-icon", type: "image/png", sizes: "180x180", url: "/favicon/apple-icon-180x180.png" },
			],
			other: [
				{ rel: "icon", type: "image/png", sizes: "144x144", url: "/favicon/android-icon-144x144.png" },
				{ rel: "icon", type: "image/png", sizes: "192x192", url: "/favicon/android-icon-192x192.png" },
				{ rel: "icon", type: "image/png", sizes: "96x96", url: "/favicon/favicon-96x96.png" },
				{ rel: "icon", type: "image/png", sizes: "16x16", url: "/favicon/favicon-16x16.png" },
				{ rel: "manifest", url: "/favicon/manifest.json" },
			],
		},
		msapplication: {
			"msapplication-TileColor": "#ffffff",
		},
	};
}
