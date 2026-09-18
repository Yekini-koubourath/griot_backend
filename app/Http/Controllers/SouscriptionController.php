<?php

namespace App\Http\Controllers;

use App\Models\Facture;
use App\Models\Plan;
use App\Models\Souscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SouscriptionController extends Controller
{
    /**
     * Récupérer l'abonnement actuel de l'utilisateur.
     */
    public function current(Request $request)
    {
        $souscription = $request->user()
            ->souscriptions()
            ->with('plan')
            ->latest('date_debut')
            ->first();

        return response()->json([
            'souscription' => $souscription,
        ]);
    }

    /**
     * Créer une nouvelle souscription.
     *
     * Règles :
     *
     * - Plan gratuit :
     *      abonnement activé immédiatement
     *      aucune facture
     *
     * - Plan payant :
     *      abonnement activé immédiatement
     *      facture créée automatiquement
     *
     * - Si l'utilisateur possède déjà une période active :
     *      la nouvelle période commence après la période actuelle
     *      et reçoit le statut "renouvelee".
     *
     * L'administrateur reste uniquement une solution
     * de secours pour les souscriptions qui seraient
     * restées en attente.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => [
                'required',
                'exists:plans,id',
            ],

            'duree_unite' => [
                'required',
                'in:jour,mois,annee',
            ],

            'quantite' => [
                'required',
                'integer',
                'min:1',
            ],

            'devise' => [
                'required',
                'in:EUR,USD,XOF',
            ],

            'mode_paiement' => [
                'nullable',
                'in:carte,mobile_money,virement',
            ],

            'reference_paiement' => [
                'nullable',
                'string',
                'max:255',
            ],

            'details_paiement' => [
                'nullable',
                'array',
            ],

            'details_paiement.*' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        $quantite = (int) $validated['quantite'];

        $dureeUnite = $validated['duree_unite'];

        /*
        |--------------------------------------------------------------------------
        | Vérifier si le plan est gratuit
        |--------------------------------------------------------------------------
        */

        $estGratuit = $plan->isGratuit();

        /*
        |--------------------------------------------------------------------------
        | Les plans payants doivent avoir un mode de paiement
        |--------------------------------------------------------------------------
        */

        if (!$estGratuit && empty($validated['mode_paiement'])) {
            return response()->json([
                'message' => 'Le mode de paiement est requis pour ce plan.',

                'errors' => [
                    'mode_paiement' => [
                        'Le mode de paiement est requis.',
                    ],
                ],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Calcul du montant
        |--------------------------------------------------------------------------
        */

        $rates = config('currencies.rates', [
            'EUR' => 1,
        ]);

        $rate = $rates[$validated['devise']] ?? 1;

        $prixUnitaire = $plan->prixPourUnite($dureeUnite);

        $montant = $estGratuit
            ? 0
            : round(
                $prixUnitaire * $rate * $quantite,
                2
            );

        /*
        |--------------------------------------------------------------------------
        | Calcul de la période
        |--------------------------------------------------------------------------
        */

        /*
        |--------------------------------------------------------------------------
        | Vérifier s'il existe déjà une période active
        |--------------------------------------------------------------------------
        */

        $derniereSouscription = $request->user()
            ->souscriptions()
            ->whereIn('statut', [
                'actif',
                'renouvelee',
            ])
            ->whereNotNull('date_fin')
            ->where('date_fin', '>=', now())
            ->orderByDesc('date_fin')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Début de la nouvelle période
        |--------------------------------------------------------------------------
        */

        if ($derniereSouscription) {
            /*
             * L'utilisateur possède déjà un abonnement.
             *
             * Le nouvel abonnement commencera
             * après la période actuelle.
             */

            $dateDebut = $derniereSouscription
                ->date_fin
                ->copy()
                ->addDay()
                ->startOfDay();

            $statut = 'renouvelee';
        } else {
            /*
             * Aucun abonnement actif.
             *
             * L'abonnement commence immédiatement.
             */

            $dateDebut = now();

            $statut = 'actif';
        }

        /*
        |--------------------------------------------------------------------------
        | Date de fin
        |--------------------------------------------------------------------------
        */

        $dateFin = match ($dureeUnite) {
            'jour' => $dateDebut
                ->copy()
                ->addDays($quantite)
                ->subSecond(),

            'annee' => $dateDebut
                ->copy()
                ->addYearsNoOverflow($quantite)
                ->subSecond(),

            default => $dateDebut
                ->copy()
                ->addMonthsNoOverflow($quantite)
                ->subSecond(),
        };

        /*
        |--------------------------------------------------------------------------
        | Création de la souscription + facture
        |--------------------------------------------------------------------------
        */

        $resultat = DB::transaction(function () use (
            $request,
            $plan,
            $estGratuit,
            $validated,
            $montant,
            $quantite,
            $dateDebut,
            $dateFin,
            $dureeUnite,
            $statut
        ) {
            /*
            |--------------------------------------------------------------------------
            | Création de la souscription
            |--------------------------------------------------------------------------
            */

            $souscription = $request->user()
                ->souscriptions()
                ->create([
                    'plan_id' => $plan->id,

                    'date_debut' => $dateDebut,

                    'date_fin' => $dateFin,

                    'date_validation' => now(),

                    'statut' => $statut,

                    'mode_paiement' =>
                        $validated['mode_paiement'] ?? null,

                    'reference_paiement' =>
                        $validated['reference_paiement'] ?? null,

                    'montant' => $montant,

                    'devise' => $validated['devise'],

                    'quantite' => $quantite,

                    'details_paiement' => array_merge(
                        $validated['details_paiement'] ?? [],
                        [
                            'plan_nom' => $plan->nom,
                            'duree_unite' => $dureeUnite,
                        ]
                    ),
                ]);

            /*
            |--------------------------------------------------------------------------
            | Création de la facture
            |--------------------------------------------------------------------------
            |
            | IMPORTANT :
            |
            | Plan gratuit :
            |      aucune facture.
            |
            | Plan payant :
            |      facture créée automatiquement.
            |
            */

            $facture = null;

            if (!$estGratuit && $montant > 0) {
                $facture = Facture::create([
                    'user_id' => $request->user()->id,

                    'souscription_id' => $souscription->id,

                    'numero' => 'FACT-SOUS-' .
                        str_pad(
                            (string) $souscription->id,
                            6,
                            '0',
                            STR_PAD_LEFT
                        ),

                    'reference_paiement' =>
                        $validated['reference_paiement'] ?? null,

                    'montant' => $montant,

                    'devise' => $validated['devise'],

                    'statut' => 'payee',

                    'date_facture' => now(),

                    'pdf_url' => null,
                ]);
            }

            return [
                'souscription' => $souscription,

                'facture' => $facture,
            ];
        });

        /*
        |--------------------------------------------------------------------------
        | Message de réponse
        |--------------------------------------------------------------------------
        */

        if ($estGratuit) {
            $message = 'Abonnement gratuit activé avec succès.';
        } elseif ($statut === 'renouvelee') {
            $message = 'Renouvellement enregistré avec succès. Il commencera à la fin de votre abonnement actuel.';
        } else {
            $message = 'Abonnement activé avec succès.';
        }

        /*
        |--------------------------------------------------------------------------
        | Réponse API
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'message' => $message,

            'souscription' => $resultat['souscription']
                ->load('plan'),

            'facture' => $resultat['facture'],
        ], 201);
    }
}