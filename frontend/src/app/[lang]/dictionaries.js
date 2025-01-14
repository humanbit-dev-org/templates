import "server-only";

const dictionaries = {
	en: () => import("lang/en.json").then((module) => module.default),
	it: () => import("lang/it.json").then((module) => module.default),
};

export const getDictionary = async (locale) => {
	// Validate the locale
	if (!dictionaries[locale]) {
		console.error(`Invalid locale: "${locale}". Falling back to default locale: "en".`);
		locale = "en"; // Fallback to the default locale
	}

	// Return the appropriate dictionary
	try {
		return await dictionaries[locale]();
	} catch (error) {
		console.error(`Error loading dictionary for locale "${locale}":`, error);
		return {}; // Return an empty dictionary as a final fallback
	}
};
