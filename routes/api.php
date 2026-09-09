<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['web', 'auth:sanctum']);

Route::get('/debug-session', function (Request $request) {
    return response()->json([
        'session_id' => $request->session()->getId(),
        'user_id' => $request->user()?->id,
        'authenticated' => auth()->check(),
    ]);
});
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('web');
Route::get('/projects', [ProjectController::class, 'index'])
    ->middleware(['web', 'auth:sanctum']);
Route::post('/projects', [ProjectController::class, 'store'])
    ->middleware(['web', 'auth:sanctum']);