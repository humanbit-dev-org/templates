// Check if an element is fully visible within the viewport
export function isElementFullyInViewport($element) {
	const elementTop = $element.offset().top;
	const elementBottom = elementTop + $element.outerHeight();
	const viewportTop = $(window).scrollTop();
	const viewportBottom = viewportTop + $(window).innerHeight();

	// Return true if the element is fully within the viewport
	return elementTop >= viewportTop && elementBottom <= viewportBottom;
}

// Calculate the total duration of an element's animation or transition
export function getAnimationDuration($element) {
	// Extract animation and transition durations from CSS (in seconds)
	const animationDuration = parseFloat($element.css("animation-duration")) || 0;
	const transitionDuration = parseFloat($element.css("transition-duration")) || 0;

	// Return the maximum duration (converted to milliseconds)
	return Math.max(animationDuration, transitionDuration) * 1000; // Convert to ms
}

// Trigger immediate animations and resolve when all are complete
export const triggerImmediateAnimations = ($elements) => {
	return new Promise((resolve) => {
		let completedCount = 0;
		const totalElements = $elements.length;

		if (totalElements === 0) {
			resolve(); // No elements to animate
			return;
		}

		// Start animations on each element
		$elements.each(function () {
			const $element = $(this);

			if (isElementFullyInViewport($element)) {
				// Add `.triggered` class to start animation
				$element.addClass("triggered");

				// Wait for the animation duration before marking as complete
				const duration = getAnimationDuration($element);
				setTimeout(() => {
					completedCount++;
					// Resolve when all animations are done
					if (completedCount === totalElements) resolve();
				}, duration || 0);
			} else {
				// Element not visible; count as completed
				completedCount++;
				if (completedCount === totalElements) resolve();
			}
		});
	});
};

// Trigger cascade animations sequentially with optional delays
export const processElementsSequentially = ($elements, totalVisibleElements) => {
	let sequence = Promise.resolve();

	if ($elements.length === 0) {
		return sequence; // No elements to process
	}

	// Chain animations sequentially
	$elements.each(function () {
		const $element = $(this);
		sequence = sequence.then(() => {
			if (isElementFullyInViewport($element)) {
				// Calculate delay based on element's duration
				const elementDuration = getAnimationDuration($element);
				const extraDelay = $element.hasClass("delay") && totalVisibleElements > 1 ? elementDuration / 2 : 0;

				return new Promise((resolve) => {
					// Wait for `extraDelay` before starting the animation
					setTimeout(() => {
						$element.addClass("triggered");
						// Wait for the animation duration before resolving
						setTimeout(() => resolve(), elementDuration || 0);
					}, extraDelay);
				});
			} else {
				return Promise.resolve(); // Skip if not visible
			}
		});
	});

	return sequence;
};

// Trigger delayed animations with incremental delays
export const triggerDelayedAnimations = ($elements, totalVisibleElements) => {
	let delayCounter = 1;

	if ($elements.length === 0) {
		return Promise.resolve(); // No elements to animate
	}

	// Apply delays to each element
	$elements.each(function () {
		const $element = $(this);
		// Apply delay only if multiple elements are visible
		const applyDelay = totalVisibleElements > 1;

		if (isElementFullyInViewport($element) && !$element.hasClass("triggered")) {
			// Calculate delay amount based on element's duration
			const elementDuration = getAnimationDuration($element);
			const delayAmount = elementDuration / 2;

			// Start animation after calculated delay
			setTimeout(
				() => {
					$element.addClass("triggered");
				},
				applyDelay ? delayCounter * delayAmount : 0
			);
			if (applyDelay) delayCounter++;
		}
	});
};

// Helper function to process a group of elements (immediate, cascade, delay)
export function processGroup($elements, totalVisibleElements, callback) {
	// Categorize elements
	let $immediateElements = $elements.not(".cascade, .delay"); // Immediate animations (not `.cascade`, not `.delay`)
	let $cascadeElements = $elements.filter(".cascade"); // Cascade animations (elements with `.cascade`)
	let $delayElements = $elements.filter(".delay").not(".cascade"); // Delayed animations (elements with `.delay` but not `.cascade`)

	// If no immediate elements, treat first cascade element as immediate
	let $firstCascadeElement = null;
	if ($immediateElements.length === 0 && $cascadeElements.length > 0) {
		// First cascade element becomes immediate
		$firstCascadeElement = $cascadeElements.first();
		$cascadeElements = $cascadeElements.not($firstCascadeElement);

		// Process the first cascade element
		if ($firstCascadeElement.hasClass("delay")) {
			if (totalVisibleElements === 1) {
				// Trigger immediately if only one element is visible
				triggerImmediateAnimations($firstCascadeElement).then(() => {
					processElementsSequentially($cascadeElements, totalVisibleElements).then(callback);
				});
			} else {
				// Apply dynamic delay before triggering
				const firstElementDuration = getAnimationDuration($firstCascadeElement);
				const delayDuration = firstElementDuration / 2;
				setTimeout(() => {
					triggerImmediateAnimations($firstCascadeElement).then(() => {
						processElementsSequentially($cascadeElements, totalVisibleElements).then(callback);
					});
				}, delayDuration);
			}
		} else {
			// Trigger immediately without delay
			triggerImmediateAnimations($firstCascadeElement).then(() => {
				processElementsSequentially($cascadeElements, totalVisibleElements).then(callback);
			});
		}

		// Trigger delayed animations
		triggerDelayedAnimations($delayElements, totalVisibleElements);
	} else {
		// Regular processing when immediate elements are present
		triggerDelayedAnimations($delayElements, totalVisibleElements);
		triggerImmediateAnimations($immediateElements).then(() => {
			// After immediate animations, process cascades
			processElementsSequentially($cascadeElements, totalVisibleElements).then(callback);
		});
	}
}

// Main function to manage animations
export function fxMove(selector) {
	const $visibleElements = $(selector);
	// Count total visible elements
	const totalVisibleElements = $visibleElements.filter(function () {
		return isElementFullyInViewport($(this));
	}).length;

	// Separate elements with and without the `.wait` class
	const $waitElements = $visibleElements.filter(".wait"); // Elements with `.wait` class
	const $nonWaitElements = $visibleElements.not(".wait"); // Elements without `.wait` class

	// Process non-`.wait` elements first
	processGroup($nonWaitElements, totalVisibleElements, () => {
		// After non-`.wait` elements finish, process `.wait` elements
		if ($waitElements.length > 0) {
			processGroup($waitElements, totalVisibleElements, () => {
				// All animations completed
			});
		}
	});
}

// Debounce function to limit how often `fxMove()` runs during rapid scroll events
export function debounce(func, wait) {
	let timeout;
	return function () {
		clearTimeout(timeout); // Clear previous timeout
		timeout = setTimeout(func, wait); // Set new timeout
	};
}
