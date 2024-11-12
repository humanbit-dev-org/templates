import path from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

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
			// Enable full source maps in production (NOT RECOMMENDED)
			// config.devtool = 'source-map';
			// Disable source maps in production
			config.devtool = false;
		}

		return config;
	},

	images: {
		remotePatterns: [
			{
				protocol: "https",
				hostname: "api.dicebear.com",
			},
		],
		dangerouslyAllowSVG: true, // Allow SVGs to be imported
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
