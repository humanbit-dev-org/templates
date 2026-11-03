$(document).ready(function () {
	// Prevent URL and history changes when switching tabs in CRUD edit/create forms
	// Store the original URL when page loads
	let originalUrl = window.location.href.split('#')[0];
	let isTabSwitch = false;
	
	// Function to manually switch tabs without touching history
	function switchTabManually(tabLink) {
		const $tabLink = $(tabLink);
		const target = $tabLink.data('bs-target') || $tabLink.attr('href');
		if (!target) return;
		
		const tabElement = document.querySelector(target);
		if (!tabElement) return;
		
		// Find the tab container
		const $navTabs = $tabLink.closest('.nav-tabs');
		const $tabContent = $tabLink.closest('.card, .crud, [bp-section]').find('.tab-content').first();
		
		// Remove active class from all tabs and panes
		$navTabs.find('.nav-link').removeClass('active').attr('aria-selected', 'false');
		$tabContent.find('.tab-pane').removeClass('show active');
		
		// Add active class to clicked tab and target pane
		$tabLink.addClass('active').attr('aria-selected', 'true');
		$(tabElement).addClass('show active');
	}
	
	// Remove hash from URL on page load if it exists
	if (window.location.hash) {
		const hash = window.location.hash.substring(1);
		const targetTab = document.querySelector(`[data-bs-target="#${hash}"], [href="#${hash}"]`);
		if (targetTab) {
			setTimeout(function() {
				switchTabManually(targetTab);
			}, 100);
		}
		// Remove hash from URL immediately
		if (window.history && window.history.replaceState) {
			window.history.replaceState(null, null, originalUrl);
		}
	}
	
	// Intercept and prevent pushState to avoid adding history entries when switching tabs
	const originalPushState = window.history.pushState;
	window.history.pushState = function(state, title, url) {
		// Check if we're on a CRUD page with tabs
		const isCrudPage = $('.crud .nav-tabs').length > 0 || $('[bp-section="crud-operation-update"] .nav-tabs').length > 0 || $('[bp-section="crud-operation-create"] .nav-tabs').length > 0;
		
		// If we're switching tabs or if the URL contains a hash on a CRUD page, use replaceState instead
		if (isTabSwitch || (isCrudPage && url && url.includes('#'))) {
			// Use replaceState to avoid adding history entry
			if (window.history.replaceState) {
				return window.history.replaceState(state, title, originalUrl);
			}
		}
		// Otherwise, allow normal pushState
		return originalPushState.apply(window.history, arguments);
	};
	
	// Intercept popstate events to prevent navigation when tabs are clicked
	window.addEventListener('popstate', function(e) {
		if (isTabSwitch) {
			// If this popstate was triggered by a tab switch, prevent it
			// Restore original URL
			if (window.history && window.history.replaceState) {
				window.history.replaceState(null, null, originalUrl);
			}
		}
	}, true);
	
	// Intercept tab clicks with highest priority (capture phase)
	document.addEventListener('click', function(e) {
		const target = e.target.closest('.crud .nav-tabs .nav-link[data-bs-toggle="tab"], [bp-section="crud-operation-update"] .nav-tabs .nav-link[data-bs-toggle="tab"], [bp-section="crud-operation-create"] .nav-tabs .nav-link[data-bs-toggle="tab"]');
		
		if (target) {
			isTabSwitch = true;
			e.preventDefault();
			e.stopPropagation();
			e.stopImmediatePropagation();
			
			// Switch tab manually
			switchTabManually(target);
			
			// Ensure URL doesn't change
			setTimeout(function() {
				if (window.history && window.history.replaceState) {
					window.history.replaceState(null, null, originalUrl);
				}
				isTabSwitch = false;
			}, 0);
			
			return false;
		}
	}, true); // Use capture phase to intercept before Bootstrap
	
	// Also use jQuery as backup (lower priority)
	$(document).on('click', '.crud .nav-tabs .nav-link[data-bs-toggle="tab"], [bp-section="crud-operation-update"] .nav-tabs .nav-link[data-bs-toggle="tab"], [bp-section="crud-operation-create"] .nav-tabs .nav-link[data-bs-toggle="tab"]', function(e) {
		if (!isTabSwitch) {
			isTabSwitch = true;
			e.preventDefault();
			e.stopPropagation();
			e.stopImmediatePropagation();
			
			switchTabManually(this);
			
			setTimeout(function() {
				if (window.history && window.history.replaceState) {
					window.history.replaceState(null, null, originalUrl);
				}
				isTabSwitch = false;
			}, 0);
			
			return false;
		}
	});
	
	// Monitor URL and remove hash if added (but don't touch history)
	setInterval(function() {
		// Only monitor if we're on a CRUD edit/create page
		if ($('.crud .nav-tabs').length > 0 || $('[bp-section="crud-operation-update"] .nav-tabs').length > 0 || $('[bp-section="crud-operation-create"] .nav-tabs').length > 0) {
			// Check if URL has hash and remove it using replaceState (doesn't add to history)
			if (window.location.hash) {
				if (window.history && window.history.replaceState) {
					window.history.replaceState(null, null, originalUrl);
				}
			}
		}
	}, 50);

	if ($(".summernote").length > 0) {
		$(".summernote").summernote({
			toolbar: [
				["style", ["style"]], // Style
				["font", ["bold", "italic", "underline", "clear"]],
				["fontsize", ["fontsize"]],
				["color", ["color"]],
				["para", ["ul", "ol", "paragraph", "height"]],
				["insert", ["link", "picture", "video", "table", "hr"]],
				["format", ["strikethrough", "superscript", "subscript"]],
				["view", ["fullscreen", "codeview", "help"]],
				["direction", ["ltr", "rtl"]],
			],
			callbacks: {
				onBlur: function () {
					var $editor = $(this);
					var content = $editor.summernote("code");

					// Check if content doesn't start with <p>
					if (!/^<p>/.test(content)) {
						// Save cursor position
						var range = window.getSelection().getRangeAt(0);

						// Prepend <p> if it doesn't exist
						content = "<p>" + content;

						// Set the updated content back to the editor
						$editor.summernote("code", content);

						// Restore cursor position
						var newRange = document.createRange();
						newRange.setStart(range.startContainer, range.startOffset);
						newRange.setEnd(range.endContainer, range.endOffset);

						var sel = window.getSelection();
						sel.removeAllRanges();
						sel.addRange(newRange);
					}
				},
			},
		});
	}

	$(".complete_task").on("click", function () {
		let step = $(this).data("step");
		let confirmTitle = $(this).data("confirm-title");
		let confirmMessage = $(this).data("confirm-message");
		let successTitle = $(this).data("success-title");
		let successMessage = $(this).data("success-message");
		let achievement = $(this).data("achievement");
		let achievementGif = $(this).data("achievement-gif");
		let completeButton = $(this).data("complete-button");
		let cancelButton = $(this).data("cancel-button");
		let successButton = $(this).data("success-button");
		swal({
			title: confirmTitle,
			text: confirmMessage,
			icon: "info",
			buttons: {
				cancel: {
					text: cancelButton,
					visible: true,
					className: "bg-secondary",
					closeModal: true,
				},
				confirm: {
					text: completeButton,
					visible: true,
					className: "bg-primary",
				},
			},
		}).then((isConfirmed) => {
			if (isConfirmed) {
				let steps = localStorage.getItem("steps");
				if (!steps) {
					steps = "";
				}
				if (!steps.includes(step)) {
					if (steps === "") {
						localStorage.setItem("steps", step);
					} else {
						localStorage.setItem("steps", steps + "," + step);
					}
				}
				if (localStorage.getItem("steps")) {
					const steps = localStorage.getItem("steps").split(",");
					for (let i = 0; i < steps.length; i++) {
						$(steps[i]).addClass("completed_task");
						$(steps[i])
							.find(".complete_task")
							.prop("disabled", true)
							.addClass("pe-none", false)
							.text(successButton);
					}
					swal({
						title: successTitle,
						content: (function () {
							let wrapper = document.createElement("div");
							wrapper.innerHTML = `
                            ${successMessage}<br>
                            <span class="badge bg-success mt-3 text-white" style="font-size: 1.3rem;">
                                ${achievement}
                                <img src="${achievementGif}" class="img-fluid" 
                                    style="width: 35px; height: 35px; margin-left: 5px;">
                            </span>`;
							return wrapper;
						})(),
						icon: "success",
						timer: 5000,
						buttons: false,
					});
				}
			}
		});
	});

	// Only handle autocomplete for value filters, not relation filters (which use vanilla JS)
	// Use event delegation to handle dynamically loaded elements (like in collapsed panels)
	// Ensure jQuery is loaded before setting up event listeners
	(function() {
		function setupAutocomplete() {
			if (typeof jQuery === 'undefined' && typeof $ === 'undefined') {
				setTimeout(setupAutocomplete, 100);
				return;
			}
			
			const $ = typeof jQuery !== 'undefined' ? jQuery : window.$;
			
			
			// Test if elements exist
			setTimeout(function() {
				const testInputs = document.querySelectorAll('.autocomplete-input:not(.relation-autocomplete-input)');
				testInputs.forEach(function(input, idx) {
				});
			}, 1000);
			
			$(document).on("input keyup", ".autocomplete-input:not(.relation-autocomplete-input)", function (e) {
		let input = $(this);
		let columnName = input.data("column");
		let tableName = input.data("table"); // Get table from data attribute
		let query = input.val();
		let suggestionBox = $("#autocomplete-" + columnName);


		// Check if suggestion box exists
		if (suggestionBox.length === 0) {	
			return;
		}

		if (query.length < 4) {
			suggestionBox.hide();
			return;
		}

		// Get autocomplete URL from data attribute or construct it
		let autocompleteUrl = input.data("autocomplete-url");
		
		if (!autocompleteUrl) {
			// Fallback: construct URL from current path
			const pathParts = window.location.pathname.split('/').filter(p => p);
			const adminIndex = pathParts.indexOf('admin');
			if (adminIndex !== -1) {
				autocompleteUrl = '/' + pathParts.slice(0, adminIndex + 1).join('/') + '/autocomplete-values';
			} else {
				autocompleteUrl = '/admin/autocomplete-values';
			}
		}
		
		
		$.ajax({
			url: autocompleteUrl,
			data: { term: query, column: columnName, table: tableName }, // Pass column + table
			dataType: "json",
			success: function (data) {
				if (data && data.length > 0) {
					let suggestions = "";
					data.forEach(function (value) {
						suggestions += "<div>" + value + "</div>";
					});
					suggestionBox.html(suggestions).show();

					// Click event for selecting a suggestion
					suggestionBox.find("div").on("click", function () {
						input.val($(this).text());
						suggestionBox.hide();
					});
				} else {
					suggestionBox.hide();
				}
			},
			error: function(xhr, status, error) {
				suggestionBox.hide();
			}
		});
			});
		}
		
		// Try to setup immediately, or wait for DOM
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', setupAutocomplete);
		} else {
			setupAutocomplete();
		}
	})();

	// Hide suggestions if clicked outside
	$(document).on("click", function (e) {
		if (!$(e.target).closest(".autocomplete-input, .autocomplete-suggestions").length) {
			$(".autocomplete-suggestions").hide();
		}
	});

	// Accordion animation for filters
	initFilterAccordionAnimations();

	$(".radio_layout").on("mouseover", function () {
		var radio = $(this);
		var imgSrc = radio.data("img");
		var borderColor = radio.data("border") === "success" ? "#42ba96" : "#00627C";

		// Create the preview box
		var previewBox = $("<div>")
			.addClass("radio-preview")
			.css({
				"position": "absolute",
				"border": "3px solid " + borderColor,
				"border-radius": "10px",
				"padding": "10px",
				"background-color": "#fff",
				"box-shadow": "0px 4px 20px rgba(0, 0, 0, 0.3)",
				"z-index": "999",
				"opacity": "0",
				"display": "block",
				"transform": "scale(0.95)",
				"transition": "opacity 0.5s, transform 0.5s",
			});

		// Create the image element and wait for it to load
		var img = $("<img>").attr("src", imgSrc).css("max-width", "300px");

		img.on("load", function () {
			previewBox.append(img);
			$("body").append(previewBox);

			// Positioning Logic AFTER image is loaded
			var radioOffset = radio.offset();
			var radioWidth = radio.outerWidth();
			var previewWidth = previewBox.outerWidth();
			var previewHeight = previewBox.outerHeight();
			var windowWidth = $(window).width();
			var windowHeight = $(window).height();
			var scrollTop = $(window).scrollTop();

			var leftPosition = radioOffset.left + radioWidth + 20; // Default right-side positioning
			var topPosition = radioOffset.top - 5;

			// Adjust if overflowing right
			if (leftPosition + previewWidth > windowWidth) {
				leftPosition = radioOffset.left - previewWidth - 20; // Move to the left
			}

			// Adjust if overflowing bottom
			if (topPosition + previewHeight > windowHeight + scrollTop) {
				topPosition = windowHeight + scrollTop - previewHeight - 20; // Move up
			}

			previewBox.css({
				top: topPosition,
				left: leftPosition,
			});

			// Fade-in effect
			setTimeout(function () {
				previewBox.css({
					opacity: 1,
					transform: "scale(1)",
				});
			}, 10);
		});
	});

	// Keep preview visible while scrolling
	$(document).on("mouseleave", ".radio_layout, .radio-preview", function () {
		var previewBox = $(".radio-preview");
		previewBox.css({
			opacity: 0,
			transform: "scale(0.95)",
		});
		setTimeout(function () {
			previewBox.remove();
		}, 500);
	});
});

