<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TikTokAuthController;
use App\Http\Controllers\FacebookController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

// routes/web.php — ajoute


Route::middleware(['web', 'auth:sanctum'])->group(function () {
    Route::get('/auth/tiktok/redirect/{project}', [TikTokAuthController::class, 'redirect'])
        ->name('tiktok.redirect');
});

Route::get('/auth/tiktok/callback', [TikTokAuthController::class, 'callback'])
    ->name('tiktok.callback');

    Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/facebook/redirect/{projectId}', [FacebookController::class, 'redirect']);
});

Route::get('/auth/facebook/callback', [FacebookController::class, 'callback']);