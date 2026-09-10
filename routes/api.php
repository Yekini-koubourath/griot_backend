<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Récupérer l'utilisateur connecté
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Route de diagnostic
Route::get('/debug-session', function (Request $request) {
    return response()->json([
        'session_id' => $request->session()->getId(),
        'user_id' => $request->user()?->id,
        'authenticated' => auth()->check(),
    ]);
})->middleware('auth:sanctum');

// Routes publiques
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Routes protégées
Route::get('/projects', [ProjectController::class, 'index'])
    ->middleware('auth:sanctum');
Route::post('/projects', [ProjectController::class, 'store'])
    ->middleware('auth:sanctum');