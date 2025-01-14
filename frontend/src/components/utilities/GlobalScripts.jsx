// import { GlobalScripts } from "components/utilities/MetadataSetup"; // File import statement

"use client";

import { useEffect } from "react";
import { usePathname, useSearchParams } from "next/navigation";

export function GlobalScripts() {
	const currentPath = usePathname(); // Hook to get the current pathname
	const searchParams = useSearchParams(); // Hook to get search parameters

	useEffect(() => {
		// Load and expose Bootstrap globally
		const bootstrap = require("bootstrap/dist/js/bootstrap.bundle.min.js");
		window.bootstrap = bootstrap;

		// Cleanup function for Bootstrap components
		const cleanupBootstrapComponents = () => {
			// Reset dropdowns
			const dropdowns = document.querySelectorAll(".dropdown.show");
			dropdowns.forEach((dropdown) => {
				const dropdownInstance = window.bootstrap.Dropdown.getInstance(dropdown);
				if (dropdownInstance) {
					dropdownInstance.hide();
				}
			});

			// Hide all open modals
			const modals = document.querySelectorAll(".modal.show");
			modals.forEach((modal) => {
				const modalInstance = window.bootstrap.Modal.getInstance(modal);
				if (modalInstance) {
					modalInstance.hide();
				}
			});

			// Hide all open offcanvas components
			const offcanvases = document.querySelectorAll(".offcanvas.show");
			offcanvases.forEach((offcanvas) => {
				const offcanvasInstance = window.bootstrap.Offcanvas.getInstance(offcanvas);
				if (offcanvasInstance) {
					offcanvasInstance.hide();
				}
			});

			// Destroy tooltips and popovers
			const tooltipsAndPopovers = document.querySelectorAll('[data-bs-toggle="tooltip"], [data-bs-toggle="popover"]');
			tooltipsAndPopovers.forEach((element) => {
				const tooltipInstance = window.bootstrap.Tooltip.getInstance(element);
				if (tooltipInstance) {
					tooltipInstance.dispose();
				}
				const popoverInstance = window.bootstrap.Popover.getInstance(element);
				if (popoverInstance) {
					popoverInstance.dispose();
				}
			});

			// Reset scrollspy (if using)
			const scrollspies = document.querySelectorAll('[data-bs-spy="scroll"]');
			scrollspies.forEach((element) => {
				const spyInstance = window.bootstrap.ScrollSpy.getInstance(element);
				if (spyInstance) {
					spyInstance.dispose();
				}
			});
		};

		// Trigger cleanup whenever path or search parameters change
		cleanupBootstrapComponents();

		// Cleanup on component unmount
		return () => {
			cleanupBootstrapComponents();
		};
	}, [currentPath, searchParams]); // Dependencies ensure this runs on URL changes

	return null; // No visual rendering

	// return (
	// 	<>
	// 		{/* <Script src="/js/myScript.js" strategy="lazyOnload" /> */}
	// 	</>
	// );
}
