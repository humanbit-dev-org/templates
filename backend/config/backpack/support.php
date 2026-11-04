<?php

return [
	/*
    |--------------------------------------------------------------------------
    | Admin panel support recipient
    |--------------------------------------------------------------------------
    |
    | Email address that receives support requests submitted from the Backpack
    | admin panel floating support widget.
    |
    */

	"recipient" => array_filter(
		array_map("trim", explode(",", env("MAIL_ADMIN_SUPPORT_ADDRESS", "borreca@humanbit.com,silveri@humanbit.com")))
	),

	/*
    |--------------------------------------------------------------------------
    | Rate limit (requests per hour per admin user)
    |--------------------------------------------------------------------------
    |
    | Applied by the "admin-support" rate limiter registered in
    | AppServiceProvider and keyed by the authenticated Backpack user.
    |
    */

	"throttle_per_hour" => (int) env("MAIL_ADMIN_SUPPORT_THROTTLE_PER_HOUR", 10),

	/*
    |--------------------------------------------------------------------------
    | Duplicate submission window (seconds)
    |--------------------------------------------------------------------------
    |
    | A request identical to one the same user sent within this window is
    | rejected, so a double click or a resubmitted form does not send the
    | same email twice.
    |
    */

	"duplicate_window_seconds" => (int) env("MAIL_ADMIN_SUPPORT_DUPLICATE_WINDOW", 60),

	/*
    |--------------------------------------------------------------------------
    | Request categories
    |--------------------------------------------------------------------------
    |
    | Keys accepted by the "category" select of the support form. Labels live
    | in lang/vendor/backpack/{locale}/support.php under "categories.<key>".
    |
    */

	"categories" => ["admin_panel", "platform", "bug", "other"],

	/*
    |--------------------------------------------------------------------------
    | Screenshot attachments
    |--------------------------------------------------------------------------
    |
    | Maximum number of images per request and maximum size of each image in
    | kilobytes. The PHP upload limits (upload_max_filesize / post_max_size)
    | must allow at least these sizes, see the Dockerfile.
    |
    */

	"max_screenshots" => 3,

	"screenshot_max_kb" => 5120,
];
