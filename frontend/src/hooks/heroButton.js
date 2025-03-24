// File import statements:
// import { heroButton } from "@/hooks/heroButton";

"use client"; // Ensures it's used in the browser

export const heroButton = async () => {
	const mobileHero = document.querySelectorAll(".hero_component.obj_mobile.el_btn");
	const desktopHero = document.querySelectorAll(".hero_component.obj_desktop");
	const sections = document.querySelectorAll(".content_component");

	if (!mobileHero.length) return;

	const listButtonId = mobileHero[0]?.getAttribute("data-list-button");
	const collapsedButtonId = mobileHero[0]?.getAttribute("data-collapsed-button");
	const bannerId = mobileHero[0]?.getAttribute("data-banner-id");

	const readChapterBtn = document.getElementById(listButtonId);
	const activeButtonBtn = document.getElementById(collapsedButtonId);
	const bannerElement = document.getElementById(bannerId);

	if (readChapterBtn) readChapterBtn.classList.remove("d-none");
	if (activeButtonBtn) activeButtonBtn.classList.add("d-none");

	// Reset sections
	sections.forEach((section) => section.classList.remove("active", "d-none", "position-absolute"));

	// Check if any section is still active
	const anyActive = [...sections].some((section) => section.classList.contains("active"));

	// Toggle hero components based on active state
	mobileHero.forEach((hero) => hero.classList.toggle("d-none", !anyActive));
	desktopHero.forEach((hero) => hero.classList.toggle("d-none", anyActive));

	if (!anyActive) {
		window.scrollTo({ top: 0, behavior: "auto" });
	}
};
