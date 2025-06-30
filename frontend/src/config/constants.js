// import * as constants from "@/config/constants"; // File import statement

// ===============================================
// ## ############################################
// ===============================================

export const BASE_APP_NAME = process.env.APP_NAME; // Application display name (metadata content)
export const BASE_APP_URL = process.env.APP_URL; // Public site origin (absolute Open Graph URLs and canonical link generation)
export const BASE_URL_CLIENT = process.env.BACKEND_URL_CLIENT; // API base client URL (build-time config)
export const BASE_URL_SERVER = process.env.BACKEND_URL_SERVER; // API base server URL (metadata requests)
export const MEDIA_PATH = `${BASE_URL_SERVER}/storage/uploads`; // Full path for uploaded media assets
export const SUPPORTED_LOCALES = ["it"]; // Supported language codes (routing, translation, and locale matching)

// Export the public version when used in browser contexts
// export const PUBLIC_<VARIABLE_NAME> = process.env.NEXT_PUBLIC_<VARIABLE_NAME>;
