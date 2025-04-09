<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ContactController;
// use App\Http\Controllers\HeroController;
// use App\Http\Controllers\InstitutionalController;
use App\Http\Controllers\SeoMetaInformationController;
// use App\Http\Controllers\TranslateController;

Route::get("/{lang}/seo", [SeoMetaInformationController::class, "index"]);
Route::get("/{lang}/{page}/seo", [SeoMetaInformationController::class, "index"]);

Route::middleware(["auth:sanctum"])->group(function () {
	Route::get("/user", function (Request $request) {
		return response()->json(["user" => $request->user()]);
	});
});

Route::get("/{lang}/about-us", [ArticleController::class, "index"]);

Route::post("/contacts/corporate", [ContactController::class, "store"]);
