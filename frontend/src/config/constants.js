// import * as constants from "@/config/constants"; // File import statement

// ===============================================
// ## ############################################
// ===============================================

export const APP_NAME = process.env.APP_NAME; // Application display name (metadata content)
export const APP_URL = process.env.APP_URL; // Public site origin (absolute Open Graph URLs and canonical link generation)
export const BACKEND_URL_CLIENT = process.env.NEXT_PUBLIC_BACKEND_URL_CLIENT; // API base client URL (build-time config)
export const BACKEND_URL_SERVER = process.env.BACKEND_URL_SERVER; // API base server URL (metadata requests)
export const MEDIA_PATH = `${BACKEND_URL_CLIENT}/storage`; // Full path for uploaded media assets
export const KEY_STRIPE = process.env.NEXT_PUBLIC_KEY_STRIPE; // Stripe API publishable key
export const DEFAULT_LOCALE = "it"; // Default language code (routing, translation, and locale matching)
export const SUPPORTED_LOCALES = [`${DEFAULT_LOCALE}`, "en"]; // Supported language codes (routing, translation, and locale matching)

// Export the public version when used in browser contexts
// export const PUBLIC_<VARIABLE_NAME> = process.env.NEXT_PUBLIC_<VARIABLE_NAME>;