/**
 * Initializes accordion animations for filter panels
 */
function initFilterAccordionAnimations() {
	const accordionItems = $("#filtersAccordion .accordion-item");

	// Skip if no accordion items found
	if (!accordionItems.length) return;

	// Function to update the visual state of accordion items
	function updateAccordionState() {
		let hasExpandedItem = false;
		let expandedItem = null;

		// First, find if any item is expanded
		accordionItems.each(function () {
			const button = $(this).find(".accordion-button");
			if (button.length && !button.hasClass("collapsed")) {
				hasExpandedItem = true;
				expandedItem = $(this);
			}
		});

		// Then, apply the appropriate classes based on the state
		accordionItems.each(function () {
			if (hasExpandedItem) {
				if (this === expandedItem[0]) {
					$(this).addClass("expanded").removeClass("compressed");
				} else {
					$(this).addClass("compressed").removeClass("expanded");
				}
			} else {
				// If no item is expanded, remove all special classes
				$(this).removeClass("expanded compressed");
			}
		});
	}

	// Run once on page load to set initial state
	updateAccordionState();

	// Add event listener for Bootstrap's events
	accordionItems.each(function () {
		const item = $(this);
		const collapse = item.find(".accordion-collapse");

		if (collapse.length) {
			// When any accordion starts to show
			collapse.on("show.bs.collapse", function () {
				// First make all items compressed
				accordionItems.addClass("compressed").removeClass("expanded");

				// Then mark this one as expanded
				item.removeClass("compressed").addClass("expanded");
			});

			// When any accordion starts to hide
			collapse.on("hide.bs.collapse", function () {
				// If this was the expanded one, reset all items
				if (item.hasClass("expanded")) {
					accordionItems.removeClass("compressed expanded");
				}
			});

			// After transitions complete
			collapse.on("hidden.bs.collapse shown.bs.collapse", updateAccordionState);
		}
	});

	// Also attach to the buttons directly for immediate feedback
	$("#filtersAccordion .accordion-button").on("click", function () {
		// Small delay to allow Bootstrap to update its classes first
		setTimeout(updateAccordionState, 50);
	});
}

