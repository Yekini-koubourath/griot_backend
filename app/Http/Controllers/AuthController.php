<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    /**
     * Inscription classique
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        /*
         * Créer le token Sanctum.
         *
         * Le frontend utilisera ce token pour
         * les futures requêtes API.
         */
        $token = $user->createToken('griot-ai')->plainTextToken;

        /*
         * IMPORTANT : on ne bloque JAMAIS la réponse HTTP avec l'envoi d'email.
         *
         * defer() exécute ce code APRÈS que la réponse ait été envoyée au
         * frontend. Ainsi, même si le serveur SMTP est lent, injoignable ou
         * mal configuré, l'inscription reste rapide pour l'utilisateur.
         * L'erreur éventuelle est tout de même loguée pour debug.
         */
        defer(function () use ($user) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                \Log::error('Erreur envoi email de vérification', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        return response()->json([
            'message' => 'Inscription réussie. Un email de vérification vous a été envoyé.',
            'token' => $token,
            'user' => $user,
            'email_verification_required' => true,
        ], 201);
    }

    /**
     * Renvoyer l'email de vérification (utilisateur déjà connecté
     * mais pas encore vérifié).
     */
    public function resendVerification(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Cette adresse email est déjà vérifiée.',
            ]);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            \Log::error('Erreur renvoi email de vérification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => "Impossible d'envoyer l'email pour le moment. Réessayez dans quelques instants.",
            ], 500);
        }

        return response()->json([
            'message' => 'Email de vérification renvoyé.',
        ]);
    }

    /**
     * Demande de réinitialisation de mot de passe.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        /*
         * On déferre aussi l'envoi ici pour ne jamais faire attendre
         * l'utilisateur à cause d'un serveur mail lent.
         */
        defer(function () use ($request) {
            try {
                Password::sendResetLink($request->only('email'));
            } catch (\Throwable $e) {
                \Log::error('Erreur envoi email de réinitialisation', [
                    'email' => $request->email,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        // Réponse volontairement générique (ne révèle pas si l'email existe).
        return response()->json([
            'message' => 'Si un compte existe avec cette adresse, un email de réinitialisation a été envoyé.',
        ]);
    }

    /**
     * Réinitialisation effective du mot de passe.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Ce lien de réinitialisation est invalide ou a expiré.',
            ], 422);
        }

        return response()->json([
            'message' => 'Mot de passe réinitialisé avec succès.',
        ]);
    }

    /**
     * Connexion classique
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        /*
         * Chercher l'utilisateur.
         */
        $user = User::where('email', $credentials['email'])->first();

        /*
         * Vérifier le mot de passe.
         */
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Email ou mot de passe incorrect'
            ], 401);
        }

        /*
         * Vérifier que l'adresse email est confirmée.
         */
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Veuillez vérifier votre adresse email avant de vous connecter.',
                'email_verification_required' => true,
            ], 403);
        }

        /*
         * Créer un token Sanctum.
         */
        $token = $user->createToken('griot-ai')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Redirection vers Google
     */
    public function redirectToGoogle()
    {
        $state = Str::random(40);

        session(['google_oauth_state' => $state]);

        $params = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'select_account',
        ]);

        return redirect(
            'https://accounts.google.com/o/oauth2/v2/auth?' . $params
        );
    }

    /**
     * Callback Google
     *
     * IMPORTANT : ce flux est totalement indépendant du formulaire
     * d'inscription classique. Il ne lit ni n'écrit aucun champ du
     * formulaire, il crée/retrouve le compte uniquement à partir des
     * informations renvoyées par Google.
     *
     * Il renvoie désormais un vrai token Sanctum (comme login/register)
     * au lieu de s'appuyer sur une session/cookie Laravel. C'est ce qui
     * garantit que ça fonctionne pour tout le monde, en local comme en
     * production : le frontend fonctionne entièrement par token Bearer
     * stocké en localStorage (voir lib/axios.ts), pas par cookie de
     * session. Une session cross-domain est fragile en production
     * (SameSite, domaines différents front/back) ; un token ne l'est pas.
     */
    public function handleGoogleCallback(Request $request)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        // Vérifier le state OAuth
        $state = $request->query('state');
        $sessionState = session('google_oauth_state');

        session()->forget('google_oauth_state');

        if (!$state || !$sessionState || !hash_equals($sessionState, $state)) {
            return redirect($frontendUrl . '/auth/login?error=oauth_state');
        }

        // Si Google retourne une erreur
        if ($request->filled('error')) {
            return redirect($frontendUrl . '/auth/login?error=oauth_cancelled');
        }

        // Récupérer le code envoyé par Google
        $code = $request->query('code');

        if (!$code) {
            return redirect($frontendUrl . '/auth/login?error=oauth_missing_code');
        }

        // Échanger le code contre un access token
        $tokenResponse = Http::asForm()->post(
            'https://oauth2.googleapis.com/token',
            [
                'code' => $code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect'),
                'grant_type' => 'authorization_code',
            ]
        );

        if (!$tokenResponse->successful()) {
            return redirect($frontendUrl . '/auth/login?error=oauth_token');
        }

        $accessToken = $tokenResponse->json('access_token');

        if (!$accessToken) {
            return redirect($frontendUrl . '/auth/login?error=oauth_token');
        }

        // Récupérer les informations du compte Google
        $googleUserResponse = Http::withToken($accessToken)
            ->get('https://openidconnect.googleapis.com/v1/userinfo');

        if (!$googleUserResponse->successful()) {
            return redirect($frontendUrl . '/auth/login?error=oauth_userinfo');
        }

        $googleUser = $googleUserResponse->json();

        $email = $googleUser['email'] ?? null;
        $name = $googleUser['name'] ?? 'Utilisateur Google';

        if (!$email || !($googleUser['email_verified'] ?? false)) {
            return redirect($frontendUrl . '/auth/login?error=oauth_email_not_verified');
        }

        // Chercher l'utilisateur dans notre base, ou le créer.
        $user = User::where('email', $email)->first();

        if (!$user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
            ]);
        }

        // Un compte connecté via Google est considéré comme vérifié.
        if (!$user->email_verified_at) {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        }

        // Génère un token Sanctum comme pour login/register classique.
        $token = $user->createToken('griot-ai')->plainTextToken;

        return redirect(
            $frontendUrl . '/auth/social-callback?token=' . urlencode($token)
        );
    }
}