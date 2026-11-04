<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\ArticleController;
// use App\Http\Controllers\HeroController;
// use App\Http\Controllers\InstitutionalController;
use App\Http\Controllers\MetadataController;
// use App\Http\Controllers\TranslateController;

Route::get("/{lang}/{page}/seo", [MetadataController::class, "index"])->name("seo.index");

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
