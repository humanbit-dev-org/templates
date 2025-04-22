// import SeoManager from "@/components/utilities/metadata-setup"; // File import statement

export default async function SeoManager(currentPage, urlLan) {
	async function fetchMetadata(url, lang) {
		try {
			// Send request with locale header
			const response = await fetch(url, {
				method: "GET",
				credentials: "include",
				headers: {
					"Content-Type": "application/json",
					"locale": lang,
				},
			});

			// Throw if response is not OK
			if (!response.ok) {
				throw new Error(`HTTP Error: ${response.status} ${response.statusText}`);
			}

			// Read and parse response safely
			const text = await response.text();
			console.log(text);

			try {
				return JSON.parse(text);
			} catch {
				console.error("Non-JSON response received:", text);

				throw new Error("Response not JSON");
			}
		} catch (error) {
			// Log and rethrow fetch errors
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
			: "Humanbit",

		description: metadata.find((item) => item.code === "description")
			? metadata.find((item) => item.code === "description")[urlLan]
			: "Humanbit",

		openGraph: {
			url: metadata.find((item) => item.code === "og_url")
				? metadata.find((item) => item.code === "og_url")[urlLan]
				: "http://localhost:3000/en",

			type: "website",

			siteName: metadata.find((item) => item.code === "og_site_name")
				? metadata.find((item) => item.code === "og_site_name")[urlLan]
				: "Humanbit",

			title: metadata.find((item) => item.code === "og_title")
				? metadata.find((item) => item.code === "og_title")[urlLan]
				: "Humanbit",

			description: metadata.find((item) => item.code === "og_description")
				? metadata.find((item) => item.code === "og_description")[urlLan]
				: "Humanbit",

			images: metadata.find((item) => item.code === "og_image")
				? metadata.find((item) => item.code === "og_image").image_path
				: "",

			locale: metadata.find((item) => item.code === "og_locale")
				? metadata.find((item) => item.code === "og_locale")[urlLan]
				: "it_IT",
		},
	};
}
