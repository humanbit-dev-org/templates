// import MetadataSetup from "@/components/utilities/MetadataSetup"; // File import statement

export * as FontsLoader from "@/config/FontsLoader";
export * as StylesheetsImporter from "@/config/StylesheetsImporter";
export { GlobalScripts } from "@/config/GlobalScripts";
import { FaviconGenerator } from "@/config/FaviconGenerator";
import SeoManager from "@/config/SeoManager";

export default async function MetadataSetup(currentPage, urlLan) {
	// Dynamically fetch SEO metadata
	const dynamicSeo = await SeoManager(currentPage, urlLan);

	return {
		...FaviconGenerator, // Structures favicon metadata
		...dynamicSeo, // Handles SEO metadata
	};
}
