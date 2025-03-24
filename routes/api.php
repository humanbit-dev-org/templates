<?php

use App\Models\Order;
use App\Models\Translate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EcommerceController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\HeroController;
use App\Http\Controllers\InstitutionalController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SeoMetaInformationController;
use App\Http\Controllers\TranslateController;

Route::get("/{lang}/seo", [SeoMetaInformationController::class, "index"]);

Route::middleware(["auth:sanctum"])->group(function () {
	Route::get("/user", function (Request $request) {
		return response()->json(["user" => $request->user()]);
	});
	Route::get("/groups", [GroupController::class, "index"]);
	Route::get("/group/{id}", [GroupController::class, "detail"]);
	Route::post("/create-groups", [GroupController::class, "create"]);
	Route::get("/checkout/{id}", [OrderController::class, "index"]);
	Route::post("/{lang}/group/{id}/send-invite", [InviteController::class, "sendInvite"]);
	Route::post("/group/{id}/invite", [InviteController::class, "acceptInvite"]);
	Route::get("/invites", [InviteController::class, "index"]);
});

Route::get("/ecommerce", [EcommerceController::class, "index"]);
Route::get("/{lang}/about-us", [ArticleController::class, "index"]);
Route::get("/open-groups", [GroupController::class, "openGroups"]);

Route::post("/contacts/corporate", [ContactController::class, "store"]);
