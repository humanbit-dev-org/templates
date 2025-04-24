// import MetadataSetup from "@/components/utilities/metadata-setup"; // File import statement

export * as FontsLoader from "@/config/fonts-loader";
export * as StylesheetsImporter from "@/config/stylesheets-importer";
import SeoManager from "@/config/seo-manager";
import { FaviconGenerator } from "@/config/favicon-generator";
export { GlobalScripts } from "@/config/global-scripts";

// ===============================================
// ## ############################################
// ===============================================

export default async function MetadataSetup(metadataJson, lang) {
	// Dynamically fetch SEO metadata
	const dynamicSeo = await SeoManager(metadataJson, lang);

	return {
		...FaviconGenerator, // Structure favicon metadata
		...dynamicSeo, // Handle SEO metadata
	};
}
