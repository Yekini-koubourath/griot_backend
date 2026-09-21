<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class VerificationController extends Controller
{
    public function verify(Request $request, $id, $hash)
    {
        // Vérifie que le lien reçu par email est bien un lien signé Laravel.
        if (! $request->hasValidSignature()) {
            abort(403, 'Le lien de vérification est invalide ou a expiré.');
        }

        // Recherche l'utilisateur correspondant à l'ID présent dans le lien.
        $user = User::findOrFail($id);

        // Vérifie que le hash correspond bien à l'adresse email de l'utilisateur.
        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'Le lien de vérification est invalide.');
        }

        // Si l'adresse est déjà vérifiée, on redirige simplement.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect(
            config('app.frontend_url', 'http://localhost:3000')
            . '/auth/email-verifie'
        );
    }
}