<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Souscription;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function stats(Request $request)
    {
        return response()->json([
            'utilisateurs_total' => User::where('role', 'user')->count(),
            'utilisateurs_ce_mois' => User::where('role', 'user')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'plans_total' => Plan::count(),
            'souscriptions_actives' => Souscription::where('statut', 'actif')->count(),
            'souscriptions_en_attente' => Souscription::where('statut', 'en_attente')->count(),
            'revenu_total' => Souscription::where('statut', 'actif')->sum('montant'),
            'dernieres_souscriptions' => Souscription::with(['user:id,name,email', 'plan:id,nom'])
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }
}