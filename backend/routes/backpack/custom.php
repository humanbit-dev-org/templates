<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\Helper\LogController;
use App\Http\Controllers\TiraggioController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\Helper\HelperBackend;
use App\Http\Controllers\Admin\Helper\DuplicateController;
use App\Http\Controllers\Admin\Helper\ExportCsvController;
use App\Http\Controllers\Admin\Helper\ImportCsvController;
use App\Http\Controllers\Admin\TiraggioManagementController;
use App\Http\Controllers\Admin\Helper\AutocompleteController;
use App\Http\Controllers\Admin\Helper\BulkOperationsController;

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

			// Two-Factor Authentication Routes (only if enabled)
			if (config("backpack.base.setup_two_factor_auth", false)) {
				Route::get("two-factor", "Auth\LoginController@showTwoFactorForm")->name("backpack.auth.two-factor");
				Route::post("two-factor/verify", "Auth\LoginController@verifyTwoFactor")->name(
					"backpack.auth.two-factor.verify"
				);
				Route::post("two-factor/resend", "Auth\LoginController@resendTwoFactorToken")->name(
					"backpack.auth.two-factor.resend"
				);
			}

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
		//CUSTOM ROUTES FOR ADVANCED CRUDS

		Route::get("logs", [LogController::class, "index"]);
		Route::get("logs/export", [LogController::class, "export"]);

		Route::post("{modelName}/sort", [HelperBackend::class, "sort"]);
		Route::get("{crud}/{id}/duplicate", [DuplicateController::class, "duplicate"]);
		Route::get("{crud}/export-csv", [ExportCsvController::class, "exportCrudToCsv"]);

		Route::get("{crud}/import-csv", [ImportCsvController::class, "showImportForm"]);
		Route::post("{crud}/import-csv/analyze", [ImportCsvController::class, "analyzeUploadedFile"]);
		Route::post("{crud}/import-csv/process", [ImportCsvController::class, "importCsv"]);
		Route::get("{crud}/import-csv/status", [ImportCsvController::class, "getImportStatus"]);

		Route::get("autocomplete-values", [AutocompleteController::class, "getValues"]);
		Route::get("autocomplete-relation-values", [AutocompleteController::class, "getRelationValues"]);

		// AJAX Relation Search Routes
		Route::get("ajax/relation-search", [
			\App\Http\Controllers\Admin\Ajax\RelationSearchController::class,
			"searchRelation",
		])->name("admin.ajax.relation-search");

		// Bulk Operations Routes
		Route::post("{crud}/bulk-delete", [BulkOperationsController::class, "bulkDelete"]);
		Route::post("{crud}/bulk-duplicate", [BulkOperationsController::class, "bulkDuplicate"]);

		// Dashboard Widget API Routes
		Route::get("users/search", [AdminController::class, "searchUsers"]);

		Route::crud("user", "UserCrudController");
		Route::crud("role", "RoleCrudController");
		Route::crud("translate", "TranslateCrudController");
		Route::crud("page", "PageCrudController");
		Route::crud("contatto", "ContattoCrudController");
		Route::crud("institutional", "InstitutionalCrudController");
		Route::crud("metadata", "MetadataCrudController");
		Route::crud("media", "MediaCrudController");
		Route::crud("attachment", "AttachmentCrudController");
		Route::crud("notification", "NotificationCrudController");
		Route::crud("backpack-role", "BackpackRoleCrudController");
		Route::crud("role", "RoleCrudController");
		Route::crud("model-permission", "ModelPermissionCrudController");
	}
); // this should be the absolute last line of this file

/**
 * DO NOT ADD ANYTHING HERE.
 */
