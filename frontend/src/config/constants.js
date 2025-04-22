// Global constants used throughout the app for API access

export const BASE_URL = process.env.NEXT_PUBLIC_BACKEND_URL_SERVER; // API base URL (used in SSR fetch calls)
export const MEDIA_PATH = `${BASE_URL}/storage/uploads`; // Full path for uploaded media assets
export const SUPPORTED_LOCALES = ["it"]; // Supported language codes (used for routing and translation)
