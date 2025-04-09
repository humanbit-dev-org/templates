// Controls the triggering of CSS-based animations on elements based on class logic and visibility.

// Check if an element is fully visible within the viewport
export function isElementFullyInViewport(element) {
	const rect = element.getBoundingClientRect();
	const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
	return rect.top >= 0 && rect.bottom <= viewportHeight;
}

// Get the longer duration between animation and transition (in ms)
export function getAnimationDuration(element) {
	const computedStyle = window.getComputedStyle(element);
	const animationDuration = parseFloat(computedStyle.animationDuration) || 0;
	const transitionDuration = parseFloat(computedStyle.transitionDuration) || 0;
	return Math.max(animationDuration, transitionDuration) * 1000;
}

// Trigger all visible elements immediately, wait for all to finish
export function triggerImmediateAnimations(elements) {
	return new Promise((resolve) => {
		let completedCount = 0;
		const totalElements = elements.length;

		if (totalElements === 0) return resolve();

		elements.forEach((element) => {
			if (isElementFullyInViewport(element)) {
				element.classList.add("triggered");
				const duration = getAnimationDuration(element);
				setTimeout(() => {
					completedCount++;
					if (completedCount === totalElements) resolve();
				}, duration || 0);
			} else {
				completedCount++;
				if (completedCount === totalElements) resolve();
			}
		});
	});
}

// Trigger visible elements sequentially with optional delays
export function processElementsSequentially(elements, totalVisible) {
	let sequence = Promise.resolve();

	if (elements.length === 0) return sequence;

	elements.forEach((element) => {
		sequence = sequence.then(() => {
			if (isElementFullyInViewport(element)) {
				const duration = getAnimationDuration(element);
				const extraDelay = element.classList.contains("delay") && totalVisible > 1 ? duration / 2 : 0;

				return new Promise((resolve) => {
					setTimeout(() => {
						element.classList.add("triggered");
						setTimeout(resolve, duration || 0);
					}, extraDelay);
				});
			}
			return Promise.resolve();
		});
	});

	return sequence;
}

// Trigger visible elements with sequenced delays
export function triggerDelayedAnimations(elements, totalVisible) {
	let delayCounter = 1;

	if (elements.length === 0) return Promise.resolve();

	elements.forEach((element) => {
		const applyDelay = totalVisible > 1;

		if (isElementFullyInViewport(element) && !element.classList.contains("triggered")) {
			const duration = getAnimationDuration(element);
			const delayAmount = duration / 2;

			setTimeout(
				() => {
					element.classList.add("triggered");
				},
				applyDelay ? delayCounter * delayAmount : 0
			);

			if (applyDelay) delayCounter++;
		}
	});
}

// Decide how to process a group: immediate, cascade, or delayed
export function processGroup(elements, totalVisible, callback) {
	const immediate = Array.from(elements).filter(
		(el) => !el.classList.contains("cascade") && !el.classList.contains("delay")
	);
	let cascade = Array.from(elements).filter((el) => el.classList.contains("cascade"));
	const delay = Array.from(elements).filter((el) => el.classList.contains("delay") && !el.classList.contains("cascade"));

	let firstCascade = null;

	if (immediate.length === 0 && cascade.length > 0) {
		firstCascade = cascade[0];
		cascade = cascade.slice(1);

		const runCascade = () => {
			triggerImmediateAnimations([firstCascade]).then(() => {
				processElementsSequentially(cascade, totalVisible).then(callback);
			});
		};

		if (firstCascade.classList.contains("delay")) {
			if (totalVisible === 1) {
				runCascade();
			} else {
				const delayDuration = getAnimationDuration(firstCascade) / 2;
				setTimeout(runCascade, delayDuration);
			}
		} else {
			runCascade();
		}

		triggerDelayedAnimations(delay, totalVisible);
	} else {
		triggerDelayedAnimations(delay, totalVisible);
		triggerImmediateAnimations(immediate).then(() => {
			processElementsSequentially(cascade, totalVisible).then(callback);
		});
	}
}

// Master function: runs animation logic for selected elements
export function fxMove(selector) {
	const all = Array.from(document.querySelectorAll(selector));
	const totalVisible = all.filter((el) => isElementFullyInViewport(el)).length;

	const wait = all.filter((el) => el.classList.contains("wait"));
	const nowait = all.filter((el) => !el.classList.contains("wait"));

	processGroup(nowait, totalVisible, () => {
		if (wait.length > 0) {
			processGroup(wait, totalVisible, () => {
				// All done
			});
		}
	});
}

// Debounce utility
export function debounce(func, wait) {
	let timeout;
	return function (...args) {
		clearTimeout(timeout);
		timeout = setTimeout(() => func.apply(this, args), wait);
	};
}
