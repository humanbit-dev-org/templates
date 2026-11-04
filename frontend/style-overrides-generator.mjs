import fs from "node:fs";
import path from "node:path";
import { loadEnvFile } from "node:process";

/**
 * Generate a Sass module that overrides default variables using environment values.
 *
 * Behavior:
 * - Reads environment variables prefixed with `STYLE_OVERRIDE_`
 * - Converts them into Sass variables (kebab-case)
 * - Forwards `_variables.scss` with overrides when present
 * - Falls back to plain forward when no overrides exist
 *
 * Pattern:
 * - JS = optional overrides (env-driven)
 * - SCSS (`_variables.scss`) = defaults via `!default`
 *
 * Result:
 * - Clean separation of concerns
 * - No duplication or runtime conditionals in Sass
 */
export function generateStyleOverrides() {
	loadEnvFile("/tmp/.env.styles");

	const tenant = process.env.NEXT_PUBLIC_TENANT_ID;
	const dir = path.join(process.cwd(), "src/assets/scss/controllers");
	const file = path.join(dir, "_variables.generated.scss");

	fs.mkdirSync(dir, { recursive: true });

	// Extract SCSS-related env variables and normalize names
	const entries = Object.entries(process.env)
		.filter(([key]) => key.startsWith("STYLE_OVERRIDE_"))
		.map(([key, value]) => {
			const tenantValue = JSON.parse(value)[tenant];

			if (!tenantValue) return null;

			const name = key.replace("STYLE_OVERRIDE_", "").toLowerCase().replaceAll("_", "-");

			return `$${name}: ${tenantValue}`;
		})
		.filter(Boolean);

	let content;

	if (entries.length > 0) {
		// Generate override layer via `@forward ... with (...)`
		content = `
@forward "variables" with (
${entries.map((entry) => `	${entry}`).join(",\n")}
);
		`;
	} else {
		// No overrides → forward defaults only
		content = `
@forward "variables";
		`;
	}

	fs.writeFileSync(file, content.trim() + "\n");
}
