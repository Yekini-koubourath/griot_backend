<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SouscriptionController;

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
Route::middleware(['auth:sanctum', 'subscribed'])->group(function () {
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
});
    Route::get('/plans', [PlanController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/souscriptions/current', [SouscriptionController::class, 'current']);
    Route::post('/souscriptions', [SouscriptionController::class, 'store']);
});