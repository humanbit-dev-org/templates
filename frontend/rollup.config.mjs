// Bundles all utility scripts into a global IIFE using Rollup's JS API and a virtual entry

// Node.js built-ins
import fs from "fs";
import path from "path";

// Rollup API
import { rollup } from "rollup";

// Rollup plugins
import nodeResolve from "@rollup/plugin-node-resolve";
import terser from "@rollup/plugin-terser";
import virtual from "@rollup/plugin-virtual";

const utilsPath = path.resolve("src/utils"); // Path to source utility scripts
const outputFile = path.resolve("public/js/__bundle.globals.js"); // Destination path for the generated bundle

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

// Build a virtual module (string in memory) containing utilities and attach it to `window` via `Object.assign()`
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

// Log the start of the bundling process
console.log("› Bundling global JS...");

// Define the bundling behavior
const bundle = await rollup({
	input: "virtual-entry", // Virtual entry module name
	plugins: [
		virtual({
			"virtual-entry": getVirtualModuleContent(), // Virtual module content generated in memory
		}),
		nodeResolve(), // Resolve imports from `node_modules` if needed
		terser(), // Minify output for production use
	],
	treeshake: false, // Keep all exports, even if unused (ensures global access)
});

// Generate the final bundled output in memory
const { output } = await bundle.generate({
	format: "iife", // IIFE (Immediately Invoked Function Expression) that attaches exports to window
	inlineDynamicImports: true, // Disable code-splitting to bundle everything into a single file
});

// Read existing bundle from disk (if any)
const existing = fs.existsSync(outputFile) ? fs.readFileSync(outputFile, "utf8") : null;
const generated = output[0]?.code ?? "";

// Write the bundle only if content has changed
if (generated && generated !== existing) {
	fs.writeFileSync(outputFile, generated); // Output file for the final bundle
	console.log("› JS bundle saved!"); // Log the completion of the bundling process
} else {
	console.log("› Skipped: bundle unchanged."); // Log if no changes were made to the bundle
}
