// import SeoManager from "@/components/utilities/metadata-setup"; // File import statement

import * as constants from "@/config/constants"; // Global constants shared across the app

// ===============================================
// ## ############################################
// ===============================================

export default async function SeoManager(metadataJson, lang) {
	return {
		title: metadataJson.find((item) => item.code === "title")
			? metadataJson.find((item) => item.code === "title")[lang]
			: constants.BASE_APP_NAME,

		description: metadataJson.find((item) => item.code === "description")
			? metadataJson.find((item) => item.code === "description")[lang]
			: constants.BASE_APP_NAME,

		openGraph: {
			url: metadataJson.find((item) => item.code === "og_url")
				? metadataJson.find((item) => item.code === "og_url")[lang]
				: constants.BASE_APP_URL,

			type: "website",

			siteName: metadataJson.find((item) => item.code === "og_site_name")
				? metadataJson.find((item) => item.code === "og_site_name")[lang]
				: constants.BASE_APP_NAME,

			title: metadataJson.find((item) => item.code === "og_title")
				? metadataJson.find((item) => item.code === "og_title")[lang]
				: constants.BASE_APP_NAME,

			description: metadataJson.find((item) => item.code === "og_description")
				? metadataJson.find((item) => item.code === "og_description")[lang]
				: constants.BASE_APP_NAME,

			images: metadataJson.find((item) => item.code === "og_image")
				? metadataJson.find((item) => item.code === "og_image").image_path
				: "",

			locale: metadataJson.find((item) => item.code === "og_locale")
				? metadataJson.find((item) => item.code === "og_locale")[lang]
				: "it_IT",
		},
	};
}
