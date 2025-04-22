// import SeoManager from "@/components/utilities/metadata-setup"; // File import statement

export default async function SeoManager(metadataJson, lang) {
	return {
		title: metadataJson.find((item) => item.code === "title")
			? metadataJson.find((item) => item.code === "title")[lang]
			: "Humanbit",

		description: metadataJson.find((item) => item.code === "description")
			? metadataJson.find((item) => item.code === "description")[lang]
			: "Humanbit",

		openGraph: {
			url: metadataJson.find((item) => item.code === "og_url")
				? metadataJson.find((item) => item.code === "og_url")[lang]
				: "http://localhost:3000/en",

			type: "website",

			siteName: metadataJson.find((item) => item.code === "og_site_name")
				? metadataJson.find((item) => item.code === "og_site_name")[lang]
				: "Humanbit",

			title: metadataJson.find((item) => item.code === "og_title")
				? metadataJson.find((item) => item.code === "og_title")[lang]
				: "Humanbit",

			description: metadataJson.find((item) => item.code === "og_description")
				? metadataJson.find((item) => item.code === "og_description")[lang]
				: "Humanbit",

			images: metadataJson.find((item) => item.code === "og_image")
				? metadataJson.find((item) => item.code === "og_image").image_path
				: "",

			locale: metadataJson.find((item) => item.code === "og_locale")
				? metadataJson.find((item) => item.code === "og_locale")[lang]
				: "it_IT",
		},
	};
}
