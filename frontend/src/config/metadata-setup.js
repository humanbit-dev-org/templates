// import MetadataSetup from "@/components/utilities/metadata-setup"; // File import statement

export * as FontsLoader from "@/config/fonts-loader";
export * as StylesheetsImporter from "@/config/stylesheets-importer";
import SeoManager from "@/config/seo-manager";
import { FaviconGenerator } from "@/config/favicon-generator";
export { GlobalScripts } from "@/config/global-scripts";

// ===============================================
// ## ############################################
// ===============================================

export default async function MetadataSetup(lang, metadataJson, { seoMetadata = true } = {}) {
	// Conditionally fetch SEO metadata
	const dynamicSeo = seoMetadata ? await SeoManager(lang, metadataJson) : {};

	return {
		...dynamicSeo, // Handle SEO metadata
		...FaviconGenerator, // Structure favicon metadata
	};
}
