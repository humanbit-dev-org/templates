<?php

use Illuminate\Support\Facades\Route;

if (app()->isLocal()) {
	Route::get("/", function () {
		return ["Laravel" => app()->version()];
	});
}

require __DIR__ . "/auth.php";
