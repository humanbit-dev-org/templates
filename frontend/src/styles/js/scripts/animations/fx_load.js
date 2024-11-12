/*!
 * Page loading animation fx_load.js
 */
export function fxLoad() {
    // Function to handle click event on internal anchor tags
    $('a:not([target="_blank"])').click(function (event) {
        const $link = $(this);
        let href = $link.attr("href");

        // Check if the link is internal (same origin)
        if (href && href.startsWith(window.location.origin) && !href.endsWith("#")) {
            // Check if Ctrl or Command key is pressed
            if (event.ctrlKey || event.metaKey) {
                return; // Let the default action happen (open in new tab)
            } else {
                event.preventDefault(); // Prevent default action of link click
            }

            // Add class to body for transition effect
            $(document.body).addClass("link");

            // Listen for the end of the animation on the body element
            $(document.body).one("animationend", function () {
                window.location.href = href; // Redirect to the link after the animation ends
            });
        }
    });

    // Restart animation on page load (including back and forward buttons navigation)
    $(window).on("pageshow", function (event) {
        if (event.originalEvent.persisted) {
            $(document.body).removeClass("fx_load link");
            setTimeout(function () {
                $(document.body).addClass("fx_load");
            }, 0);
        }
    });
}
