<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
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
         * Envoyer le mail de vérification.
         */
        $user->sendEmailVerificationNotification();

        Auth::login($user);

        $request->session()->regenerate();

        return response()->json([
            'message' => 'Inscription réussie. Un email de vérification vous a été envoyé.',
            'user' => $user,
            'email_verification_required' => true,
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Email ou mot de passe incorrect'
            ], 401);
        }

        $request->session()->regenerate();

        return response()->json([
            'message' => 'Connexion réussie',
            'user' => $request->user(),
        ]);
    }

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

    public function handleGoogleCallback(Request $request)
    {
        // Vérifier le state OAuth
        $state = $request->query('state');
        $sessionState = session('google_oauth_state');

        session()->forget('google_oauth_state');

        if (!$state || !$sessionState || !hash_equals($sessionState, $state)) {
            return response()->json([
                'message' => 'État OAuth invalide.'
            ], 419);
        }

        // Si Google retourne une erreur
        if ($request->filled('error')) {
            return response()->json([
                'message' => 'Connexion Google annulée.'
            ], 400);
        }

        // Récupérer le code envoyé par Google
        $code = $request->query('code');

        if (!$code) {
            return response()->json([
                'message' => 'Code Google manquant.'
            ], 400);
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
            return response()->json([
                'message' => 'Impossible de valider la connexion Google.'
            ], 401);
        }

        $accessToken = $tokenResponse->json('access_token');

        if (!$accessToken) {
            return response()->json([
                'message' => 'Token Google manquant.'
            ], 401);
        }

        // Récupérer les informations du compte Google
        $googleUserResponse = Http::withToken($accessToken)
            ->get('https://openidconnect.googleapis.com/v1/userinfo');

        if (!$googleUserResponse->successful()) {
            return response()->json([
                'message' => 'Impossible de récupérer les informations Google.'
            ], 401);
        }

        $googleUser = $googleUserResponse->json();

        $email = $googleUser['email'] ?? null;
        $name = $googleUser['name'] ?? 'Utilisateur Google';

        if (!$email || !($googleUser['email_verified'] ?? false)) {
            return response()->json([
                'message' => 'Adresse email Google non vérifiée.'
            ], 403);
        }

        // Chercher l'utilisateur dans notre base
        $user = User::where('email', $email)->first();

        // Créer le compte s'il n'existe pas
        if (!$user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
            ]);

            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        } else {
            // Marquer l'email comme vérifié
            if (!$user->email_verified_at) {
                $user->forceFill([
                    'email_verified_at' => now(),
                ])->save();
            }
        }

        // Connecter l'utilisateur
        Auth::login($user);

        $request->session()->regenerate();

        // Déterminer la redirection selon le rôle et le statut d'abonnement
        $frontendUrl = config(
            'app.frontend_url',
            'http://localhost:3000'
        );

        if ($user->isAdmin()) {
            return redirect($frontendUrl . '/dashboard');
        }

        $souscription = $user->souscriptionActive();

        if ($souscription) {
            return redirect($frontendUrl . '/dashboard');
        }

        $derniereSouscription = $user->souscriptions()->latest()->first();

        if (
            $derniereSouscription &&
            $derniereSouscription->statut === 'en_attente'
        ) {
            return redirect($frontendUrl . '/auth/attente');
        }

        return redirect($frontendUrl . '/auth/abonnement');
    }
}
