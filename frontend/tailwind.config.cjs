/** @type {import('tailwindcss').Config} */
module.exports = {
	prefix: "tw_", // Prefixes Tailwind classes with `tw_` to avoid naming conflicts
	content: ["./src/**/*.{js,ts,jsx,tsx}"], // Targets all `.js`, `.ts`, `.jsx`, `.tsx` files inside `src` and all subdirectories
	darkMode: "media", // or "class" to trigger it manually using `.dark`
	plugins: [],
};
