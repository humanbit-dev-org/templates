// Auto-generates `__auto.index.js`:
// - Scans `/src/utils` for `.js` files (excluding `/manual` and `__auto.index.js` itself)
// - Imports each file and assigns its exports to `window` via `Object.assign()`

import { readdirSync, writeFileSync } from "fs";
import { resolve, relative } from "path";

const utilsPath = resolve("src/utils");
const indexPath = resolve(utilsPath, "__auto.index.js");

/**
 * Recursively retrieves all `.js` files from a directory,
 * excluding `/manual` and `__auto.index.js` itself.
 */
function getAllJsFiles(dir) {
	const entries = readdirSync(dir, { withFileTypes: true });

	return entries.flatMap((entry) => {
		const fullPath = resolve(dir, entry.name);

		// Skip the generated file and manually excluded folder
		if (entry.name === "__auto.index.js" || entry.name === "manual") return [];

		if (entry.isDirectory()) return getAllJsFiles(fullPath);
		if (entry.name.endsWith(".js")) return [fullPath];

		return [];
	});
}

const files = getAllJsFiles(utilsPath);

/**
 * For each file:
 * - Generate a unique module name (e.g. `module0`)
 * - Import it and assign its exports to `window`
 */
const imports = files.map((file, i) => {
	const varName = `module${i}`;
	const importPath = "./" + relative(utilsPath, file).replace(/\\/g, "/");
	return `import * as ${varName} from "${importPath}";\nObject.assign(window, ${varName});`;
});

writeFileSync(indexPath, imports.join("\n") + "\n");
