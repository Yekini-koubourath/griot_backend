<?php

use App\Http\Controllers\CompteSocialController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SouscriptionController;
use App\Http\Controllers\Admin\AdminPlanController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminSouscriptionController;
use App\Http\Controllers\Admin\AdminUserController;

// Utilisateur connecté
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/debug-session', function (Request $request) {
    return response()->json([
        'session_id' => $request->session()->getId(),
        'user_id' => $request->user()?->id,
        'authenticated' => auth()->check(),
    ]);
})->middleware('auth:sanctum');

// Auth publique
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Plans (public)
Route::get('/plans', [PlanController::class, 'index']);

// Souscriptions (utilisateur connecté)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/souscriptions/current', [SouscriptionController::class, 'current']);
    Route::post('/souscriptions', [SouscriptionController::class, 'store']);
});

// Dashboard (connecté + abonné)
Route::middleware(['auth:sanctum', 'subscribed'])->group(function () {
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
});

// Admin (connecté + rôle admin)
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/plans', [AdminPlanController::class, 'index']);
    Route::post('/plans', [AdminPlanController::class, 'store']);
    Route::put('/plans/{plan}', [AdminPlanController::class, 'update']);
    Route::delete('/plans/{plan}', [AdminPlanController::class, 'destroy']);
});

Route::get('/plans', [PlanController::class, 'index']);
Route::get('/plans/{plan}', [PlanController::class, 'show']);

// Admin (connecté + rôle admin)
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/stats', [AdminController::class, 'stats']);

    Route::get('/plans', [AdminPlanController::class, 'index']);
    Route::post('/plans', [AdminPlanController::class, 'store']);
    Route::put('/plans/{plan}', [AdminPlanController::class, 'update']);
    Route::delete('/plans/{plan}', [AdminPlanController::class, 'destroy']);

    Route::get('/souscriptions', [AdminSouscriptionController::class, 'index']);
    Route::post('/souscriptions/{souscription}/valider', [AdminSouscriptionController::class, 'valider']);
    Route::post('/souscriptions/{souscription}/rejeter', [AdminSouscriptionController::class, 'rejeter']);

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::put('/users/{user}', [AdminUserController::class, 'update']);
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
});


Route::middleware(['auth:sanctum', 'subscribed'])->group(function () {
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);

    Route::get('/projects/{project}/comptes-sociaux', [CompteSocialController::class, 'index']);
    Route::delete('/projects/{project}/comptes-sociaux/{compte}', [CompteSocialController::class, 'destroy']);
});