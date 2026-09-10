<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        $souscription = $request->user()?->souscriptionActive();

        if (!$souscription) {
            return response()->json([
                'message' => 'Aucun abonnement actif.',
                'code' => 'SUBSCRIPTION_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}