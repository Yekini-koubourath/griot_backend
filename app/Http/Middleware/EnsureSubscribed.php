<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Les admins ne sont jamais soumis au contrôle d'abonnement
        if ($user?->isAdmin()) {
            return $next($request);
        }

        // Interrupteur global : tant que "enforce" est à false,
        // on laisse passer tout le monde (le parcours reste visible côté front).
        if (!config('subscription.enforce', false)) {
            return $next($request);
        }

        $souscription = $user?->souscriptionActive();

        if (!$souscription) {
            return response()->json([
                'message' => 'Aucun abonnement actif.',
                'code' => 'SUBSCRIPTION_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}