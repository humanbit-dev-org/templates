import "server-only";
import fs from "fs";
import path from "path";

const SUPPORTED_LOCALES = ["en", "it"];

// Build the path to the locale file based on the selected language
const getFilePath = (locale) => path.join(process.cwd(), "lang", `${locale}.json`);

export const getDictionary = async (locale) => {
	// Validate the locale
	if (!SUPPORTED_LOCALES.includes(locale)) {
		console.error(`Invalid locale: "${locale}". Falling back to default locale: "en".`);
		locale = "en";
	}

	try {
		// Read the dictionary file for the requested locale
		const filePath = getFilePath(locale);
		const fileContents = fs.readFileSync(filePath, "utf-8");

		// Parse and return the dictionary
		return JSON.parse(fileContents);
	} catch (error) {
		// Log the error and return an empty fallback dictionary
		console.error(`Error loading dictionary for locale "${locale}":`, error);

		return {};
	}
};
