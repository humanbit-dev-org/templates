// File import statements:
// import { useSectionScroll } from "@/hooks/useSectionScroll";

"use client"; // Ensures it's used in the browser

import { useEffect } from "react";

export function useSectionScroll(isActive) {
	useEffect(() => {
		if (typeof window === "undefined" || !isActive) return;

		let delay = false;

		const handleScroll = (event) => {
			// Allow scrolling if the event target is inside `.nav_side_burger_component`
			if (event.target.closest(".nav_side_burger_component")) return;

			// Prevent scrolling if any `.content_component.active` exists
			if (document.querySelectorAll(".content_component.active").length > 0) return;

			event.preventDefault();
			if (delay) return;

			delay = true;
			setTimeout(() => (delay = false), 200);

			const sections = document.querySelectorAll(".content_component");
			const scrollY = window.scrollY;
			let nextSection = null;

			if (event.deltaY > 0) {
				// Scrolling down
				for (let i = 0; i < sections.length; i++) {
					if (sections[i].offsetTop > scrollY + 300) {
						nextSection = sections[i];
						break;
					}
				}
			} else {
				// Scrolling up
				for (let i = sections.length - 1; i >= 0; i--) {
					if (sections[i].offsetTop < scrollY - 20) {
						nextSection = sections[i];
						break;
					}
				}
			}

			if (nextSection) {
				window.scrollTo({
					top: nextSection.offsetTop - 204,
					behavior: "auto",
				});
			}
		};

		window.addEventListener("wheel", handleScroll, { passive: false });

		return () => {
			window.removeEventListener("wheel", handleScroll);
		};
	}, [isActive]);
}

export function useFooterMargin() {
	useEffect(() => {
		const handleScroll = (event) => {
			const footerElement = document.getElementsByClassName("footerJs");

			// Ensure no .content_component has an active class before allowing scroll behavior
			if (document.querySelectorAll(".content_component.active").length > 0) {
				footerElement[0].classList.remove("d-none");
			} else {
				footerElement[0].classList.remove("d-none");
			}
		};

		window.addEventListener("wheel", handleScroll, { passive: false });
		window.addEventListener("keydown", handleScroll);

		return () => {
			window.removeEventListener("wheel", handleScroll);
			window.removeEventListener("keydown", handleScroll);
		};
	}, []);
}
