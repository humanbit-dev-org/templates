// import * as constants from "@/config/constants"; // File import statement

// ===============================================
// ## ############################################
// ===============================================

export const BASE_URL_CLIENT = process.env.NEXT_PUBLIC_BACKEND_URL_CLIENT; // API base client URL (used in CSR fetch calls)
export const BASE_URL_SERVER = process.env.NEXT_PUBLIC_BACKEND_URL_SERVER; // API base server URL (used in SSR fetch calls)
export const MEDIA_PATH = `${BASE_URL_SERVER}/storage/uploads`; // Full path for uploaded media assets
export const SUPPORTED_LOCALES = ["it"]; // Supported language codes (used for routing and translation)
