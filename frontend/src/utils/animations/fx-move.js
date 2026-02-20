// Check if an element should be animated based on visibility
export function isElementFullyInViewport(element) {
	const rect = element.getBoundingClientRect();
	const windowHeight = window.innerHeight || document.documentElement.clientHeight;
	const windowWidth = window.innerWidth || document.documentElement.clientWidth;
	const computedStyle = window.getComputedStyle(element);
	const position = computedStyle.position;
	const isLargerThanViewport = rect.height > windowHeight || rect.width > windowWidth;

	if (position === "fixed") return true;

	if (
		element.tagName === "IMG" ||
		element.tagName === "FIGURE" ||
		element.tagName === "svg" ||
		element.tagName === "path"
	) {
		return true;
	}

	if (
		computedStyle.height === "auto" ||
		computedStyle.width === "auto" ||
		computedStyle.height === "inherit" ||
		computedStyle.width === "inherit"
	) {
		return true;
	}

	if (isLargerThanViewport) {
		return rect.top < windowHeight && rect.bottom > 0 && rect.left < windowWidth && rect.right > 0;
	}

	return rect.top >= 0 && rect.bottom <= windowHeight;
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
export function triggerDelayedAnimations(elements, totalVisible, baseElement) {
	if (elements.length === 0) return Promise.resolve();

	return new Promise((resolve) => {
		let completed = 0;
		let delayCounter = 1;
		const visibleElements = elements.filter((el) => isElementFullyInViewport(el) && !el.classList.contains("triggered"));
		const totalToAnimate = visibleElements.length;
		const applyDelay = totalToAnimate > 1;
		const baseDuration = baseElement ? getAnimationDuration(baseElement) : getAnimationDuration(visibleElements[0]);
		const delayAmount = baseDuration / 2;

		if (totalToAnimate === 0) return resolve();

		visibleElements.forEach((element) => {
			const duration = getAnimationDuration(element);
			const delayTime = applyDelay ? delayCounter * delayAmount : 0;

			setTimeout(() => {
				element.classList.add("triggered");
				setTimeout(() => {
					completed++;
					if (completed === totalToAnimate) resolve();
				}, duration);
			}, delayTime);

			if (applyDelay) delayCounter++;
		});
	});
}

// Decide how to process a group: immediate, cascade, or delayed
export function processGroup(elements, totalVisible, callback) {
	const immediate = elements.filter((el) => !el.classList.contains("cascade") && !el.classList.contains("delay"));
	const cascade = elements.filter((el) => el.classList.contains("cascade"));
	const delay = elements.filter((el) => el.classList.contains("delay") && !el.classList.contains("cascade"));

	const firstReference = immediate[0] || cascade[0] || delay[0];

	// Start `.fx` and `.fx.delay` concurrently:
	const immediateAnimations = triggerImmediateAnimations(immediate);
	const delayedAnimations = triggerDelayedAnimations(delay, totalVisible, firstReference);

	// Cascade animations run after immediate (`.fx`), but independently of delay:
	const cascadeAnimations = immediateAnimations.then(() => processElementsSequentially(cascade, totalVisible));

	// Wait for all types of animations to complete:
	Promise.all([immediateAnimations, delayedAnimations, cascadeAnimations]).then(callback);
}

// Master function: runs animation logic for selected elements
export function fxMove(selector) {
	const all = Array.from(document.querySelectorAll(selector));
	const totalVisible = all.filter(isElementFullyInViewport).length;
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

// Delay function calls to avoid excessive triggering
export function debounce(func, wait) {
	let timeout;
	return (...args) => {
		clearTimeout(timeout);
		timeout = setTimeout(() => func.apply(this, args), wait);
	};
}
