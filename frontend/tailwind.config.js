/** @type {import('tailwindcss').Config} */
module.exports = {
    prefix: "tw-", // Prefixes Tailwind classes with `tw-` to avoid naming conflicts
    content: ["./src/**/*.{js,ts,jsx,tsx}"], // Targets all `.js`, `.ts`, `.jsx`, `.tsx` files inside `src` and all subdirectories
    theme: {
        extend: {},
    },
    plugins: [],
};
