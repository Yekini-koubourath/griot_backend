<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Souscription;
use Illuminate\Http\Request;

class AdminSouscriptionController extends Controller
{
    /**
     * Liste des souscriptions.
     */
    public function index(Request $request)
    {
        $query = Souscription::with([
            'user:id,name,email',
            'plan:id,nom',
        ]);

        if ($request->filled('statut')) {
            $query->where('statut', $request->query('statut'));
        }

        return response()->json([
            'souscriptions' => $query->latest()->paginate(20),
        ]);
    }

    /**
     * Valider une souscription.
     */
    public function valider(Souscription $souscription)
    {
        // Une souscription déjà active ne doit pas être validée une deuxième fois.
        if ($souscription->statut === 'actif') {
            return response()->json([
                'message' => 'Cette souscription est déjà active.',
            ], 422);
        }

        // Charger le plan associé.
        $souscription->load('plan');

        if (!$souscription->plan) {
            return response()->json([
                'message' => 'Le plan associé à cette souscription est introuvable.',
            ], 422);
        }

        $dateDebut = now();

        // Calcul de la date de fin selon la durée du plan.
        $dateFin = match ($souscription->plan->duree_unite) {
            'jour' => $dateDebut->copy()->addDays(
                $souscription->plan->duree
            ),

            'annee' => $dateDebut->copy()->addYears(
                $souscription->plan->duree
            ),

            default => $dateDebut->copy()->addMonths(
                $souscription->plan->duree
            ),
        };

        $souscription->update([
            'statut' => 'actif',
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
        ]);

        return response()->json([
            'message' => 'Souscription validée, accès activé.',
            'souscription' => $souscription->fresh()->load([
                'user:id,name,email',
                'plan:id,nom',
            ]),
        ]);
    }

    /**
     * Rejeter une souscription.
     */
    public function rejeter(
        Request $request,
        Souscription $souscription
    ) {
        $validated = $request->validate([
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        // Une souscription déjà active ne doit pas être rejetée.
        if ($souscription->statut === 'actif') {
            return response()->json([
                'message' => 'Une souscription active ne peut pas être rejetée.',
            ], 422);
        }

        $details = $souscription->details_paiement ?? [];

        $details['motif_rejet'] =
            $validated['motif'] ?? 'Paiement non confirmé';

        $souscription->update([
            'statut' => 'expiree',
            'details_paiement' => $details,
        ]);

        return response()->json([
            'message' => 'Souscription rejetée.',
            'souscription' => $souscription->fresh()->load([
                'user:id,name,email',
                'plan:id,nom',
            ]),
        ]);
    }
}