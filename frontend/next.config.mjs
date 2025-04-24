// Node.js path module to work with file and directory paths
import path from "path";
// Utility function to convert an ESM import URL (import.meta.url) into a file system path
import { fileURLToPath } from "url";

// Gives you the absolute file path for the current ES module file
const __filename = fileURLToPath(import.meta.url);
// Extracts the directory name from the absolute path
const __dirname = path.dirname(__filename);

// Parses the environment variable into a URL object so you can access protocol, hostname, etc.
const url = new URL(process.env.NEXT_PUBLIC_BACKEND_URL_CLIENT);

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
				poll: false, // Use native file system events (polling causes rebuild loops in this setup)
				aggregateTimeout: 1000, // Wait longer before triggering rebuild
				// Ignore files to avoid unnecessary recompilations
				ignored: [
					"**/.DS_Store",
					"**/.git/**",
					"**/.next/**",
					"**/cache/**",
					"**/node_modules/**",
					"**/*.min.css",
					"**/*.min.js",
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
			// Development
			{
				protocol: url.protocol.replace(":", ""),
				hostname: url.hostname,
				port: url.port,
			},
			// Production
			{
				protocol: "https",
				hostname: process.env.NEXT_PUBLIC_BACKEND_URL_CLIENT,
			},
			// External
			{
				protocol: "https",
				hostname: "api.dicebear.com",
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
};

// Turbopack configuration (next-gen engine)
nextConfig.turbopack = {
	// Match file extensions in import paths
	resolveExtensions: [".tsx", ".ts", ".jsx", ".js", ".json"],

	// Define custom loaders per file type
	rules: {
		"*.svg": {
			loaders: ["@svgr/webpack"], // Support importing SVGs as React components
			as: "*.js", // Fallback: treat loader output as JS
		},
	},
};

export default nextConfig;
