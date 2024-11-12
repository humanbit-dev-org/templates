<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupDetailController;

// Route::middleware(['auth:sanctum'])->group(function () {
//     Route::get('/user', function (Request $request) {
//         return $request->user();
//     });
//     Route::get('/groups', [GroupController::class, 'index']);
//     Route::get('/group/{id}', [GroupDetailController::class, 'index']);
//     Route::post('/group/{id}/send-invite', [GroupDetailController::class, 'sendInvite']);
//     Route::post('/group/{id}/invite', [GroupDetailController::class, 'acceptInvite']);
//     Route::post('/create-groups', [GroupController::class, 'create']);
// });

Route::get('/data', [GroupController::class, 'index']);
