// Uses Rollup's JS API (not CLI) to bundle all utility scripts into a single IIFE
// Writes output to `/public/js/__bundle.globals.js` using an in-memory virtual entry

import fs from "fs";
import path from "path";
import { execSync } from "child_process";
import virtual from "@rollup/plugin-virtual";
import nodeResolve from "@rollup/plugin-node-resolve";
import terser from "@rollup/plugin-terser";
import { rollup } from "rollup";

const utilsPath = path.resolve("src/utils");
const outputFile = path.resolve("public/js/__bundle.globals.js");

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
	const files = getAllJsFiles(utilsPath).sort();
	return files
		.map((file, i) => {
			const varName = `module${i}`;
			const importPath = "./" + path.relative(process.cwd(), file).replace(/\\/g, "/");
			return `import * as ${varName} from "${importPath}";\nObject.assign(window, ${varName});`;
		})
		.join("\n");
}

console.log("✓ Starting build...");

const bundle = await rollup({
	input: "virtual-entry",
	plugins: [virtual({ "virtual-entry": getVirtualModuleContent() }), nodeResolve(), terser()],
	treeshake: false,
});

await bundle.write({
	file: outputFile,
	format: "iife",
	inlineDynamicImports: true,
});

console.log("✔ Bundle written to /public/js/__bundle.globals.js");
