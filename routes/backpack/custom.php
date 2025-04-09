<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Admin\Helper\HelperBackend;
use App\Http\Controllers\Admin\OrderUserCrudController;
use App\Http\Controllers\Admin\TranslateCrudController;
use App\Http\Controllers\Admin\Services\GitHubController;
use App\Http\Controllers\Admin\Helper\DuplicateController;
use App\Http\Controllers\Admin\Helper\ExportCsvController;
use App\Http\Controllers\Admin\Helper\AutocompleteController;

// --------------------------
// Custom Backpack Routes
// --------------------------
// This route file is loaded automatically by Backpack\CRUD.
// Routes you generate using Backpack\Generators will be placed here.

Route::group(
	[
		"namespace" => "App\Http\Controllers\Admin",
		"middleware" => config("backpack.base.web_middleware", "web"),
		"prefix" => config("backpack.base.route_prefix", "admin"),
	],
	function () {
		if (config("backpack.base.setup_auth_routes")) {
			Route::get("login", "Auth\LoginController@showLoginForm")->name("backpack.auth.login");
			Route::post("login", "Auth\LoginController@login");
			Route::get("logout", "Auth\LoginController@logout")->name("backpack.auth.logout");
			Route::post("logout", "Auth\LoginController@logout");

			Route::get("register", "Auth\RegisterController@showRegistrationForm")->name("backpack.auth.register");
			Route::post("register", "Auth\RegisterController@register");

			if (config("backpack.base.setup_email_verification_routes", false)) {
				Route::get("email/verify", "Auth\VerifyEmailController@emailVerificationRequired")->name(
					"verification.notice"
				);
				Route::get("email/verify/{id}/{hash}", "Auth\VerifyEmailController@verifyEmail")->name(
					"verification.verify"
				);
				Route::post("email/verification-notification", "Auth\VerifyEmailController@resendVerificationEmail")
					->name("verification.send")
					->middleware(["throttle:50,1"]);
			}
		}
	}
);

Route::group(
	[
		"prefix" => config("backpack.base.route_prefix", "admin"),
		"middleware" => array_merge(
			(array) config("backpack.base.web_middleware", "web"),
			(array) config("backpack.base.middleware_key", "admin")
		),
		"namespace" => "App\Http\Controllers\Admin",
	],
	function () {
		//DOCUMENTATION
		Route::get("documentation", [GitHubController::class, "index"]);
		Route::get("documentation/{page}", [GitHubController::class, "index"]);

		//CUSTOM ROUTES FOR ADVANCED CRUDS

		Route::post("ajax-request/{elemdId}", [HelperBackend::class, "getDataByAjax"]);
		Route::post("{modelName}/sort", [HelperBackend::class, "sort"]);
		Route::get("{crud}/{id}/duplicate", [DuplicateController::class, "duplicate"]);
		Route::get("{crud}/export-csv", [ExportCsvController::class, "exportCrudToCsv"]);
		Route::get("autocomplete-values", [AutocompleteController::class, "getValues"]);

		Route::post("contact/{id}/{option}", [ContactController::class, "contactOption"]);

		Route::crud("user", "UserCrudController");
		Route::crud("role", "RoleCrudController");
		Route::crud("translate", "TranslateCrudController");
		Route::crud("page", "PageCrudController");
		Route::crud("article", "ArticleCrudController");
		Route::crud("institutional", "InstitutionalCrudController");
		Route::crud("seo-meta-information", "SeoMetaInformationCrudController");
		Route::crud("media", "MediaCrudController");
		Route::crud("attachment", "AttachmentCrudController");
		Route::crud("contact", "ContactCrudController");
	}
); // this should be the absolute last line of this file

/**
 * DO NOT ADD ANYTHING HERE.
 */
