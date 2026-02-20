// File import statements:
// import { updateSectionHeight } from "@/hooks/useSectionHeight";

"use client"; // Ensures it runs in the browser

import { useEffect, useState } from "react";

// Hook version (for components that need the value)
export function useSectionHeight() {
	const [contentHeight, setContentHeight] = useState(0);

	useEffect(() => {
		updateSectionHeight(); // Call standalone function
		window.addEventListener("resize", updateSectionHeight);
		return () => window.removeEventListener("resize", updateSectionHeight);
	}, []);

	return contentHeight;
}

// Standalone function (for calling inside onClick)
export function updateSectionHeight() {
	if (typeof window === "undefined") return;

	const activeContent = document.querySelector(".content_component.active");
	const spaceWrap = document.querySelector(".space_wrap");

	if (activeContent) {
		const height = activeContent.offsetHeight;

		if (spaceWrap) spaceWrap.style.height = `${height}px`;
	} else {
		if (spaceWrap) spaceWrap.style.height = "auto";
	}
}
