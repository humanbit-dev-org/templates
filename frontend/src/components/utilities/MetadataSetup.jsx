// import MetadataSetup from "components/utilities/MetadataSetup"; // File import statement

export * as FontsLoader from "components/utilities/FontsLoader";
export * as StylesheetsImporter from "components/utilities/StylesheetsImporter";
export { GlobalScripts } from "components/utilities/GlobalScripts";
import FaviconGenerator from "components/utilities/FaviconGenerator";
import SeoManager from "components/utilities/SeoManager";

export default async function MetadataSetup(currentPage, urlLan) {
	// Dynamically fetch SEO metadata
	const dynamicSeo = await SeoManager(currentPage, urlLan);

	return {
		...FaviconGenerator(), // Structures favicon metadata
		...dynamicSeo, // Handles SEO metadata
	};
}
