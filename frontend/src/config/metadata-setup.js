// import MetadataSetup from "@/components/utilities/metadata-setup"; // File import statement

export * as FontsLoader from "@/config/fonts-loader";
export * as StylesheetsImporter from "@/config/stylesheets-importer";
export { GlobalScripts } from "@/config/global-scripts";
import { FaviconGenerator } from "@/config/favicon-generator";
import SeoManager from "@/config/seo-manager";

export default async function MetadataSetup(currentPage, urlLan) {
	// Dynamically fetch SEO metadata
	const dynamicSeo = await SeoManager(currentPage, urlLan);

	return {
		...FaviconGenerator, // Structures favicon metadata
		...dynamicSeo, // Handles SEO metadata
	};
}
