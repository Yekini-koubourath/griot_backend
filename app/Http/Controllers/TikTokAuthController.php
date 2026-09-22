<?php

namespace App\Http\Controllers;

use App\Models\CompteSocial;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class TikTokAuthController extends Controller
{
    /**
     * IMPORTANT :
     *
     * Cette route est atteinte via une navigation plein-écran
     * (window.location.href côté frontend), pas via un appel axios.
     * Une navigation de ce type n'envoie JAMAIS de header
     * "Authorization: Bearer ...", donc auth:sanctum ne peut pas
     * authentifier l'utilisateur de cette façon.
     *
     * On accepte donc aussi le token en query string (?token=...)
     * et on le résout manuellement via Sanctum. Le frontend doit
     * ajouter ce paramètre à l'URL (voir reseaux_sociaux/page.tsx).
     */
    private function resolveAuthenticatedUser(Request $request): ?User
    {
        if ($request->user()) {
            return $request->user();
        }

        $token = $request->query('token');

        if (!$token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            return null;
        }

        return $accessToken->tokenable;
    }

    public function redirect(Request $request, Project $project)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        $user = $this->resolveAuthenticatedUser($request);

        if (!$user) {
            return redirect($frontendUrl . '/auth/login');
        }

        // Vérifie que le projet appartient bien à l'utilisateur connecté
        if ($project->user_id !== $user->id) {
            abort(403);
        }

        /*
        |--------------------------------------------------------------------------
        | State anti-CSRF
        |--------------------------------------------------------------------------
        */

        $state = Str::random(40);

        /*
        |--------------------------------------------------------------------------
        | PKCE
        |--------------------------------------------------------------------------
        |
        | TikTok demande un code_verifier + code_challenge
        | pour le flux Desktop.
        |
        */

        $codeVerifier = Str::random(64);

        // TikTok demande SHA-256 en encodage hexadécimal
        $codeChallenge = hash('sha256', $codeVerifier);

        /*
        |--------------------------------------------------------------------------
        | Stockage en session
        |--------------------------------------------------------------------------
        */

        session([
            'tiktok_oauth_state' => $state,
            'tiktok_oauth_project_id' => $project->id,
            'tiktok_oauth_code_verifier' => $codeVerifier,
        ]);

        /*
        |--------------------------------------------------------------------------
        | URL TikTok
        |--------------------------------------------------------------------------
        */

        $params = http_build_query([
            'client_key' => config('services.tiktok.client_id'),
            'redirect_uri' => config('services.tiktok.redirect'),
            'response_type' => 'code',
            'scope' => 'user.info.basic,video.publish,video.upload',
            'state' => $state,

            // PKCE
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return redirect(
            'https://www.tiktok.com/v2/auth/authorize/?' . $params
        );
    }

    public function callback(Request $request)
    {
        $frontendUrl = config(
            'app.frontend_url',
            'http://localhost:3000'
        );

        /*
        |--------------------------------------------------------------------------
        | Récupération des données OAuth
        |--------------------------------------------------------------------------
        */

        $state = $request->query('state');

        $sessionState = session('tiktok_oauth_state');

        $projectId = session('tiktok_oauth_project_id');

        $codeVerifier = session('tiktok_oauth_code_verifier');

        /*
        |--------------------------------------------------------------------------
        | Nettoyage de la session OAuth
        |--------------------------------------------------------------------------
        */

        session()->forget([
            'tiktok_oauth_state',
            'tiktok_oauth_project_id',
            'tiktok_oauth_code_verifier',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Vérification du state
        |--------------------------------------------------------------------------
        */

        if (
            !$state ||
            !$sessionState ||
            !hash_equals($sessionState, $state)
        ) {
            return redirect(
                $frontendUrl . '/dashboard/reseaux_sociaux?erreur=oauth_invalide'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | L'utilisateur a annulé
        |--------------------------------------------------------------------------
        */

        if ($request->filled('error')) {
            return redirect(
                $frontendUrl . '/dashboard/reseaux_sociaux?erreur=connexion_annulee'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Vérification du code
        |--------------------------------------------------------------------------
        */

        $code = $request->query('code');

        if (!$code || !$projectId || !$codeVerifier) {
            return redirect(
                $frontendUrl . '/dashboard/reseaux_sociaux?erreur=code_manquant'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Vérification du projet
        |--------------------------------------------------------------------------
        */

        $project = Project::find($projectId);

        if (!$project) {
            return redirect(
                $frontendUrl . '/dashboard/reseaux_sociaux?erreur=projet_introuvable'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Échange du code contre les tokens TikTok
        |--------------------------------------------------------------------------
        */

        $tokenResponse = Http::asForm()->post(
            'https://open.tiktokapis.com/v2/oauth/token/',
            [
                'client_key' => config('services.tiktok.client_id'),
                'client_secret' => config('services.tiktok.client_secret'),

                'code' => $code,

                'grant_type' => 'authorization_code',

                'redirect_uri' => config('services.tiktok.redirect'),

                // PKCE
                'code_verifier' => $codeVerifier,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Vérification de la réponse TikTok
        |--------------------------------------------------------------------------
        */

        if (!$tokenResponse->successful()) {
            \Log::error('TikTok token error', [
                'status' => $tokenResponse->status(),
                'response' => $tokenResponse->json(),
            ]);

            return redirect(
                $frontendUrl . '/dashboard/reseaux_sociaux?project='
                . $project->id
                . '&erreur=token_invalide'
            );
        }

        $tokenData = $tokenResponse->json();

        /*
        |--------------------------------------------------------------------------
        | Récupération des tokens
        |--------------------------------------------------------------------------
        */

        $accessToken = $tokenData['access_token'] ?? null;

        $refreshToken = $tokenData['refresh_token'] ?? null;

        $expiresIn = $tokenData['expires_in'] ?? 86400;

        $openId = $tokenData['open_id'] ?? null;

        if (!$accessToken || !$openId) {
            \Log::error('TikTok token incomplet', [
                'response' => $tokenData,
            ]);

            return redirect(
                $frontendUrl . '/dashboard/reseaux_sociaux?project='
                . $project->id
                . '&erreur=token_incomplet'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Récupération du profil TikTok
        |--------------------------------------------------------------------------
        */

        $userInfoResponse = Http::withToken($accessToken)
            ->get(
                'https://open.tiktokapis.com/v2/user/info/',
                [
                    'fields' =>
                        'open_id,display_name,avatar_url,username',
                ]
            );

        $userInfo = $userInfoResponse->json(
            'data.user',
            []
        );

        /*
        |--------------------------------------------------------------------------
        | Enregistrement du compte TikTok
        |--------------------------------------------------------------------------
        */

        CompteSocial::updateOrCreate(
            [
                'project_id' => $project->id,
                'reseau' => 'tiktok',
                'compte_id' => $openId,
            ],
            [
                'user_id' => $project->user_id,

                'nom_affichage' =>
                    $userInfo['display_name']
                    ?? 'Compte TikTok',

                'nom_utilisateur' =>
                    $userInfo['username']
                    ?? null,

                'avatar_url' =>
                    $userInfo['avatar_url']
                    ?? null,

                'access_token' => $accessToken,

                'refresh_token' => $refreshToken,

                'token_expires_at' =>
                    now()->addSeconds($expiresIn),

                'meta' => $tokenData,

                'statut' => 'actif',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Retour vers la page Réseaux sociaux du projet
        |--------------------------------------------------------------------------
        */

        return redirect(
            $frontendUrl
            . '/dashboard/reseaux_sociaux?project='
            . $project->id
            . '&connecte=tiktok'
        );
    }
}