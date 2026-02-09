// import SeoManager from "@/components/utilities/metadata-setup"; // File import statement

import * as constants from "@/config/constants"; // Global constants shared across the app

// ===============================================
// ## Builds structured SEO metadata for Next.js pages
// ===============================================

// Accepts the current language and raw metadata JSON from the backend
export default async function SeoManager(lang, metadataJson) {
	return {
		// Standard HTML metadata
		title: metadataJson.find((item) => item.code === "title")
			? metadataJson.find((item) => item.code === "title")[lang]
			: constants.APP_NAME,

		description: metadataJson.find((item) => item.code === "description")
			? metadataJson.find((item) => item.code === "description")[lang]
			: constants.APP_NAME,

		// Open Graph metadata for social sharing (Facebook, Twitter, etc.)
		openGraph: {
			// Page URL for the Open Graph
			url: metadataJson.find((item) => item.code === "og_url")
				? metadataJson.find((item) => item.code === "og_url")[lang]
				: constants.APP_URL,

			// Default Open Graph type for most websites
			type: "website",

			// Site name for the Open Graph
			siteName: metadataJson.find((item) => item.code === "og_site_name")
				? metadataJson.find((item) => item.code === "og_site_name")[lang]
				: constants.APP_NAME,

			// Page title for the Open Graph
			title: metadataJson.find((item) => item.code === "og_title")
				? metadataJson.find((item) => item.code === "og_title")[lang]
				: constants.APP_NAME,

			// Page description for the Open Graph
			description: metadataJson.find((item) => item.code === "og_description")
				? metadataJson.find((item) => item.code === "og_description")[lang]
				: constants.APP_NAME,

			// Page image path for the Open Graph
			images: metadataJson.find((item) => item.code === "og_image")
				? metadataJson.find((item) => item.code === "og_image").image_path
				: "",

			// Page locale for the Open Graph (default: it_IT)
			locale: metadataJson.find((item) => item.code === "og_locale")
				? metadataJson.find((item) => item.code === "og_locale")[lang]
				: "it_IT",
		},
	};
}
