// import SeoManager from "@/components/utilities/MetadataSetup"; // File import statement

export default async function SeoManager(currentPage, urlLan) {
	async function fetchMetadata(url, lang) {
		try {
			const response = await fetch(url, {
				method: "GET",
				credentials: "include",
				headers: {
					"Content-Type": "application/json",
					"locale": lang,
				},
			});

			if (!response.ok) {
				throw new Error(`HTTP Error: ${response.status} ${response.statusText}`);
			}

			// Safe JSON parsing:
			const text = await response.text();
			try {
				return JSON.parse(text);
			} catch (e) {
				console.error("Non-JSON response received:", text);
				throw new Error("Response not JSON");
			}
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
			: "Fondazione Assolombarda",

		description: metadata.find((item) => item.code === "description")
			? metadata.find((item) => item.code === "description")[urlLan]
			: "Fondazione Assolombarda",

		openGraph: {
			url: metadata.find((item) => item.code === "og_url")
				? metadata.find((item) => item.code === "og_url")[urlLan]
				: "http://localhost:3000/en",

			type: "website",

			siteName: metadata.find((item) => item.code === "og_site_name")
				? metadata.find((item) => item.code === "og_site_name")[urlLan]
				: "FondazioneAssolombarda",

			title: metadata.find((item) => item.code === "og_title")
				? metadata.find((item) => item.code === "og_title")[urlLan]
				: "FondazioneAssolombarda",

			description: metadata.find((item) => item.code === "og_description")
				? metadata.find((item) => item.code === "og_description")[urlLan]
				: "Fondazione Assolombarda",

			images: metadata.find((item) => item.code === "og_image")
				? metadata.find((item) => item.code === "og_image").image_path
				: "",

			locale: metadata.find((item) => item.code === "og_locale")
				? metadata.find((item) => item.code === "og_locale")[urlLan]
				: "it_IT",
		},
	};
}
