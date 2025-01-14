<?php

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\SeoMetaInformationController;

Route::get("/{lang}/seo", [SeoMetaInformationController::class, "index"]);

Route::middleware(["auth:sanctum"])->group(function () {
	Route::get("/user", function (Request $request) {
		return response()->json(["user" => $request->user()]);
	});
});
