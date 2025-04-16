// This config is no longer used in the current setup, as builds are now handled by `manage-globals.mjs`
// Retained for manual use or fallback purposes

// Bundles all utility scripts into a single file at `/public/js/__bundle.globals.js` using an in-memory virtual entry
// The output is a single IIFE that attaches all exports to `window`

// Node.js built-ins
import fs from "fs";
import path from "path";

// Rollup plugins
import nodeResolve from "@rollup/plugin-node-resolve";
import terser from "@rollup/plugin-terser";
import virtual from "@rollup/plugin-virtual";

const utilsPath = path.resolve("src/utils"); // Absolute path to the folder containing utility scripts

// Recursively collect `.js` files from `src/utils` (excluding `manual`)
function getAllJsFiles(dir) {
	const entries = fs.readdirSync(dir, { withFileTypes: true });

	return entries.flatMap((entry) => {
		const fullPath = path.join(dir, entry.name);
		if (entry.name === "manual") return [];
		if (entry.isDirectory()) return getAllJsFiles(fullPath);
		if (entry.isFile() && entry.name.endsWith(".js")) return [fullPath];
		return [];
	});
}

// Build a virtual module (no temp files) that imports everything and attaches to `window` via `Object.assign()`
function getVirtualModuleContent() {
	const files = getAllJsFiles(utilsPath).sort(); // `sort()` ensures consistent order

	return files
		.map((file, i) => {
			const varName = `module${i}`;
			const importPath = "./" + path.relative(process.cwd(), file).replace(/\\/g, "/");
			return `import * as ${varName} from "${importPath}";\nObject.assign(window, ${varName});`;
		})
		.join("\n");
}

// Main Rollup export used to define the bundling behavior
export default {
	input: "virtual-entry", // Virtual entry module
	output: {
		file: "public/js/__bundle.globals.js", // Output file for the final bundle
		format: "iife", // Immediately-invoked function expression — attaches exports to window
		inlineDynamicImports: true, // Prevents code-splitting — forces all content into one file
	},
	plugins: [
		virtual({
			"virtual-entry": getVirtualModuleContent(), // In-memory module content
		}),
		nodeResolve(), // Resolve imports from node_modules if needed
		terser(), // Minify output for production use
	],
	treeshake: false, // Keep all exports even if unused — ensures globals aren't stripped out
};
