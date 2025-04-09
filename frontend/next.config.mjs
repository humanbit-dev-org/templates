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
const nextConfig = {
	sassOptions: {
		includePaths: ["*"], // Resolve all directories for SCSS
	},

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
				poll: 1000, // Enable polling, checks for changes every second
				aggregateTimeout: 300, // Delay rebuild after the first change
				ignored: /node_modules/, // Ignore node_modules to avoid unnecessary recompilations
			};
		} else if (!options.dev && !options.isServer) {
			// Disable source maps in production
			config.devtool = false;
		}

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

if (process.env.IS_TURBO === "true") {
	nextConfig.experimental = {
		turbo: {
			moduleIdStrategy: "deterministic", // Ensures quick rebuilds with hashed numeric IDs
			resolveExtensions: [".tsx", ".ts", ".jsx", ".js", ".json"], // Minimize unnecessary file lookups
			rules: {
				"*.svg": {
					loaders: ["@svgr/webpack"], // Optimize SVG imports
					as: "*.js",
				},
			},
		},
	};
}

export default nextConfig;
