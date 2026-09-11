<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class AdminPlanController extends Controller
{
    public function index()
    {
        return response()->json(['plans' => Plan::orderBy('position')->get()]);
    }

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
            'position' => ['nullable', 'integer'],
            'statut' => ['required', 'in:actif,inactif'],
            'trial' => ['boolean'],
            'trial_duration' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string'],
        ]);

        $plan = Plan::create($validated);

        return response()->json(['message' => 'Plan créé', 'plan' => $plan], 201);
    }

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
            'position' => ['nullable', 'integer'],
            'statut' => ['sometimes', 'in:actif,inactif'],
            'trial' => ['boolean'],
            'trial_duration' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string'],
        ]);

        $plan->update($validated);

        return response()->json(['message' => 'Plan mis à jour', 'plan' => $plan]);
    }

    public function destroy(Plan $plan)
    {
        $plan->delete();

        return response()->json(['message' => 'Plan supprimé']);
    }
}