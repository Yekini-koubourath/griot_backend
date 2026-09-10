<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Souscription;
use Illuminate\Http\Request;

class SouscriptionController extends Controller
{
    public function current(Request $request)
    {
        $souscription = $request->user()
            ->souscriptions()
            ->with('plan')
            ->latest()
            ->first();

        return response()->json(['souscription' => $souscription]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'quantite' => ['nullable', 'integer', 'min:1'],
            'devise' => ['required', 'in:EUR,USD,XOF'],
            'mode_paiement' => ['required_unless:plan_est_gratuit,true', 'nullable', 'in:carte,mobile_money,virement'],
            'reference_paiement' => ['nullable', 'string', 'max:255'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        $quantite = $validated['quantite'] ?? 1;

        $rates = config('currencies.rates', ['EUR' => 1]);
        $rate = $rates[$validated['devise']] ?? 1;
        $montant = round($plan->prix * $rate * $quantite, 2);

        $estGratuit = $plan->isGratuit();

        if (!$estGratuit && empty($validated['mode_paiement'])) {
            return response()->json([
                'message' => 'Le mode de paiement est requis pour ce plan.',
                'errors' => ['mode_paiement' => ['Le mode de paiement est requis.']],
            ], 422);
        }

        $dateDebut = now();
        $dateFin = match ($plan->duree_unite) {
            'jour' => $dateDebut->copy()->addDays($plan->duree * $quantite),
            'annee' => $dateDebut->copy()->addYears($plan->duree * $quantite),
            default => $dateDebut->copy()->addMonths($plan->duree * $quantite),
        };

        $souscription = $request->user()->souscriptions()->create([
            'plan_id' => $plan->id,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'statut' => $estGratuit ? 'actif' : 'en_attente',
            'mode_paiement' => $validated['mode_paiement'] ?? null,
            'reference_paiement' => $validated['reference_paiement'] ?? null,
            'montant' => $montant,
            'devise' => $validated['devise'],
            'quantite' => $quantite,
            'details_paiement' => [
                'plan_nom' => $plan->nom,
                'prix_unitaire' => $plan->prix,
                'devise_reference' => $plan->devise,
            ],
        ]);

        return response()->json([
            'message' => $estGratuit
                ? 'Abonnement activé avec succès.'
                : 'Souscription enregistrée, en attente de validation du paiement.',
            'souscription' => $souscription->load('plan'),
        ], 201);
    }
}