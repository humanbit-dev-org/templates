// import HeadComponent from "components/utilities/HeadComponent"; // to include it in another file
//
// "use client";

export { GlobalScripts } from "components/utilities/GlobalScripts";
import FaviconComponent from "components/utilities/FaviconComponent";
import SeoComponent from "components/utilities/SeoComponent";
export * as StylesheetsImporter from "components/utilities/StylesheetsImporter";

export default async function MetaSetup(currentPage, urlLan) {
	// Dynamically fetch SEO metadata
	const seoMetadata = await SeoComponent(currentPage, urlLan);

	return {
		// viewport:
		// 	"width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no, target-densitydpi=device-dpi, shrink-to-fit=no",
		// icons: {
		// 	...FaviconComponent(), // Favicon logic
		// },
		...FaviconComponent(),
		...seoMetadata, // Include dynamic SEO metadata
	};
}
