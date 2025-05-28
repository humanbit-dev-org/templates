// import { useDeviceInfo } from "@/hooks/deviceInfo"; // File import statement
//
"use client"; // marks module for full browser execution

import { useEffect, useState } from "react";

// ===============================================
// ## ############################################
// ===============================================

// Tracks viewport width on the client and computes basic device info
export function useDeviceInfo() {
	const [viewport, setViewport] = useState(null); // Store the current viewport width

	useEffect(() => {
		// Define and register a resize handler to update viewport
		const handleResize = () => setViewport(window.innerWidth);
		window.addEventListener("resize", handleResize);
		handleResize(); // Set initial viewport width on mount

		// Clean up listener on unmount
		return () => window.removeEventListener("resize", handleResize);
	}, []);

	// Check if the viewport falls under a mobile threshold
	const isPhone = typeof viewport === "number" && viewport < 575.98; // Small viewports
	const isTablet = typeof viewport === "number" && viewport > 576 && viewport < 1199.98; // Medium viewports
	const isMobile = isPhone || isTablet; // Combined mobile viewports
	const isDesktop = typeof viewport === "number" && viewport > 1200; // Large viewports

	return {
		viewport, // Current viewport width
		isPhone, // Smartphone
		isTablet, // Tablet
		isMobile, // Smartphone & tablet
		isDesktop, // Desktop & laptop
	};
}
