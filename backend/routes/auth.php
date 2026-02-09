<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\UpdatedUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::post("/register", [RegisteredUserController::class, "store"])
	->middleware("guest")
	->name("register");

Route::get("/login", [AuthenticatedSessionController::class, "create"])
	->middleware("guest")
	->name("login");

Route::post("/login", [AuthenticatedSessionController::class, "store"])->middleware("guest");

Route::post("/forgot-password", [PasswordResetLinkController::class, "store"])
	->middleware("guest")
	->name("password.email");

Route::post("/reset-password", [NewPasswordController::class, "store"])
	->middleware("guest")
	->name("password.store");

Route::get("/verify-email/{id}/{hash}", VerifyEmailController::class)
	->middleware(["signed", "throttle:6,1"])
	->name("user.verification.verify");

Route::post("/email/verification-notification/{id}", [EmailVerificationNotificationController::class, "store"])
	->middleware(["throttle:5,1"])
	->name("user.verification.send");

Route::post("/logout", [AuthenticatedSessionController::class, "destroy"])
	->middleware("auth")
	->name("logout");

Route::post("/update-profile", [UpdatedUserController::class, "updateProfile"])
	->middleware("auth")
	->name("update-profile");

Route::post("/update-password", [UpdatedUserController::class, "updatePassword"])
	->middleware("auth")
	->name("update-password");
