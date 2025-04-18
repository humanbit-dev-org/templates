$(document).ready(function () {
    if ($('.summernote').length > 0) {
        $('.summernote').summernote({
            toolbar: [
                ['style', ['style']], // Style
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['fontsize', ['fontsize']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph', 'height']],
                ['insert', ['link', 'picture', 'video', 'table', 'hr']],
                ['format', ['strikethrough', 'superscript', 'subscript']],
                ['view', ['fullscreen', 'codeview', 'help']],
                ['direction', ['ltr', 'rtl']]
            ],
            callbacks: {
                onBlur: function () {
                    var $editor = $(this);
                    var content = $editor.summernote('code');

                    // Check if content doesn't start with <p>
                    if (!/^<p>/.test(content)) {
                        // Save cursor position
                        var range = window.getSelection().getRangeAt(0);

                        // Prepend <p> if it doesn't exist
                        content = '<p>' + content;

                        // Set the updated content back to the editor
                        $editor.summernote('code', content);

                        // Restore cursor position
                        var newRange = document.createRange();
                        newRange.setStart(range.startContainer, range.startOffset);
                        newRange.setEnd(range.endContainer, range.endOffset);

                        var sel = window.getSelection();
                        sel.removeAllRanges();
                        sel.addRange(newRange);
                    }
                }
            }
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
                if (localStorage.getItem('steps')) {
                    const steps = localStorage.getItem('steps').split(',');
                    for (let i = 0; i < steps.length; i++) {
                        $(steps[i]).addClass('completed_task');
                        $(steps[i]).find(".complete_task").prop('disabled', true).addClass('pe-none', false).text(successButton);
                    }
                    swal({
                        title: successTitle,
                        content: (function () {
                            let wrapper = document.createElement('div');
                            wrapper.innerHTML = `
                            ${successMessage}<br>
                            <span class="badge bg-success mt-3" style="font-size: 1.3rem;">
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

    $(".autocomplete-input").on("keyup", function () {
        let input = $(this);
        let columnName = input.data("column");
        let tableName = input.data("table");  // Get table from data attribute
        let query = input.val();
        let suggestionBox = $("#autocomplete-" + columnName);

        if (query.length < 4) {
            suggestionBox.hide();
            return;
        }

        $.ajax({
            url: "autocomplete-values",  // Original URL without path modifications
            data: { term: query, column: columnName, table: tableName }, // Pass column + table
            dataType: "json",
            success: function (data) {
                if (data.length > 0) {
                    let suggestions = "";
                    data.forEach(function (value) {
                        suggestions += '<div>' + value + '</div>';
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
            }
        });
    });

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
        var previewBox = $("<div>").addClass("radio-preview")
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
                "transition": "opacity 0.5s, transform 0.5s"
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
                "top": topPosition,
                "left": leftPosition
            });

            // Fade-in effect
            setTimeout(function () {
                previewBox.css({
                    "opacity": 1,
                    "transform": "scale(1)"
                });
            }, 10);
        });
    });

    // Keep preview visible while scrolling
    $(document).on("mouseleave", ".radio_layout, .radio-preview", function () {
        var previewBox = $(".radio-preview");
        previewBox.css({
            "opacity": 0,
            "transform": "scale(0.95)"
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
    const accordionItems = $('#filtersAccordion .accordion-item');

    // Skip if no accordion items found
    if (!accordionItems.length) return;

    // Function to update the visual state of accordion items
    function updateAccordionState() {
        let hasExpandedItem = false;
        let expandedItem = null;

        // First, find if any item is expanded
        accordionItems.each(function () {
            const button = $(this).find('.accordion-button');
            if (button.length && !button.hasClass('collapsed')) {
                hasExpandedItem = true;
                expandedItem = $(this);
            }
        });

        // Then, apply the appropriate classes based on the state
        accordionItems.each(function () {
            if (hasExpandedItem) {
                if (this === expandedItem[0]) {
                    $(this).addClass('expanded').removeClass('compressed');
                } else {
                    $(this).addClass('compressed').removeClass('expanded');
                }
            } else {
                // If no item is expanded, remove all special classes
                $(this).removeClass('expanded compressed');
            }
        });
    }

    // Run once on page load to set initial state
    updateAccordionState();

    // Add event listener for Bootstrap's events
    accordionItems.each(function () {
        const item = $(this);
        const collapse = item.find('.accordion-collapse');

        if (collapse.length) {
            // When any accordion starts to show
            collapse.on('show.bs.collapse', function () {
                // First make all items compressed
                accordionItems.addClass('compressed').removeClass('expanded');

                // Then mark this one as expanded
                item.removeClass('compressed').addClass('expanded');
            });

            // When any accordion starts to hide
            collapse.on('hide.bs.collapse', function () {
                // If this was the expanded one, reset all items
                if (item.hasClass('expanded')) {
                    accordionItems.removeClass('compressed expanded');
                }
            });

            // After transitions complete
            collapse.on('hidden.bs.collapse shown.bs.collapse', updateAccordionState);
        }
    });

    // Also attach to the buttons directly for immediate feedback
    $('#filtersAccordion .accordion-button').on('click', function () {
        // Small delay to allow Bootstrap to update its classes first
        setTimeout(updateAccordionState, 50);
    });
}

