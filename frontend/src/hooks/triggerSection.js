// File import statements:
// import { triggerSection } from "@/hooks/triggerSectionSticky";

"use client"; // Ensures it's used in the browser

export const triggerSection = async (id, idListButtonWrap, idCollapsedButtonWrap) => {
	const paragraphs = document.querySelectorAll(".paragraph_txt_component");
	paragraphs.forEach((paragraph) => {
		paragraph.style.opacity = "0"; // Hide instantly
		paragraph.style.transition = ""; // Remove transition to avoid immediate fade-in
	});
	const mobileHero = document.querySelectorAll(".hero_component.obj_mobile.el_btn");
	const desktopHero = document.querySelectorAll(".hero_component.obj_desktop");
	const sections = document.querySelectorAll(".content_component");
	const listButtonWrap = document.getElementById(idListButtonWrap);
	const collapsedButtonWrap = document.getElementById(idCollapsedButtonWrap);
	const targetSection = document.getElementById("content" + id);
	const bannerElement = document.getElementById("banner" + id);
	const footerElement = document.getElementsByClassName("footerJs");

	if (!targetSection) return; // Exit if section doesn't exist

	const isActive = targetSection.classList.contains("active");

	// Toggle section visibility
	sections.forEach((section) => {
		if (section === targetSection) {
			setTimeout(() => {
				paragraphs.forEach((paragraph) => {
					paragraph.style.transition = "opacity 0.5s ease-in-out"; // Add transition
					paragraph.style.opacity = "1"; // Fade-in after delay
				});
			}, 200);
			section.classList.toggle("active", !isActive);
			section.classList.toggle("position-absolute", !isActive);
		} else {
			section.classList.toggle("d-none", !isActive);
			section.classList.remove("active");
			section.classList.remove("position-absolute");
		}
	});

	// Toggle button visibility
	listButtonWrap?.classList.toggle("d-none", !isActive);
	collapsedButtonWrap?.classList.toggle("d-none", isActive);

	if (!isActive) {
		const mobileHeroEl = mobileHero[0];
		footerElement[0].classList.add("d-none");

		if (mobileHeroEl) {
			mobileHeroEl.setAttribute("data-bs-target", `#collapse${id}`);
			mobileHeroEl.setAttribute("data-bs-toggle", "collapse");
			mobileHeroEl.setAttribute("data-list-button", `listButtonWrap${id}`);
			mobileHeroEl.setAttribute("data-collapsed-button", `collapsedButtonWrap${id}`);
			mobileHeroEl.setAttribute("data-banner-id", `banner${id}`);
		}
	}

	// Scroll to banner if exists
	if (bannerElement) {
		window.scrollTo({ top: 0, behavior: "auto" });
	}

	// Determine if any section is still active
	const anyActive = [...sections].some((section) => section.classList.contains("active"));

	// Toggle hero components based on active state
	mobileHero.forEach((hero) => hero.classList.toggle("d-none", !anyActive));
	desktopHero.forEach((hero) => hero.classList.toggle("d-none", anyActive));

	if (!anyActive) {
		const offset = 204;
		const elementPosition = bannerElement.getBoundingClientRect().top + window.scrollY;
		window.scrollTo({ top: elementPosition - offset, behavior: "auto" });
	}
};
