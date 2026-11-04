<?php

use App\Http\Controllers\Admin\Action\CsvRollbackController;
use App\Http\Controllers\Admin\Action\CsvUploadController;
use App\Http\Controllers\Admin\Action\DataCleanupController;
use App\Http\Controllers\Admin\Action\DependencyAuditController;
use App\Http\Controllers\Admin\Action\ExportCsvAction;
use App\Http\Controllers\Admin\Action\ExportXlsxAction;
use App\Http\Controllers\Admin\Action\ImportCsvAction;
use App\Http\Controllers\Admin\Action\ImportXlsxAction;
use App\Http\Controllers\Admin\Action\LogAction;
use App\Http\Controllers\Admin\Action\ScheduledJobsController;
use App\Http\Controllers\Admin\StoredFileController;
use App\Http\Controllers\Admin\Support\AdminOperation;
use App\Http\Controllers\Admin\Support\AutocompleteOperation;
use App\Http\Controllers\Admin\Support\BelongsToSearchOperation;
use App\Http\Controllers\Admin\Support\BulkOperation;
use App\Http\Controllers\Admin\Support\DuplicateOperation;
use App\Http\Controllers\Admin\Support\RelationSearchOperation;
use App\Http\Controllers\Admin\SupportController;
use App\Support\Backpack\BackpackHelper;
use Illuminate\Support\Facades\Route;

// --------------------------
// Custom Backpack Routes
// --------------------------
// This route file is loaded automatically by Backpack\CRUD.
// Routes you generate using Backpack\Generators will be placed here.

