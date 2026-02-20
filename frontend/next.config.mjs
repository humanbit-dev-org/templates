// Node.js path module to work with file and directory paths
import path from "path";
// Utility function to convert an ESM import URL (import.meta.url) into a file system path
import { fileURLToPath } from "url";

// Gives you the absolute file path for the current ES module file
const __filename = fileURLToPath(import.meta.url);
// Extracts the directory name from the absolute path
const __dirname = path.dirname(__filename);

// Parses the environment variable into a URL object so you can access protocol, hostname, etc.
// const url = new URL(process.env.NEXT_PUBLIC_BACKEND_URL_CLIENT);
/**
 * Safely parse backend URL.
 * - During `next build` (Docker): env may be undefined → do nothing
 * - During runtime (`next start`): env exists → parsed
 */
let backendUrl = null;

// Read and validate the backend URL used by the frontend
// ------------------------------------------------------
// - Only runs if the environment variable is defined
// - Converts the string into a URL object for safe parsing
// - Falls back to null if the value is missing or invalid
try {
	const backendUrlValue = process.env.NEXT_PUBLIC_BACKEND_URL_CLIENT || "";

	if (backendUrlValue) {
		backendUrl = new URL(String(backendUrlValue));
	}
} catch {
	backendUrl = null;
}

/** @type {import('next').NextConfig} */
// Webpack configuration (default engine)
const nextConfig = {
	sassOptions: {
		includePaths: ["*"], // Resolve all directories for SCSS
	},

	// Keep pages in memory longer to speed up Fast Refresh
	onDemandEntries: {
		maxInactiveAge: 300_000, // 5 minutes
		pagesBufferLength: 10, // Max pages cached
	},

	// Disable React dev double-rendering
	reactStrictMode: false,

	// Disable Next.js Dev Tools overlay
	devIndicators: false,

	webpack: (config, options) => {
		if (options.dev) {
			// Force SCSS source maps for debugging.
			// If there are performance issues or you don't need debug CSS,
			// use the value "eval-source-map" instead.
			Object.defineProperty(config, "devtool", {
				get() {
					return "source-map";
				},
				set() {},
			});

			// Configure file-watching behavior for faster and optimized rebuilds
			config.watchOptions = {
				poll: false, // File watching method (polling vs. native events)
				aggregateTimeout: 1000, // Time before triggering rebuild
				// Ignore files to avoid unnecessary recompilations
				ignored: [
					"**/.cursor",
					"**/.DS_Store",
					"**/.eslintcache",
					"**/.git",
					"**/.github",
					"**/.idea",
					"**/.next",
					"**/.parcel-cache",
					"**/.pnpm-files",
					"**/.sass-cache",
					"**/.turbo",
					"**/.vscode",
					"**/cache",
					"**/coverage",
					"**/dist",
					"**/node_modules",
					"**/public",
					"**/temp",
					"**/tmp",
					"**/*.map",
					"**/*.min.*",
					"**/*.swp",
				],
			};
		} else if (!options.dev && !options.isServer) {
			// Disable source maps in production
			config.devtool = false;
		}

		// Allow importing SVGs as React components
		config.module.rules.push({
			test: /\.svg$/,
			issuer: /\.[jt]sx?$/,
			use: ["@svgr/webpack"],
		});

		return config;
	},

	images: {
		deviceSizes: [640, 750, 828, 1080, 1200, 1920, 3840, 5000], // Add larger sizes
		imageSizes: [16, 32, 48, 64, 96, 128, 256, 384], // Default thumbnails
		remotePatterns: [
			// ==========================================================
			// Internal sources
			// ----------------------------------------------------------
			...(backendUrl
				? [
						// Development:
						// Use the backend URL as-is (protocol, host, and port)
						{
							protocol: backendUrl.protocol.replace(":", ""), // "http:" → "http"
							hostname: backendUrl.hostname, // "localhost"
							port: backendUrl.port || "", // Empty string if no explicit port
						},
						// Production:
						// Same host, but always served over HTTPS
						{
							protocol: "https",
							hostname: backendUrl.hostname,
						},
					]
				: []),
			// ==========================================================
			// External sources
			// ----------------------------------------------------------
			{
				protocol: "https",
				hostname: "api.dicebear.com",
			},
			{
				protocol: "https",
				hostname: "picsum.photos",
			},
		],
		dangerouslyAllowSVG: true, // Allow SVGs to be imported
	},

	async rewrites() {
		return [
			{
				source: "/lang/:lang",
				destination: "/frontend/lang/:lang", // Serve from the `/frontend/lang` folder
			},
		];
	},

	async headers() {
		const enabled = String(process.env.NEXT_IFRAME_ENABLE || "").toLowerCase() === "true";

		const allowed = String(process.env.NEXT_ALLOWED_ORIGINS || "")
			.split(",")
			.map((s) => s.trim())
			.filter(Boolean);

		const list = allowed
			.map((s) => String(s).trim())
			.filter(Boolean)
			.filter((s) => s.startsWith("http://") || s.startsWith("https://"));

		const csp = enabled ? `frame-ancestors 'self' ${list.join(" ")};` : `frame-ancestors 'none';`;

		return [
			{
				source: "/:path*",
				headers: [
					{ key: "Content-Security-Policy", value: csp },
					...(enabled ? [] : [{ key: "X-Frame-Options", value: "DENY" }]),
				],
			},
		];
	},
};

// Turbopack configuration (next-gen engine)
nextConfig.turbopack = {
	// Match file extensions in import paths
	// prettier-ignore
	resolveExtensions: [
		".mdx",
		".md",
		".tsx",
		".ts",
		".jsx",
		".js",
		".mjs",
		".cjs",
		".json",
		".scss",
		".css",
	],

	// Define custom loaders per file type
	rules: {
		"*.svg": {
			loaders: ["@svgr/webpack"], // Support importing SVGs as React components
			as: "*.js", // Fallback: treat loader output as JS
		},
	},
};

export default nextConfig;
