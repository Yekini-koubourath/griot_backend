<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FacturesController extends Controller
{
    /**
     * Liste les factures de l'utilisateur connecté.
     */
    public function index(Request $request)
    {
        $factures = $request->user()
            ->factures()
            ->with([
                'souscription.plan',
            ])
            ->latest('date_facture')
            ->get();

        return response()->json([
            'factures' => $factures->map(function ($facture) {
                return [
                    'id' => $facture->id,

                    // Numéro de facture
                    'numero' => $facture->numero,

                    // Référence du paiement
                    'reference' => $facture->reference_paiement,

                    // Dates
                    'date' => $facture->date_facture?->toIso8601String(),
                    'date_facture' => $facture->date_facture?->toIso8601String(),
                    'created_at' => $facture->created_at?->toIso8601String(),

                    // Montant
                    'montant' => $facture->montant,
                    'total' => $facture->montant,
                    'amount' => $facture->montant,

                    // Devise
                    'devise' => $facture->devise,
                    'currency' => $facture->devise,

                    // Statut
                    'statut' => $facture->statut,
                    'status' => $facture->statut,

                    // Plan associé
                    'plan' => $facture->souscription?->plan
                        ? [
                            'id' => $facture->souscription->plan->id,
                            'nom' => $facture->souscription->plan->nom,
                            'name' => $facture->souscription->plan->nom,
                        ]
                        : null,

                    // PDF de la facture
                    'pdf_url' => $facture->pdf_url,
                    'invoice_url' => $facture->pdf_url,
                    'url' => $facture->pdf_url,

                    // Informations de la souscription
                    'mode_paiement' => $facture->souscription?->mode_paiement,
                    'quantite' => $facture->souscription?->quantite,

                    'date_debut' => $facture->souscription?->date_debut
                        ?->toIso8601String(),

                    'date_fin' => $facture->souscription?->date_fin
                        ?->toIso8601String(),
                ];
            })->values(),
        ]);
    }
}