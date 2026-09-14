<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminSouscriptionController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompteSocialController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SouscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Utilisateur connecté
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Authentification publique
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


/*
|--------------------------------------------------------------------------
| Plans publics
|--------------------------------------------------------------------------
*/

Route::get('/plans', [PlanController::class, 'index']);
Route::get('/plans/{plan}', [PlanController::class, 'show']);


/*
|--------------------------------------------------------------------------
| Souscriptions utilisateur
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get(
        '/souscriptions/current',
        [SouscriptionController::class, 'current']
    );

    Route::post(
        '/souscriptions',
        [SouscriptionController::class, 'store']
    );
});


/*
|--------------------------------------------------------------------------
| Projets utilisateur
|--------------------------------------------------------------------------
|
| L'utilisateur doit :
| - être connecté
| - avoir une souscription active
|
*/

Route::middleware(['auth:sanctum', 'subscribed'])->group(function () {

    // Liste des projets
    Route::get(
        '/projects',
        [ProjectController::class, 'index']
    );

    // Création d'un projet
    Route::post(
        '/projects',
        [ProjectController::class, 'store']
    );

    // Voir un projet
    Route::get(
        '/projects/{project}',
        [ProjectController::class, 'show']
    );

    // Modifier un projet
    Route::put(
        '/projects/{project}',
        [ProjectController::class, 'update']
    );

    // Archiver un projet
    Route::put(
        '/projects/{project}/archive',
        [ProjectController::class, 'archive']
    );

    // Restaurer un projet archivé
    Route::put(
        '/projects/{project}/restore',
        [ProjectController::class, 'restore']
    );

    // Supprimer un projet
    Route::delete(
        '/projects/{project}',
        [ProjectController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | Comptes sociaux d'un projet
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/projects/{project}/comptes-sociaux',
        [CompteSocialController::class, 'index']
    );

    Route::delete(
        '/projects/{project}/comptes-sociaux/{compte}',
        [CompteSocialController::class, 'destroy']
    );
});


/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
|
| Toutes les routes ci-dessous nécessitent :
| - authentification Sanctum
| - rôle admin
|
*/

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard admin
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/stats',
            [AdminController::class, 'stats']
        );


        /*
        |--------------------------------------------------------------------------
        | Plans admin
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/plans',
            [AdminPlanController::class, 'index']
        );

        Route::post(
            '/plans',
            [AdminPlanController::class, 'store']
        );

        Route::put(
            '/plans/{plan}',
            [AdminPlanController::class, 'update']
        );

        Route::delete(
            '/plans/{plan}',
            [AdminPlanController::class, 'destroy']
        );


        /*
        |--------------------------------------------------------------------------
        | Souscriptions admin
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/souscriptions',
            [AdminSouscriptionController::class, 'index']
        );

        Route::post(
            '/souscriptions/{souscription}/valider',
            [AdminSouscriptionController::class, 'valider']
        );

        Route::post(
            '/souscriptions/{souscription}/rejeter',
            [AdminSouscriptionController::class, 'rejeter']
        );


        /*
        |--------------------------------------------------------------------------
        | Utilisateurs admin
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/users',
            [AdminUserController::class, 'index']
        );

        Route::put(
            '/users/{user}',
            [AdminUserController::class, 'update']
        );

        Route::delete(
            '/users/{user}',
            [AdminUserController::class, 'destroy']
        );
    });
