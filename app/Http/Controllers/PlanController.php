<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
   public function index(Request $request)
{
    $dureeUnite = $request->query('duree_unite', 'mois');

    if (!in_array($dureeUnite, ['jour', 'mois', 'annee'], true)) {
        $dureeUnite = 'mois';
    }

    $plans = Plan::where('statut', 'actif')
        ->orderBy('position')
        ->get()
        ->map(function (Plan $plan) use ($dureeUnite) {
            return [
                'id' => $plan->id,
                'nom' => $plan->nom,
                'type' => $plan->type,
                'description' => $plan->description,
                'prix' => round($plan->prixPourUnite($dureeUnite), 2),
                'devise' => $plan->devise,
                'duree_unite' => $dureeUnite,
                'trial' => $plan->trial,
                'trial_duration' => $plan->trial_duration,
                'features' => $plan->features,
                'est_gratuit' => $plan->isGratuit(),
            ];
        });

    return response()->json([
        'plans' => $plans,
    ]);
}

    public function show(Request $request, Plan $plan)
    {
        $devise = strtoupper($request->query('devise', 'EUR'));
        $dureeUnite = $request->query('duree_unite', 'mois');

        if (!in_array($dureeUnite, ['jour', 'mois', 'annee'], true)) {
            $dureeUnite = 'mois';
        }

        $rates = config('currencies.rates', ['EUR' => 1]);
        $rate = $rates[$devise] ?? 1;

        return response()->json([
            'plan' => $this->format($plan, $devise, $rate, $dureeUnite),
        ]);
    }

    private function format(Plan $plan, string $devise, float $rate, string $dureeUnite): array
    {
        return [
            'id' => $plan->id,
            'nom' => $plan->nom,
            'type' => $plan->type,
            'description' => $plan->description,
            'prix' => round($plan->prixPourUnite($dureeUnite) * $rate, 2),
            'devise' => $devise,
            'duree_unite' => $dureeUnite,
            'trial' => $plan->trial,
            'trial_duration' => $plan->trial_duration,
            'features' => $plan->features,
            'est_gratuit' => $plan->isGratuit(),
        ];
    }
}