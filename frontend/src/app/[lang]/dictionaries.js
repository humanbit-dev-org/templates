import "server-only";

const SUPPORTED_LOCALES = ["en", "it"];

// Get the static module path
const getModulePath = (locale) => `lang/${locale}.json`;

export const getDictionary = async (locale) => {
	// Validate the locale
	if (!SUPPORTED_LOCALES.includes(locale)) {
		console.error(`Invalid locale: "${locale}". Falling back to default locale: "en".`);
		locale = "en";
	}

	try {
		const module = await import(getModulePath(locale));
		return module.default;
	} catch (error) {
		console.error(`Error loading dictionary for locale "${locale}":`, error);
		return {};
	}
};