// We intentionally do NOT append "#<id>" to CRUD subheadings.
// Subheading is handled server-side to respect model display priorities.

// Keep the BelongsTo "preview link" (next to plain selects in the Relazioni tab)
// pointing at the currently selected related record. The select carries a
// data-route-keys JSON map (owner key value -> related route key).
$(document).on("change", "select.relation-preview-select[data-route-keys]", function () {
	const select = this;
	const field = select.getAttribute("data-relation-field") || select.name;
	const link = document.querySelector(
		'.js-relation-preview-link[data-relation-field="' + field + '"]',
	);
	if (!link) return;

	let routeKeys = {};
	try {
		routeKeys = JSON.parse(select.getAttribute("data-route-keys") || "{}");
	} catch (e) {
		routeKeys = {};
	}

	const routeKey = routeKeys[select.value];
	if (routeKey !== undefined && routeKey !== null && routeKey !== "") {
		link.href =
			link.getAttribute("data-base-path") +
			link.getAttribute("data-route-name") +
			"/" +
			routeKey +
			"/edit";
		link.removeAttribute("disabled");
		link.removeAttribute("aria-disabled");
	} else {
		link.href = "#";
		link.setAttribute("disabled", "");
		link.setAttribute("aria-disabled", "true");
	}
});

// Disabled preview links must not navigate.
$(document).on("click", ".js-relation-preview-link[disabled]", function (event) {
	event.preventDefault();
});
