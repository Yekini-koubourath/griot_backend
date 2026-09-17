<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    /**
     * Récupérer les informations du profil.
     */
    public function profile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'company' => $user->company,
                'avatar' => $user->avatar,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Modifier les informations du profil.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'company' => ['nullable', 'string', 'max:255'],
        ]);

        $user->first_name = $validated['first_name'] ?? null;
        $user->last_name = $validated['last_name'] ?? null;
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;
        $user->company = $validated['company'] ?? null;

        // On conserve "name" pour rester compatible
        // avec le reste de l'application.
        $fullName = trim(
            ($user->first_name ?? '') . ' ' . ($user->last_name ?? '')
        );

        if ($fullName !== '') {
            $user->name = $fullName;
        }

        $user->save();

        return response()->json([
            'message' => 'Profil mis à jour avec succès.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'company' => $user->company,
                'avatar' => $user->avatar,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Récupérer les préférences de notifications.
     */
    public function notifications(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'notifications' => [
                'publications' => (bool) $user->notification_publications,
                'reminders' => (bool) $user->notification_reminders,
                'analytics' => (bool) $user->notification_analytics,
                'marketing' => (bool) $user->notification_marketing,
            ],
        ]);
    }

    /**
     * Modifier les préférences de notifications.
     */
    public function updateNotifications(Request $request)
    {
        $validated = $request->validate([
            'publications' => ['required', 'boolean'],
            'reminders' => ['required', 'boolean'],
            'analytics' => ['required', 'boolean'],
            'marketing' => ['required', 'boolean'],
        ]);

        $user = $request->user();

        $user->notification_publications = $validated['publications'];
        $user->notification_reminders = $validated['reminders'];
        $user->notification_analytics = $validated['analytics'];
        $user->notification_marketing = $validated['marketing'];

        $user->save();

        return response()->json([
            'message' => 'Préférences de notifications mises à jour.',
            'notifications' => [
                'publications' => (bool) $user->notification_publications,
                'reminders' => (bool) $user->notification_reminders,
                'analytics' => (bool) $user->notification_analytics,
                'marketing' => (bool) $user->notification_marketing,
            ],
        ]);
    }

    /**
     * Récupérer les préférences générales.
     */
    public function preferences(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'preferences' => [
                'language' => $user->language,
                'timezone' => $user->timezone,
            ],
        ]);
    }

    /**
     * Modifier les préférences générales.
     */
    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'language' => ['required', 'in:Français,English'],
            'timezone' => ['required', 'in:GMT +0,GMT +1,GMT +2'],
        ]);

        $user = $request->user();

        $user->language = $validated['language'];
        $user->timezone = $validated['timezone'];

        $user->save();

        return response()->json([
            'message' => 'Préférences mises à jour avec succès.',
            'preferences' => [
                'language' => $user->language,
                'timezone' => $user->timezone,
            ],
        ]);
    }

    /**
     * Modifier le mot de passe.
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8),
            ],
        ]);

        $user = $request->user();

        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json([
            'message' => 'Mot de passe modifié avec succès.',
        ]);
    }

    /**
     * Déconnecter l'utilisateur.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }
}