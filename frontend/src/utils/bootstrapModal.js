/**
 * Remove stray Bootstrap modal backdrops and body scroll lock.
 */
export function cleanupBootstrapModalBackdrop({ force = false } = {}) {
	if (typeof document === "undefined") {
		return;
	}

	if (!force && document.querySelector(".modal.show")) {
		return;
	}

	document.querySelectorAll(".modal-backdrop").forEach((el) => el.remove());

	document.querySelectorAll(".modal.show").forEach((el) => {
		el.classList.remove("show");
		el.style.removeProperty("display");
		el.setAttribute("aria-hidden", "true");
	});

	document.body.classList.remove("modal-open");
	document.body.style.removeProperty("padding-right");
	document.body.style.removeProperty("overflow");
}

/**
 * Hide a Bootstrap modal and ensure backdrop/body state is cleaned up.
 *
 * @param {HTMLElement|null} element
 * @returns {Promise<void>}
 */
export function hideBootstrapModal(element) {
	if (typeof window === "undefined" || !element || !window.bootstrap?.Modal) {
		return Promise.resolve();
	}

	return new Promise((resolve) => {
		let settled = false;

		const finish = () => {
			if (settled) {
				return;
			}
			settled = true;
			resolve();
		};

		element.addEventListener("hidden.bs.modal", finish, { once: true });

		const instance = window.bootstrap.Modal.getOrCreateInstance(element);
		instance.hide();

		// Fallback when the event does not fire (e.g. rapid unmount / concurrent refresh).
		window.setTimeout(finish, 400);
	});
}

/**
 * @param {string} modalId
 * @returns {Promise<void>}
 */
export function hideBootstrapModalById(modalId) {
	if (typeof document === "undefined") {
		return Promise.resolve();
	}

	return hideBootstrapModal(document.getElementById(modalId));
}

/**
 * Show a Bootstrap modal by element id.
 *
 * Returns a promise that resolves when the `shown.bs.modal` event fires
 * (or after a fallback timeout), so callers can await the full transition.
 *
 * @param {string} modalId
 * @returns {Promise<void>}
 */
export function showBootstrapModalById(modalId) {
	if (typeof document === "undefined" || !window.bootstrap?.Modal) {
		return Promise.resolve();
	}

	const element = document.getElementById(modalId);
	if (!element) {
		return Promise.resolve();
	}

	return new Promise((resolve) => {
		let settled = false;

		const finish = () => {
			if (settled) return;
			settled = true;
			element.removeEventListener("shown.bs.modal", finish);
			resolve();
		};

		element.addEventListener("shown.bs.modal", finish, { once: true });

		window.bootstrap.Modal.getOrCreateInstance(element).show();

		window.setTimeout(finish, 400);
	});
}

/**
 * Hide one modal and show another.
 *
 * @param {string} fromModalId
 * @param {string} toModalId
 * @param {{ switchingRef?: { current: boolean } }} [options] Optional ref set while switching, e.g. to skip dismiss side-effects.
 * @returns {Promise<void>}
 */
export async function switchBootstrapModalById(fromModalId, toModalId, options = {}) {
	const { switchingRef } = options;

	if (switchingRef) {
		switchingRef.current = true;
	}

	try {
		await hideBootstrapModalById(fromModalId);
		await showBootstrapModalById(toModalId);
	} finally {
		if (switchingRef) {
			switchingRef.current = false;
		}
	}
}
