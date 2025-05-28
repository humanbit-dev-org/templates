// import { getDeviceInfo } from "@/lib/server/device-info"; // File import statement

import { headers } from "next/headers";

// ===============================================
// ## ############################################
// ===============================================

// Extracts device information from the current request on the server
export async function getDeviceInfo() {
	// Get the `user-agent` string from the request headers
	const ua = (await headers()).get("user-agent") || "";

	// Bot detection
	const isBot = /bot|crawl|spider|slurp/i.test(ua); // Common bots and web crawlers

	// Device type flags
	const isPhone = /iphone|android.*mobile|mobile/i.test(ua); // Smartphone or handheld devices
	const isTablet =
		/ipad/i.test(ua) || // Apple tablets
		(/android/i.test(ua) && !/mobile/i.test(ua)) || // Android tablets (exclude smartphones)
		/windows nt.*touch/i.test(ua); // Microsoft tablets
	const isMobile = isPhone || isTablet; // Match both smartphone and tablet devices
	const isIPad = /ipad/i.test(ua); // All iPad variants (Apple tablets)
	const isSurface = /windows nt.*touch/i.test(ua); // All Surface variants (Microsoft tablets)
	const isDesktop = !isMobile && !isBot; // Non-mobile, non-bot device (likely desktop or laptop)

	// Operating system flags
	const isWindows = /windows nt/i.test(ua); // Windows OS (Microsoft PC and Surface line)
	const isMac = /macintosh/i.test(ua); // macOS (Apple PC)
	const isAndroid = /android/i.test(ua); // Android OS (all devices)
	const isiOS = /iphone|ipad/i.test(ua); // iOS (iPhone and iPad)

	// Browser flags
	const isChrome = /chrome|crios|crmo|edg|edge|brave|opr/i.test(ua) && !/safari/i.test(ua); // Chrome and Chromium-based browsers (exclude Safari)
	const isSafari = /safari/i.test(ua) && !/chrome|chromium/i.test(ua); // Safari browser (exclude Chrome and Chromium-based)
	const isFirefox = /firefox/i.test(ua); // Firefox browser

	// Return all parsed values as a reusable object
	return {
		ua, // Full `user-agent` string
		isPhone, // Smartphone
		isTablet, // Tablet
		isMobile, // Smartphone & tablet
		isIPad, // Apple tablet
		isSurface, // Microsoft tablet
		isDesktop, // Desktop & laptop
		isWindows, // Microsoft PC
		isMac, // Apple PC
		isAndroid, // Android OS
		isiOS, // iPhone and iPad
		isChrome, // Chrome, Edge, Brave, Opera, etc.
		isSafari, // Safari
		isFirefox, // Firefox
		isBot, // Bots & crawlers
	};
}
