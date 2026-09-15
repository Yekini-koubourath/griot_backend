<?php

namespace App\Http\Controllers;

use App\Models\CompteSocial;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FacebookController extends Controller
{
    /**
     * Redirige l'utilisateur vers Facebook pour autoriser son compte.
     */
    public function redirect(Request $request, int $projectId)
    {
        $user = $request->user();

        $project = Project::where('id', $projectId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $state = Str::random(40);

        $request->session()->put('facebook_oauth_state', $state);
        $request->session()->put('facebook_project_id', $project->id);

        $query = http_build_query([
            'client_id' => config('services.facebook.client_id'),
            'redirect_uri' => config('services.facebook.redirect_uri'),
            'state' => $state,
            'scope' => implode(',', [
                'pages_show_list',
                'pages_read_engagement',
                'pages_manage_posts',
            ]),
            'response_type' => 'code',
        ]);

        return redirect(
            'https://www.facebook.com/v24.0/dialog/oauth?' . $query
        );
    }

    /**
     * Retour de Facebook après autorisation.
     */
    public function callback(Request $request)
    {
        $state = $request->query('state');
        $code = $request->query('code');

        $savedState = $request->session()->pull('facebook_oauth_state');
        $projectId = $request->session()->pull('facebook_project_id');

        if (!$state || !$code) {
            return redirect(
                $this->frontendUrl() . '/dashboard/reseaux_sociaux?facebook=error'
            );
        }

        if (!$savedState || !hash_equals($savedState, $state)) {
            return redirect(
                $this->frontendUrl() . '/dashboard/reseaux_sociaux?facebook=invalid_state'
            );
        }

        if (!$projectId) {
            return redirect(
                $this->frontendUrl() . '/dashboard/reseaux_sociaux?facebook=no_project'
            );
        }

        $project = Project::find($projectId);

        if (!$project) {
            return redirect(
                $this->frontendUrl() . '/dashboard/reseaux_sociaux?facebook=project_not_found'
            );
        }

        try {
            /**
             * 1. Récupérer l'access token utilisateur.
             */
            $tokenResponse = Http::get(
                'https://graph.facebook.com/v24.0/oauth/access_token',
                [
                    'client_id' => config('services.facebook.client_id'),
                    'client_secret' => config('services.facebook.client_secret'),
                    'redirect_uri' => config('services.facebook.redirect_uri'),
                    'code' => $code,
                ]
            );

            if (!$tokenResponse->successful()) {
                \Log::error('Facebook OAuth token error', [
                    'response' => $tokenResponse->json(),
                ]);

                return redirect(
                    $this->frontendUrl() . '/dashboard/reseaux_sociaux?facebook=token_error'
                );
            }

            $userAccessToken = $tokenResponse->json('access_token');

            /**
             * 2. Récupérer les Pages Facebook auxquelles
             *    l'utilisateur a accès.
             */
            $pagesResponse = Http::get(
                'https://graph.facebook.com/v24.0/me/accounts',
                [
                    'access_token' => $userAccessToken,
                    'fields' => 'id,name,access_token,picture',
                ]
            );

            if (!$pagesResponse->successful()) {
                \Log::error('Facebook Pages error', [
                    'response' => $pagesResponse->json(),
                ]);

                return redirect(
                    $this->frontendUrl() . '/dashboard/reseaux_sociaux?facebook=pages_error'
                );
            }

            $pages = $pagesResponse->json('data', []);

            if (empty($pages)) {
                return redirect(
                    $this->frontendUrl() . '/dashboard/reseaux_sociaux?facebook=no_page'
                );
            }

            /**
             * Pour le moment, on prend la première Page disponible.
             *
             * Plus tard, on pourra afficher une page de sélection
             * si l'utilisateur possède plusieurs Pages.
             */
            $page = $pages[0];

            $pageId = $page['id'] ?? null;
            $pageName = $page['name'] ?? 'Page Facebook';
            $pageAccessToken = $page['access_token'] ?? null;

            if (!$pageId || !$pageAccessToken) {
                return redirect(
                    $this->frontendUrl() . '/dashboard/reseaux_sociaux?facebook=invalid_page'
                );
            }

            /**
             * 3. Récupérer l'image de profil de la Page.
             */
            $avatarUrl = null;

            if (!empty($page['picture']['data']['url'])) {
                $avatarUrl = $page['picture']['data']['url'];
            }

            /**
             * 4. Enregistrer ou mettre à jour le compte Facebook
             *    pour ce projet.
             */
            CompteSocial::updateOrCreate(
                [
                    'project_id' => $project->id,
                    'reseau' => 'facebook',
                    'compte_id' => $pageId,
                ],
                [
                    'user_id' => $project->user_id,
                    'nom_affichage' => $pageName,
                    'nom_utilisateur' => null,
                    'avatar_url' => $avatarUrl,
                    'access_token' => $pageAccessToken,
                    'refresh_token' => null,
                    'token_expires_at' => null,
                    'meta' => [
                        'type' => 'facebook_page',
                        'page_id' => $pageId,
                        'page_name' => $pageName,
                        'scopes' => [
                            'pages_show_list',
                            'pages_read_engagement',
                            'pages_manage_posts',
                        ],
                    ],
                    'statut' => 'actif',
                ]
            );

            return redirect(
                $this->frontendUrl() . '/dashboard/reseaux_sociaux?facebook=connected&project=' . $project->id
            );
        } catch (\Throwable $e) {
            \Log::error('Facebook OAuth exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect(
                $this->frontendUrl() . '/dashboard/reseaux_sociaux?facebook=error'
            );
        }
    }

    /**
     * URL du frontend Next.js.
     */
    private function frontendUrl(): string
    {
        return rtrim(
            env('FRONTEND_URL', 'http://localhost:3000'),
            '/'
        );
    }
}