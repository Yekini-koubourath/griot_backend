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
            'plan:id,nom,duree,duree_unite',
        ]);

        if ($request->filled('statut')) {
            $query->where('statut', $request->query('statut'));
        }

        return response()->json([
            'souscriptions' => $query
                ->latest()
                ->paginate(20),
        ]);
    }

    /**
     * Valider une souscription en attente.
     *
     * Règle importante :
     *
     * Si l'utilisateur n'a aucune période active/future :
     *     nouvelle période = maintenant → durée du plan
     *
     * Si l'utilisateur possède déjà une période active/future :
     *     nouvelle période = lendemain de la dernière date de fin
     *
     * Exemple :
     *
     * abonnement actuel :
     * 01/09 → 30/09
     *
     * renouvellement payé le :
     * 20/09
     *
     * nouveau renouvellement :
     * 01/10 → 30/10
     */
    public function valider(Souscription $souscription)
    {
        if ($souscription->statut !== 'en_attente') {
            return response()->json([
                'message' => 'Seules les souscriptions en attente peuvent être validées.',
            ], 422);
        }

        $souscription->load('plan');

        if (!$souscription->plan) {
            return response()->json([
                'message' => 'Le plan associé à cette souscription est introuvable.',
            ], 422);
        }

        $plan = $souscription->plan;

        /*
        |--------------------------------------------------------------------------
        | Recherche de la dernière période active ou déjà programmée
        |--------------------------------------------------------------------------
        |
        | On regarde les souscriptions :
        |
        | - actif
        | - renouvelee
        |
        | dont la date de fin n'est pas encore dépassée.
        |
        | On prend celle qui finit le plus tard.
        |
        | Cela permet également d'empiler plusieurs renouvellements.
        |
        */

        $derniereSouscription = Souscription::where('user_id', $souscription->user_id)
            ->whereIn('statut', ['actif', 'renouvelee'])
            ->whereNotNull('date_fin')
            ->where('date_fin', '>=', now())
            ->orderByDesc('date_fin')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Détermination de la date de début
        |--------------------------------------------------------------------------
        */

        if ($derniereSouscription) {
            /*
             * L'utilisateur possède déjà une période.
             *
             * Le nouveau renouvellement commence
             * le lendemain de la fin de cette période.
             */
            $dateDebut = $derniereSouscription->date_fin
                ->copy()
                ->addDay()
                ->startOfDay();

            $statut = 'renouvelee';
        } else {
            /*
             * Aucun abonnement actif ou futur.
             *
             * La nouvelle souscription commence aujourd'hui.
             */
            $dateDebut = now()->startOfDay();

            $statut = 'actif';
        }

        /*
        |--------------------------------------------------------------------------
        | Calcul de la date de fin
        |--------------------------------------------------------------------------
        |
        | Les périodes sont inclusives.
        |
        | Exemple :
        |
        | 01 septembre → 30 septembre
        |
        | et non :
        |
        | 01 septembre → 01 octobre
        |
        */

        $dateFin = match ($plan->duree_unite) {
            'jour' => $dateDebut
                ->copy()
                ->addDays(max((int) $plan->duree, 1) - 1)
                ->endOfDay(),

            'annee' => $dateDebut
                ->copy()
                ->addYearsNoOverflow(max((int) $plan->duree, 1))
                ->subDay()
                ->endOfDay(),

            default => $dateDebut
                ->copy()
                ->addMonthsNoOverflow(max((int) $plan->duree, 1))
                ->subDay()
                ->endOfDay(),
        };

        /*
        |--------------------------------------------------------------------------
        | Validation de la souscription
        |--------------------------------------------------------------------------
        */

        $souscription->update([
            'statut' => $statut,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'date_validation' => now(),
        ]);

        return response()->json([
            'message' => $statut === 'renouvelee'
                ? 'Renouvellement validé. La nouvelle période commencera après la période actuelle.'
                : 'Souscription validée, accès activé.',

            'souscription' => $souscription
                ->fresh()
                ->load([
                    'user:id,name,email',
                    'plan:id,nom,duree,duree_unite',
                ]),
        ]);
    }

    /**
     * Rejeter une souscription en attente.
     */
    public function rejeter(Request $request, Souscription $souscription)
    {
        $validated = $request->validate([
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        if ($souscription->statut !== 'en_attente') {
            return response()->json([
                'message' => 'Seules les souscriptions en attente peuvent être rejetées.',
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

            'souscription' => $souscription
                ->fresh()
                ->load([
                    'user:id,name,email',
                    'plan:id,nom,duree,duree_unite',
                ]),
        ]);
    }
}