Route::group(
	[
		"namespace" => "App\Http\Controllers\Admin",
		"middleware" => array_merge(
			(array) config("backpack.base.web_middleware", "web"),
			["shield:backpack"] // IP restriction on login/logout routes too
		),
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

// The Auth Simulation (SAML) page lives in routes/restricted.php under
// /auth-simulation, outside the Backpack admin middleware.

Route::group(
	[
		"prefix" => config("backpack.base.route_prefix", "admin"),
		"name" => config("backpack.base.route_prefix", "admin") . ".",
		"middleware" => array_merge(
			(array) config("backpack.base.web_middleware", "web"),
			(array) config("backpack.base.middleware_key", "admin"),
			["shield:backpack"] // IP restriction via restricted_tools.php
		),
		"namespace" => "App\Http\Controllers\Admin",
	],
	function () {
		// CUSTOM ROUTES FOR ADVANCED CRUDS

		Route::get("logs", [LogAction::class, "index"]);
		Route::get("logs/show", [LogAction::class, "show"]);
		Route::get("logs/view", [LogAction::class, "view"]);
		Route::get("logs/export", [LogAction::class, "export"]);
		Route::post("logs/delete", [LogAction::class, "delete"]);
		Route::post("logs/delete-group", [LogAction::class, "deleteGroup"]);
		Route::post("logs/delete-all", [LogAction::class, "deleteAll"]);

		// CSV Upload routes
		Route::get("csv-upload", [CsvUploadController::class, "index"]);
		Route::post("csv-upload/upload", [CsvUploadController::class, "upload"]);

		// CSV Rollback routes
		Route::get("csv-rollback", [CsvRollbackController::class, "index"])->name("csv-rollback.index");
		Route::get("csv-rollback/preview", [CsvRollbackController::class, "preview"])->name("csv-rollback.preview");
		Route::get("csv-rollback/download", [CsvRollbackController::class, "download"])->name("csv-rollback.download");
		Route::post("csv-rollback/delete", [CsvRollbackController::class, "delete"])->name("csv-rollback.delete");
		Route::post("csv-rollback/restore", [CsvRollbackController::class, "restore"])->name("csv-rollback.restore");

		// Data Cleanup routes
		Route::get("data-cleanup", [DataCleanupController::class, "index"])->name("data-cleanup.index");
		Route::get("data-cleanup/preview/{commandId}", [DataCleanupController::class, "preview"])->name(
			"data-cleanup.preview"
		);
		Route::post("data-cleanup/execute/{commandId}", [DataCleanupController::class, "execute"])->name(
			"data-cleanup.execute"
		);

		// Scheduled Jobs
		Route::get("scheduled-jobs", [ScheduledJobsController::class, "index"])->name("scheduled-jobs.index");
		Route::get("scheduled-jobs/runs/{run}", [ScheduledJobsController::class, "show"])->name("scheduled-jobs.show");
		Route::post("scheduled-jobs/run", [ScheduledJobsController::class, "run"])->name("scheduled-jobs.run");

		// Dependency Audit
		Route::get("dependency-audit", [DependencyAuditController::class, "index"])->name("dependency-audit.index");

		Route::post("{modelName}/sort", [BackpackHelper::class, "sort"]);
		Route::get("{crud}/{id}/duplicate", [DuplicateOperation::class, "duplicate"]);
		Route::get("{crud}/export-csv", [ExportCsvAction::class, "exportCrudToCsv"]);
		Route::get("{crud}/export-xlsx", [ExportXlsxAction::class, "exportCrudToXlsx"]);

		Route::get("{crud}/import-csv", [ImportCsvAction::class, "showImportForm"]);
		Route::post("{crud}/import-csv/analyze", [ImportCsvAction::class, "analyzeUploadedFile"]);
		Route::post("{crud}/import-csv/preview", [ImportCsvAction::class, "previewImport"]);
		Route::post("{crud}/import-csv/process", [ImportCsvAction::class, "importCsv"]);
		Route::get("{crud}/import-csv/status", [ImportCsvAction::class, "getImportStatus"]);

		Route::get("{crud}/import-xlsx", [ImportXlsxAction::class, "showImportForm"]);
		Route::post("{crud}/import-xlsx/analyze", [ImportXlsxAction::class, "analyzeUploadedFile"]);

		Route::get("autocomplete-values", [AutocompleteOperation::class, "getValues"]);
		Route::get("autocomplete-relation-values", [AutocompleteOperation::class, "getRelationValues"]);

		// AJAX Relation Search Routes
		Route::get("ajax/relation-search", [RelationSearchOperation::class, "searchRelation"])->name(
			"admin.ajax.relation-search"
		);

		// AJAX BelongsTo Search Route (used when options > 25)
		Route::get("ajax/belongs-to-search", [BelongsToSearchOperation::class, "search"])->name(
			"admin.ajax.belongs-to-search"
		);

		// Bulk Operations Routes
		Route::get("{crud}/bulk-fields", [BulkOperation::class, "bulkFields"]);
		Route::post("{crud}/bulk-delete", [BulkOperation::class, "bulkDelete"]);
		Route::post("{crud}/bulk-duplicate", [BulkOperation::class, "bulkDuplicate"]);
		Route::post("{crud}/bulk-update", [BulkOperation::class, "bulkUpdate"]);

		// Dashboard Widget API Routes
		Route::get("users/search", [AdminOperation::class, "searchUsers"]);

		// Hourly per-admin limit: see the "admin-support" limiter in AppServiceProvider.
		Route::post("support", [SupportController::class, "store"])
			->middleware("throttle:admin-support")
			->name("support.store");

		Route::get("media-stream/{path}", [StoredFileController::class, "show"])
			->where("path", ".*")
			->name("media-stream.show");

		Route::get("stored-file/{path}", [StoredFileController::class, "show"])
			->where("path", ".*")
			->middleware("signed")
			->name("stored-file.show");

<<<<<<< HEAD
		Route::crud("user", "UserCrudController");
		Route::crud("user-two-factor", "UserTwoFactorCrudController");
		Route::crud("role", "RoleCrudController");
		Route::crud("translate", "TranslateCrudController");
		Route::crud("page", "PageCrudController");
		Route::crud("contatto", "ContattoCrudController");
		Route::crud("article", "ArticleCrudController");
		Route::crud("institutional", "InstitutionalCrudController");
		Route::crud("metadata", "MetadataCrudController");
		Route::crud("media", "MediaCrudController");
		Route::crud("attachment", "AttachmentCrudController");
		Route::crud("notification", "NotificationCrudController");
		Route::crud("backpack-role", "BackpackRoleCrudController");
		Route::crud("role", "RoleCrudController");
		Route::crud("model-permission", "ModelPermissionCrudController");
	}
=======
        Route::crud('user', 'UserCrudController');
        Route::crud('user-two-factor', 'UserTwoFactorCrudController');
        Route::crud('role', 'RoleCrudController');
        Route::crud('translate', 'TranslateCrudController');
        Route::crud('page', 'PageCrudController');
        Route::crud('contatto', 'ContattoCrudController');
        Route::crud('article', 'ArticleCrudController');
        Route::crud('institutional', 'InstitutionalCrudController');
        Route::crud('metadata', 'MetadataCrudController');
        Route::crud('media', 'MediaCrudController');
        Route::crud('attachment', 'AttachmentCrudController');
        Route::crud('notification', 'NotificationCrudController');
        Route::crud('backpack-role', 'BackpackRoleCrudController');
        Route::crud('model-permission', 'ModelPermissionCrudController');
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
); // this should be the absolute last line of this file

/**
 * DO NOT ADD ANYTHING HERE.
 */
