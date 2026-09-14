<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class AdminPlanController extends Controller
{
    /**
     * Liste de tous les plans.
     */
    public function index()
    {
        return response()->json([
            'plans' => Plan::orderBy('position')->get(),
        ]);
    }

    /**
     * Créer un nouveau plan.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:particulier,entreprise,agence'],
            'description' => ['nullable', 'string'],
            'prix' => ['required', 'numeric', 'min:0'],
            'devise' => ['required', 'in:EUR,USD,XOF'],
            'duree' => ['required', 'integer', 'min:1'],
            'duree_unite' => ['required', 'in:jour,mois,annee'],
            'position' => ['nullable', 'integer', 'min:0'],
            'statut' => ['required', 'in:actif,inactif'],
            'trial' => ['sometimes', 'boolean'],
            'trial_duration' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string'],
        ]);

        $plan = Plan::create($validated);

        return response()->json([
            'message' => 'Plan créé avec succès.',
            'plan' => $plan,
        ], 201);
    }

    /**
     * Modifier un plan.
     */
    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:particulier,entreprise,agence'],
            'description' => ['nullable', 'string'],
            'prix' => ['sometimes', 'numeric', 'min:0'],
            'devise' => ['sometimes', 'in:EUR,USD,XOF'],
            'duree' => ['sometimes', 'integer', 'min:1'],
            'duree_unite' => ['sometimes', 'in:jour,mois,annee'],
            'position' => ['nullable', 'integer', 'min:0'],
            'statut' => ['sometimes', 'in:actif,inactif'],
            'trial' => ['sometimes', 'boolean'],
            'trial_duration' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string'],
        ]);

        $plan->update($validated);

        return response()->json([
            'message' => 'Plan mis à jour avec succès.',
            'plan' => $plan->fresh(),
        ]);
    }

    /**
     * Supprimer un plan.
     */
    public function destroy(Plan $plan)
    {
        $nombreSouscriptions = $plan->souscriptions()->count();

        if ($nombreSouscriptions > 0) {
            return response()->json([
                'message' => 'Ce plan ne peut pas être supprimé car il possède des souscriptions.',
                'nombre_souscriptions' => $nombreSouscriptions,
            ], 422);
        }

        $plan->delete();

        return response()->json([
            'message' => 'Plan supprimé avec succès.',
        ]);
    }
}