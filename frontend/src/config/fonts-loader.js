// import { FontsLoader } from "@/components/utilities/metadata-setup"; // File import statement

import { Lora, Manrope } from "next/font/google";

export const manrope = Manrope({
	variable: "--font-manrope",
	weight: ["200", "300", "400", "500", "600", "700", "800"],
	subsets: ["latin"],
	style: "normal",
	display: "swap",
	adjustFontFallback: false,
});

export const lora = Lora({
	variable: "--font-lora",
	weight: ["400", "500", "600", "700"],
	subsets: ["latin"],
	style: "normal",
	display: "swap",
	adjustFontFallback: false,
});
