<?php

namespace App\Http\Controllers;

use App\Models\CompteSocial;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class FacebookController extends Controller
{
    /**
     * Résout l'utilisateur connecté.
     *
     * La connexion Facebook est lancée avec :
     *
     * window.location.href =
     * /auth/facebook/redirect/{project}?token=...
     *
     * Une navigation navigateur classique ne transmet pas
     * automatiquement le header Authorization: Bearer ...
     *
     * On accepte donc :
     * 1. l'utilisateur de la session Laravel
     * 2. le token Sanctum présent dans ?token=
     */
    private function resolveAuthenticatedUser(Request $request): ?User
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Vérifier d'abord la session Laravel
        |--------------------------------------------------------------------------
        */

        if ($request->user()) {
            return $request->user();
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Vérifier le token Sanctum
        |--------------------------------------------------------------------------
        */

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

    /**
     * Redirige l'utilisateur vers Facebook pour autoriser son compte.
     */
    public function redirect(Request $request, int $projectId)
    {
        /*
        |--------------------------------------------------------------------------
        | Récupération de l'utilisateur connecté
        |--------------------------------------------------------------------------
        */

        $user = $this->resolveAuthenticatedUser($request);

        \Log::info('FACEBOOK AUTH DEBUG', [
    'request_user_id' => optional($request->user())->id,
    'token_present' => $request->filled('token'),
    'resolved_user_id' => optional($user)->id,
]);

        /*
        |--------------------------------------------------------------------------
        | Si aucun utilisateur n'est trouvé
        |--------------------------------------------------------------------------
        */

        if (!$user) {
            return redirect(
                $this->frontendUrl()
                . '/auth/login?erreur=non_connecte'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Vérification du projet
        |--------------------------------------------------------------------------
        */

        $project = Project::where('id', $projectId)
            ->where('user_id', $user->id)
            ->first();

        if (!$project) {
            return redirect(
                $this->frontendUrl()
                . '/dashboard/reseaux_sociaux?facebook=project_not_found'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | State anti-CSRF
        |--------------------------------------------------------------------------
        */

        $state = Str::random(40);

        $request->session()->put(
            'facebook_oauth_state',
            $state
        );

        $request->session()->put(
            'facebook_project_id',
            $project->id
        );

        /*
        |--------------------------------------------------------------------------
        | URL Facebook
        |--------------------------------------------------------------------------
        */

        $query = http_build_query([
            'client_id' => config('services.facebook.client_id'),

            'redirect_uri' =>
                config('services.facebook.redirect_uri'),

            'state' => $state,

            'scope' => implode(',', [
                'pages_show_list',
                'pages_read_engagement',
                'pages_manage_posts',
            ]),

            'response_type' => 'code',
        ]);

        return redirect(
            'https://www.facebook.com/v24.0/dialog/oauth?'
            . $query
        );
    }

    /**
     * Retour de Facebook après autorisation.
     */
    public function callback(Request $request)
    {
        \Log::info('FACEBOOK CALLBACK DEBUG', [
    'has_code' => $request->filled('code'),
    'has_state' => $request->filled('state'),
    'has_error' => $request->filled('error'),
    'error' => $request->query('error'),
    'error_description' => $request->query('error_description'),
]);
        /*
        |--------------------------------------------------------------------------
        | URL frontend
        |--------------------------------------------------------------------------
        */

        $frontendUrl = $this->frontendUrl();

        /*
        |--------------------------------------------------------------------------
        | Récupération des données OAuth
        |--------------------------------------------------------------------------
        */

        $state = $request->query('state');

        $code = $request->query('code');

        /*
        |--------------------------------------------------------------------------
        | Récupération puis suppression des données de session
        |--------------------------------------------------------------------------
        */

        $savedState = $request->session()->pull(
            'facebook_oauth_state'
        );

        $projectId = $request->session()->pull(
            'facebook_project_id'
        );

        \Log::info('Facebook OAuth callback reçu', [
    'state_present' => !empty($state),
    'saved_state_present' => !empty($savedState),
    'project_id' => $projectId,
    'code_present' => !empty($code),
]);

        /*
        |--------------------------------------------------------------------------
        | Vérification d'une erreur Facebook
        |--------------------------------------------------------------------------
        */

        if ($request->filled('error')) {
            \Log::warning('Facebook OAuth annulé', [
                'error' => $request->query('error'),
                'description' =>
                    $request->query('error_description'),
            ]);

            return redirect(
                $frontendUrl
                . '/dashboard/reseaux_sociaux?facebook=cancelled'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Vérification du state
        |--------------------------------------------------------------------------
        */

        if (
            !$state ||
            !$savedState ||
            !hash_equals($savedState, $state)
        ) {
            \Log::warning(
                'Facebook OAuth state invalide'
            );

            return redirect(
                $frontendUrl
                . '/dashboard/reseaux_sociaux?facebook=invalid_state'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Vérification du code
        |--------------------------------------------------------------------------
        */

        if (!$code) {
            return redirect(
                $frontendUrl
                . '/dashboard/reseaux_sociaux?facebook=code_missing'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Vérification du projet
        |--------------------------------------------------------------------------
        */

        if (!$projectId) {
            return redirect(
                $frontendUrl
                . '/dashboard/reseaux_sociaux?facebook=no_project'
            );
        }

        $project = Project::find($projectId);

        if (!$project) {
            return redirect(
                $frontendUrl
                . '/dashboard/reseaux_sociaux?facebook=project_not_found'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Échange du code Facebook contre un access token
        |--------------------------------------------------------------------------
        */

        try {
            $tokenResponse = Http::get(
                'https://graph.facebook.com/v24.0/oauth/access_token',
                [
                    'client_id' =>
                        config('services.facebook.client_id'),

                    'client_secret' =>
                        config('services.facebook.client_secret'),

                    'redirect_uri' =>
                        config('services.facebook.redirect_uri'),

                    'code' => $code,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Vérification du token
            |--------------------------------------------------------------------------
            */

            if (!$tokenResponse->successful()) {
                \Log::error(
                    'Facebook OAuth token error',
                    [
                        'status' =>
                            $tokenResponse->status(),

                        'response' =>
                            $tokenResponse->json(),
                    ]
                );

                return redirect(
                    $frontendUrl
                    . '/dashboard/reseaux_sociaux?facebook=token_error&project='
                    . $project->id
                );
            }

            $userAccessToken =
                $tokenResponse->json('access_token');

            if (!$userAccessToken) {
                \Log::error(
                    'Facebook access token manquant',
                    [
                        'response' =>
                            $tokenResponse->json(),
                    ]
                );

                return redirect(
                    $frontendUrl
                    . '/dashboard/reseaux_sociaux?facebook=token_missing&project='
                    . $project->id
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Récupération des Pages Facebook
            |--------------------------------------------------------------------------
            */

            $pagesResponse = Http::get(
                'https://graph.facebook.com/v24.0/me/accounts',
                [
                    'access_token' =>
                        $userAccessToken,

                    'fields' =>
                        'id,name,access_token,picture',
                ]
            );

            if (!$pagesResponse->successful()) {
                \Log::error(
                    'Facebook Pages error',
                    [
                        'status' =>
                            $pagesResponse->status(),

                        'response' =>
                            $pagesResponse->json(),
                    ]
                );

                return redirect(
                    $frontendUrl
                    . '/dashboard/reseaux_sociaux?facebook=pages_error&project='
                    . $project->id
                );
            }

            $pages = $pagesResponse->json(
                'data',
                []
            );

            /*
            |--------------------------------------------------------------------------
            | Aucune Page Facebook
            |--------------------------------------------------------------------------
            */

            if (empty($pages)) {
                return redirect(
                    $frontendUrl
                    . '/dashboard/reseaux_sociaux?facebook=no_page&project='
                    . $project->id
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Pour le moment :
            | on utilise la première Page disponible.
            |--------------------------------------------------------------------------
            */

            $page = $pages[0];

            $pageId = $page['id'] ?? null;

            $pageName =
                $page['name']
                ?? 'Page Facebook';

            $pageAccessToken =
                $page['access_token']
                ?? null;

            /*
            |--------------------------------------------------------------------------
            | Vérification de la Page
            |--------------------------------------------------------------------------
            */

            if (
                !$pageId ||
                !$pageAccessToken
            ) {
                \Log::error(
                    'Facebook Page invalide',
                    [
                        'page' => $page,
                    ]
                );

                return redirect(
                    $frontendUrl
                    . '/dashboard/reseaux_sociaux?facebook=invalid_page&project='
                    . $project->id
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Récupération de l'avatar
            |--------------------------------------------------------------------------
            */

            $avatarUrl = null;

            if (
                !empty(
                    $page['picture']['data']['url']
                )
            ) {
                $avatarUrl =
                    $page['picture']['data']['url'];
            }

            /*
            |--------------------------------------------------------------------------
            | Enregistrement du compte Facebook
            |--------------------------------------------------------------------------
            */

            CompteSocial::updateOrCreate(
                [
                    'project_id' =>
                        $project->id,

                    'reseau' =>
                        'facebook',

                    'compte_id' =>
                        $pageId,
                ],
                [
                    'user_id' =>
                        $project->user_id,

                    'nom_affichage' =>
                        $pageName,

                    'nom_utilisateur' =>
                        null,

                    'avatar_url' =>
                        $avatarUrl,

                    'access_token' =>
                        $pageAccessToken,

                    'refresh_token' =>
                        null,

                    'token_expires_at' =>
                        null,

                    'meta' => [
                        'type' =>
                            'facebook_page',

                        'page_id' =>
                            $pageId,

                        'page_name' =>
                            $pageName,

                        'scopes' => [
                            'pages_show_list',
                            'pages_read_engagement',
                            'pages_manage_posts',
                        ],
                    ],

                    'statut' =>
                        'actif',
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Retour vers Réseaux sociaux
            |--------------------------------------------------------------------------
            */

            return redirect(
                $frontendUrl
                . '/dashboard/reseaux_sociaux?facebook=connected&project='
                . $project->id
            );
        } catch (\Throwable $e) {
            \Log::error(
                'Facebook OAuth exception',
                [
                    'message' =>
                        $e->getMessage(),

                    'trace' =>
                        $e->getTraceAsString(),
                ]
            );

            return redirect(
                $frontendUrl
                . '/dashboard/reseaux_sociaux?facebook=error&project='
                . $project->id
            );
        }
    }

    /**
     * URL du frontend Next.js.
     */
    private function frontendUrl(): string
    {
        return rtrim(
            config(
                'app.frontend_url',
                env(
                    'FRONTEND_URL',
                    'http://localhost:3000'
                )
            ),
            '/'
        );
    }
}