<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $devise = strtoupper($request->query('devise', 'EUR'));
        $rates = config('currencies.rates', ['EUR' => 1]);
        $rate = $rates[$devise] ?? 1;

        $plans = Plan::where('statut', 'actif')
            ->orderBy('position')
            ->get()
            ->map(function (Plan $plan) use ($devise, $rate) {
                return [
                    'id' => $plan->id,
                    'nom' => $plan->nom,
                    'type' => $plan->type,
                    'description' => $plan->description,
                    'prix' => round($plan->prix * $rate, 2),
                    'devise' => $devise,
                    'duree' => $plan->duree,
                    'duree_unite' => $plan->duree_unite,
                    'trial' => $plan->trial,
                    'trial_duration' => $plan->trial_duration,
                    'features' => $plan->features,
                    'est_gratuit' => $plan->isGratuit(),
                ];
            });

        return response()->json(['plans' => $plans]);
    }
}