<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TikTokAuthController;
use App\Http\Controllers\FacebookController;
use App\Http\Controllers\VerificationController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

/*
|--------------------------------------------------------------------------
| TikTok
|--------------------------------------------------------------------------
|
| IMPORTANT : ces routes sont atteintes par une navigation plein-écran
| (window.location.href), qui n'envoie jamais de header Authorization.
| On ne peut donc PAS les protéger avec auth:sanctum comme avant : le
| contrôleur résout lui-même l'utilisateur à partir d'un ?token=...
| ajouté par le frontend (voir TikTokAuthController::resolveAuthenticatedUser).
|
*/

Route::get('/auth/tiktok/redirect/{project}', [TikTokAuthController::class, 'redirect'])
    ->middleware('web')
    ->name('tiktok.redirect');

Route::get('/auth/tiktok/callback', [TikTokAuthController::class, 'callback'])
    ->middleware('web')
    ->name('tiktok.callback');

/*
|--------------------------------------------------------------------------
| Facebook
|--------------------------------------------------------------------------
|
| ATTENTION : FacebookController::redirect doit recevoir le même
| correctif que TikTokAuthController::redirect (résolution manuelle
| du token via ?token=..., voir le fichier TikTokAuthController.php
| fourni comme modèle). Son code source n'a pas été fourni ici, donc
| il n'a pas pu être corrigé automatiquement — sans ce correctif, le
| bouton "Connecter Facebook" aura exactement le même bug que TikTok.
|
*/

Route::get('/auth/facebook/redirect/{projectId}', [FacebookController::class, 'redirect'])
    ->middleware('web');

Route::get('/auth/facebook/callback', [FacebookController::class, 'callback'])
    ->middleware('web');