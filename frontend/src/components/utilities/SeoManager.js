// import SeoManager from "components/utilities/MetadataSetup"; // File import statement

export default async function SeoManager(currentPage, urlLan) {
	const baseUrl = process.env.NEXT_PUBLIC_BACKEND_URL_SERVER;

	async function fetchMetadata(page, lang) {
		try {
			const response = await fetch(`${baseUrl}/api/${lang}/seo`, {
				method: "GET",
				credentials: "include",
				headers: {
					"Content-Type": "application/json",
					"locale": lang,
				},
			});

			if (!response.ok) {
				if (response.status !== 409) {
					throw new Error(`Error: ${response.statusText}`);
				}

				return null;
			}

			return await response.json();
		} catch (error) {
			console.error("Metadata fetch error:", error);
			throw error;
		}
	}

	// Fetch metadata dynamically and return the complete template
	const metadata = await fetchMetadata(currentPage, urlLan);
	// console.log(metadata);

	return {
		title: metadata.find((item) => item.code === "title")
			? metadata.find((item) => item.code === "title")[urlLan]
			: "Default Title",

		description: metadata.find((item) => item.code === "description")
			? metadata.find((item) => item.code === "description")[urlLan]
			: "Default Description",

		openGraph: {
			url: metadata.find((item) => item.code === "og_url")
				? metadata.find((item) => item.code === "og_url")[urlLan]
				: "http://localhost:3000/en",

			type: "website",

			siteName: metadata.find((item) => item.code === "og_site_name")
				? metadata.find((item) => item.code === "og_site_name")[urlLan]
				: "AllTogetherPay",

			title: metadata.find((item) => item.code === "og_title")
				? metadata.find((item) => item.code === "og_title")[urlLan]
				: "AllTogetherPay",

			description: metadata.find((item) => item.code === "og_description")
				? metadata.find((item) => item.code === "og_description")[urlLan]
				: "Default Description",

			images: metadata.find((item) => item.code === "og_image")
				? metadata.find((item) => item.code === "og_image").image_path
				: "",

			locale: metadata.find((item) => item.code === "og_locale")
				? metadata.find((item) => item.code === "og_locale")[urlLan]
				: "en_US",
		},
	};
}
