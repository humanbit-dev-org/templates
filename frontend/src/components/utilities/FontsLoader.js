// import { FontsLoader } from "components/utilities/MetadataSetup"; // File import statement

import { Inter, Roboto_Mono, Quicksand } from "next/font/google";

export const inter = Inter({
	variable: "--font-inter",
	weight: ["100", "200", "300", "400", "500", "600", "700", "800", "900"],
	subsets: ["latin"],
	display: "swap",
});

export const roboto_mono = Roboto_Mono({
	variable: "--font-roboto-mono",
	weight: ["100", "200", "300", "400", "500", "600", "700"],
	subsets: ["latin"],
	display: "swap",
});

export const quicksand = Quicksand({
	variable: "--font-quicksand",
	weight: ["400", "700"],
	subsets: ["latin"],
	display: "swap",
});
