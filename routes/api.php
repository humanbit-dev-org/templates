<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ContactController;
// use App\Http\Controllers\HeroController;
// use App\Http\Controllers\InstitutionalController;
use App\Http\Controllers\MetadataController;
// use App\Http\Controllers\TranslateController;

Route::get("/{lang}/{page}/seo", [MetadataController::class, "index"]);

Route::middleware(["auth:sanctum"])->group(function () {
	Route::get("/user", function (Request $request) {
		return response()->json(["user" => $request->user()]);
	});
});
