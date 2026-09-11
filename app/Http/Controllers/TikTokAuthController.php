<?php

namespace App\Http\Controllers;

use App\Models\CompteSocial;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TikTokAuthController extends Controller
{
    public function redirect(Request $request, Project $project)
    {
        // Vérifie que le projet appartient bien à l'utilisateur connecté
        if ($project->user_id !== $request->user()->id) {
            abort(403);
        }

        $state = Str::random(40);

        session([
            'tiktok_oauth_state' => $state,
            'tiktok_oauth_project_id' => $project->id,
        ]);

        $params = http_build_query([
            'client_key' => config('services.tiktok.client_id'),
            'redirect_uri' => config('services.tiktok.redirect'),
            'response_type' => 'code',
            'scope' => 'user.info.basic,video.publish,video.upload',
            'state' => $state,
        ]);

        return redirect('https://www.tiktok.com/v2/auth/authorize/?' . $params);
    }

    public function callback(Request $request)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        $state = $request->query('state');
        $sessionState = session('tiktok_oauth_state');
        $projectId = session('tiktok_oauth_project_id');

        session()->forget(['tiktok_oauth_state', 'tiktok_oauth_project_id']);

        if (!$state || !$sessionState || !hash_equals($sessionState, $state)) {
            return redirect($frontendUrl . '/dashboard/projets?erreur=oauth_invalide');
        }

        if ($request->filled('error')) {
            return redirect($frontendUrl . '/dashboard/projets?erreur=connexion_annulee');
        }

        $code = $request->query('code');

        if (!$code || !$projectId) {
            return redirect($frontendUrl . '/dashboard/projets?erreur=code_manquant');
        }

        $project = Project::find($projectId);

        if (!$project) {
            return redirect($frontendUrl . '/dashboard/projets?erreur=projet_introuvable');
        }

        // Échange le code contre un access token
        $tokenResponse = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => config('services.tiktok.client_id'),
            'client_secret' => config('services.tiktok.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.tiktok.redirect'),
        ]);

        if (!$tokenResponse->successful()) {
            return redirect($frontendUrl . '/dashboard/projets?erreur=token_invalide');
        }

        $tokenData = $tokenResponse->json();

        $accessToken = $tokenData['access_token'] ?? null;
        $refreshToken = $tokenData['refresh_token'] ?? null;
        $expiresIn = $tokenData['expires_in'] ?? 86400;
        $openId = $tokenData['open_id'] ?? null;

        if (!$accessToken || !$openId) {
            return redirect($frontendUrl . '/dashboard/projets?erreur=token_incomplet');
        }

        // Récupère les infos du profil TikTok
        $userInfoResponse = Http::withToken($accessToken)
            ->get('https://open.tiktokapis.com/v2/user/info/', [
                'fields' => 'open_id,display_name,avatar_url,username',
            ]);

        $userInfo = $userInfoResponse->json('data.user', []);

        CompteSocial::updateOrCreate(
            [
                'project_id' => $project->id,
                'reseau' => 'tiktok',
                'compte_id' => $openId,
            ],
            [
                'user_id' => $project->user_id,
                'nom_affichage' => $userInfo['display_name'] ?? 'Compte TikTok',
                'nom_utilisateur' => $userInfo['username'] ?? null,
                'avatar_url' => $userInfo['avatar_url'] ?? null,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_expires_at' => now()->addSeconds($expiresIn),
                'meta' => $tokenData,
                'statut' => 'actif',
            ]
        );

        return redirect($frontendUrl . '/dashboard/projets/' . $project->id . '?connecte=tiktok');
    }
}