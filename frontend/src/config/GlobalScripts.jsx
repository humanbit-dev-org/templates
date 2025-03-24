// import { GlobalScripts } from "@/components/utilities/MetadataSetup"; // File import statement

"use client";

import { useEffect } from "react";
import { usePathname, useSearchParams } from "next/navigation";
import { fxLoad } from "@/utils/animations/fxLoad";
import { fxMove, debounce } from "@/utils/animations/fxMove";

export function GlobalScripts() {
	const currentPath = usePathname();
	const searchParams = useSearchParams();

	useEffect(() => {
		// Load and expose Bootstrap globally
		const bootstrap = require("bootstrap/dist/js/bootstrap.bundle.min.js");
		window.bootstrap = bootstrap;

		// Function to initialize all Bootstrap components automatically
		const initializeBootstrapComponents = () => {
			// Initialize Dropdowns
			document.querySelectorAll(".dropdown-toggle").forEach((dropdown) => {
				window.bootstrap.Dropdown.getOrCreateInstance(dropdown);
			});

			// Initialize Tooltips
			document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((tooltip) => {
				window.bootstrap.Tooltip.getOrCreateInstance(tooltip);
			});

			// Initialize Popovers
			document.querySelectorAll('[data-bs-toggle="popover"]').forEach((popover) => {
				window.bootstrap.Popover.getOrCreateInstance(popover);
			});

			// Initialize Toasts
			document.querySelectorAll(".toast").forEach((toast) => {
				window.bootstrap.Toast.getOrCreateInstance(toast);
			});

			// Initialize ScrollSpy
			document.querySelectorAll('[data-bs-spy="scroll"]').forEach((scrollSpy) => {
				window.bootstrap.ScrollSpy.getOrCreateInstance(scrollSpy);
			});
		};

		// Function to clean up Bootstrap components
		const cleanupBootstrapComponents = () => {
			// Reset active dropdowns
			document.querySelectorAll(".dropdown.show").forEach((dropdown) => {
				const instance = window.bootstrap.Dropdown.getInstance(dropdown);
				if (instance) instance.hide();
			});

			// Reset all open modals
			document.querySelectorAll(".modal.show").forEach((modal) => {
				const instance = window.bootstrap.Modal.getInstance(modal);
				if (instance) instance.hide();
			});

			// Reset all open offcanvas components
			document.querySelectorAll(".offcanvas.show").forEach((offcanvas) => {
				const instance = window.bootstrap.Offcanvas.getInstance(offcanvas);
				if (instance) instance.hide();
			});

			// Destroy tooltips and popovers
			document.querySelectorAll('[data-bs-toggle="tooltip"], [data-bs-toggle="popover"]').forEach((element) => {
				const tooltip = window.bootstrap.Tooltip.getInstance(element);
				if (tooltip) tooltip.dispose();
				const popover = window.bootstrap.Popover.getInstance(element);
				if (popover) popover.dispose();
			});

			// Reset all open toasts
			document.querySelectorAll(".toast.show").forEach((toast) => {
				const instance = window.bootstrap.Toast.getInstance(toast);
				if (instance) instance.hide();
			});

			// Destroy active ScrollSpy instances
			document.querySelectorAll('[data-bs-spy="scroll"]').forEach((scrollSpy) => {
				const instance = window.bootstrap.ScrollSpy.getInstance(scrollSpy);
				if (instance) instance.dispose();
			});
		};

		// Event delegation for Bootstrap components
		const handleBootstrapEvents = (event) => {
			const target = event.target.closest("[data-bs-toggle]");
			const openToasts = document.querySelectorAll(".toast.show");

			// If clicking outside of any toast, hide them
			if (!target && openToasts.length > 0) {
				openToasts.forEach((toast) => {
					const instance = window.bootstrap.Toast.getInstance(toast);
					if (instance) instance.hide();
				});
				return;
			}

			if (!target) return;

			const toggleType = target.getAttribute("data-bs-toggle");
			const targetId = target.getAttribute("data-bs-target");
			const element = document.querySelector(targetId);

			if (!element) return;

			// If clicking on a new toast, hide all currently open toasts first
			if (toggleType === "toast") {
				openToasts.forEach((toast) => {
					if (toast !== element) {
						const instance = window.bootstrap.Toast.getInstance(toast);
						if (instance) instance.hide();
					}
				});

				window.bootstrap.Toast.getOrCreateInstance(element).show();
			} else if (toggleType === "modal") {
				window.bootstrap.Modal.getOrCreateInstance(element).show();
			}
		};

		// Attach event delegation to `document.body`
		document.body.addEventListener("click", handleBootstrapEvents);

		// Initialize Bootstrap components on page load
		initializeBootstrapComponents();
		// Other scripts triggered on page load
		fxLoad();
		fxMove(".fx");

		// Utility wrappers
		const fxMoveDebounced = debounce(() => {
			fxMove(".fx");
		}, 200);

		// Scripts triggered on scroll
		window.addEventListener("scroll", fxMoveDebounced);

		// Trigger cleanup whenever route (path or search) parameters change
		return () => {
			cleanupBootstrapComponents();
			document.body.removeEventListener("click", handleBootstrapEvents);
			window.removeEventListener("scroll", fxMoveDebounced);
		};
	}, [currentPath, searchParams]); // Dependencies ensure this runs on URL changes

	return null; // No visual rendering

	// Use the following `return` statament instead when:
	//
	// - The script is placed in `/public` and not importable via ES modules
	// - It's non-critical (doesn't block rendering)
	// - You want to load it after the page becomes interactive
	// - It's for third-party scripts, e.g., analytics, widgets, or old libraries

	// Avoid it when:
	//
	// - The script can be imported via import (prefer `@import`)
	// - You need the script immediately on page load
	// - You're running on the Edge Runtime (it won't run there)

	// return (
	// 	<>
	// 		<Script src="/js/myScript.js" strategy="lazyOnload" />
	// 	</>
	// );
}