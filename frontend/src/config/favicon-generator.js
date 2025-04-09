// import FaviconGenerator from "@/components/utilities/metadata-setup"; // File import statement
//
// "use client";

export const FaviconGenerator = {
	icons: {
		icon: "/favicon/icon1.png",
		shortcut: "/favicon/favicon.ico",
		apple: [{ rel: "apple-touch-icon", type: "image/png", sizes: "180x180", url: "/favicon/apple-icon.png" }],
		other: [
			{ rel: "icon", type: "image/svg+xml", url: "/favicon/icon0.svg" },
			{ rel: "manifest", url: "/favicon/manifest.json" },
		],
	},
	msapplication: {
		"msapplication-TileColor": "#ffffff",
	},
};
