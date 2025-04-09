// Applies exit and entrance effects based on link clicks and navigation events.

// Handle page transition animation on link clicks and history navigation
export function fxLoad() {
	const debounceDelay = 300;
	let isTransitioning = false;
	let lastTransitionTime = 0;

	// Trigger exit animation on refresh, address bar nav, or browser buttons
	window.addEventListener("beforeunload", () => {
		document.body.classList.add("link");
	});

	// Handle internal anchor clicks
	document.querySelectorAll('a:not([target="_blank"])').forEach((link) => {
		link.addEventListener("click", (event) => {
			const now = Date.now();

			// Skip if a transition is already in progress or user clicks too fast
			if (isTransitioning || now - lastTransitionTime < debounceDelay) return;

			const href = link.getAttribute("href");
			if (!href || href === "#") return;

			// Check if link is internal
			const linkURL = new URL(href, window.location.href);
			const currentPath = window.location.pathname;

			// Skip external links
			if (linkURL.origin !== window.location.origin) return;

			// Allow Ctrl/Command + click to open in new tab
			if (event.ctrlKey || event.metaKey) return;

			// Skip same-page anchor links
			if (linkURL.pathname === currentPath && linkURL.hash) return;

			event.preventDefault();

			// Block further clicks and mark time
			isTransitioning = true;
			lastTransitionTime = now;

			// Add transition class to body
			document.body.classList.add("link");

			// Wait for animation to end before navigating
			const handleAnimationEnd = () => {
				document.body.removeEventListener("animationend", handleAnimationEnd);
				window.location.href = href;
			};
			document.body.addEventListener("animationend", handleAnimationEnd);
		});
	});

	// Handle back/forward navigation from bfcache
	window.addEventListener("pageshow", (event) => {
		if (event.persisted) {
			document.body.classList.remove("fx_load", "link");

			requestAnimationFrame(() => {
				requestAnimationFrame(() => {
					document.body.classList.add("fx_load");
				});
			});
		}
	});
}
