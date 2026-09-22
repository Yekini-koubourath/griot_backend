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
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MediaFolderController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\FacturesController;

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

Route::get('/debug-session-raw', function (Request $request) {
    return response()->json([
        'session_id' => $request->session()->getId(),
        'session_has_login' => $request->session()->has('login_web_' . sha1('App\Models\User')),
        'auth_check' => auth()->check(),
        'user_id' => auth()->id(),
    ]);
});
/*
|--------------------------------------------------------------------------
| Authentification publique
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Mot de passe oublié (nouveau)
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);


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

    Route::apiResource('publications', PublicationController::class);

     Route::get(
    '/medias',
    [MediaController::class, 'index']
);

Route::post(
    '/medias',
    [MediaController::class, 'store']
);

Route::get(
    '/medias/{media}/download',
    [MediaController::class, 'download']
);

Route::delete(
    '/medias/{media}',
    [MediaController::class, 'destroy']
);

Route::get(
    '/media-folders',
    [MediaFolderController::class, 'index']
);

Route::post(
    '/media-folders',
    [MediaFolderController::class, 'store']
);

Route::get('/analytics', [AnalyticsController::class, 'index']);

    // Vérification email : renvoyer l'email (nouveau)
    Route::post('/email/resend', [AuthController::class, 'resendVerification']);

    // Settings
    Route::get('/settings/profile', [SettingsController::class, 'profile']);
    Route::put('/settings/profile', [SettingsController::class, 'updateProfile']);

    Route::get('/settings/notifications', [SettingsController::class, 'notifications']);
    Route::put('/settings/notifications', [SettingsController::class, 'updateNotifications']);

    Route::get('/settings/preferences', [SettingsController::class, 'preferences']);
    Route::put('/settings/preferences', [SettingsController::class, 'updatePreferences']);

    Route::put('/settings/password', [SettingsController::class, 'updatePassword']);

    Route::post('/logout', [SettingsController::class, 'logout']);
    Route::get('/factures', [FacturesController::class, 'index']);
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


        Route::middleware('auth')->group(function () {
    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::post('/campaigns', [CampaignController::class, 'store']);
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show']);
    Route::put('/campaigns/{campaign}', [CampaignController::class, 'update']);
    Route::patch('/campaigns/{campaign}', [CampaignController::class, 'update']);
    Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy']);

    Route::post('/campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate']);
    Route::post('/campaigns/{campaign}/pause', [CampaignController::class, 'pause']);
    Route::post('/campaigns/{campaign}/resume', [CampaignController::class, 'resume']);
});
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