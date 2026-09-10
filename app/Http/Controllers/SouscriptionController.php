<?php

namespace App\Http\Controllers;

use App\Models\Plan;
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
            'duree_unite' => ['required', 'in:jour,mois,annee'],
            'quantite' => ['required', 'integer', 'min:1'],
            'devise' => ['required', 'in:EUR,USD,XOF'],
            'mode_paiement' => ['required_unless:plan_est_gratuit,true', 'nullable', 'in:carte,mobile_money,virement'],
            'reference_paiement' => ['nullable', 'string', 'max:255'],
            'details_paiement' => ['nullable', 'array'],
            'details_paiement.*' => ['nullable', 'string', 'max:255'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        $quantite = $validated['quantite'];
        $dureeUnite = $validated['duree_unite'];

        $estGratuit = $plan->isGratuit();

        if (!$estGratuit && empty($validated['mode_paiement'])) {
            return response()->json([
                'message' => 'Le mode de paiement est requis pour ce plan.',
                'errors' => ['mode_paiement' => ['Le mode de paiement est requis.']],
            ], 422);
        }

        $rates = config('currencies.rates', ['EUR' => 1]);
        $rate = $rates[$validated['devise']] ?? 1;
        $prixUnitaire = $plan->prixPourUnite($dureeUnite);
        $montant = $estGratuit ? 0 : round($prixUnitaire * $rate * $quantite, 2);

        $dateDebut = now();
        $dateFin = match ($dureeUnite) {
            'jour' => $dateDebut->copy()->addDays($quantite),
            'annee' => $dateDebut->copy()->addYears($quantite),
            default => $dateDebut->copy()->addMonths($quantite),
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
            'details_paiement' => array_merge($validated['details_paiement'] ?? [], [
                'plan_nom' => $plan->nom,
                'duree_unite' => $dureeUnite,
            ]),
        ]);

        return response()->json([
            'message' => $estGratuit
                ? 'Abonnement activé avec succès.'
                : 'Souscription enregistrée. Votre accès sera activé après validation du paiement.',
            'souscription' => $souscription->load('plan'),
        ], 201);
    }
}