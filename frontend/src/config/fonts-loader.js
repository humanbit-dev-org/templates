// import { FontsLoader } from "@/components/utilities/metadata-setup"; // File import statement

import { Lora, Manrope } from "next/font/google"; // Loads Google Fonts using built-in API
// import localFont from "next/font/local"; // Loads self-hosted fonts

export const manrope = Manrope({
	variable: "--font-manrope", // Creates global CSS variable to reference font in stylesheets
	weight: ["200", "300", "400", "500", "600", "700", "800"], // Declares all available `font-weight` values
	style: "normal", // Specifies `font-style`; valid values are "normal" and "italic"
	subsets: ["latin"], // Limits Unicode character range to reduce file size (Google Fonts only)
	display: "swap", // Shows fallback font immediately, swaps when loaded
	adjustFontFallback: true, // Aligns fallback metrics to reduce layout shift (CLS) [Google Fonts only]
});

export const lora = Lora({
	variable: "--font-lora", // Creates global CSS variable to reference font in stylesheets
	weight: ["400", "500", "600", "700"], // Declares all available `font-weight` values
	style: "normal", // Specifies `font-style`; valid values are "normal" and "italic"
	subsets: ["latin"], // Limits Unicode character range to reduce file size (Google Fonts only)
	display: "swap", // Shows fallback font immediately, swaps when loaded
	adjustFontFallback: true, // Aligns fallback metrics to reduce layout shift (CLS) [Google Fonts only]
});

// Template for loading local fonts
// export const font_example_1 = localFont({
// 	src: [{ path: "../../public/fonts/Font-Example-1.otf" }], // Points to source files stored in `/public/fonts` (path must be relative to this file and extension lowercase)
// 	variable: "--font-example-1", // Creates global CSS variable to reference font in stylesheets
// 	weight: ["100", "200", "300", "400", "500", "600", "700", "800", "900"], // Declares the full range of `font-weight` values
// 	style: ["normal", "italic"], // Specifies `font-style`; valid values are "normal" and "italic"
// 	display: "swap", // Shows fallback font immediately, swaps when loaded
// });
