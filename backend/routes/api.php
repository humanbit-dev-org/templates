<?php

use App\Http\Controllers\Api\MetadataController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

<<<<<<< HEAD
Route::get("/{lang}/{page}/seo", [MetadataController::class, "index"])->name("seo.index");
=======
// SEO metadata of a frontend page, in the requested language.
Route::get('/{lang}/{page}/seo', [MetadataController::class, 'index'])
    ->name('seo.index');
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

// Send password reset link via email (unauthenticated)
Route::post("/forgot-password", [PasswordResetLinkController::class, "store"])
	->middleware("throttle:password.forgot")
	->name("password.forgot");

// Validate token and set new password from email reset link (unauthenticated)
Route::post("/reset-password", [NewPasswordController::class, "store"])
	->middleware("throttle:password.reset")
	->name("password.reset");
Route::middleware(["auth:sanctum"])->group(function () {
	Route::middleware(["frontend.permission:User,read"])->get("/user", function (Request $request) {
		return response()->json(["user" => $request->user()]);
	});
});
