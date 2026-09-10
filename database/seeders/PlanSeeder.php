<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::query()->delete();

        Plan::create([
            'nom' => 'Gratuit',
            'type' => 'particulier',
            'description' => 'Idéal pour découvrir Griot AI.',
            'prix' => 0,
            'devise' => 'EUR',
            'duree' => 1,
            'duree_unite' => 'mois',
            'position' => 1,
            'statut' => 'actif',
            'trial' => false,
            'trial_duration' => null,
            'features' => [
                '1 espace de travail',
                'Jusqu\'à 2 comptes sociaux',
                '5 générations IA / mois',
                'Publication manuelle',
            ],
        ]);

        Plan::create([
            'nom' => 'Middle',
            'type' => 'particulier',
            'description' => 'Le choix incontournable pour les créateurs actifs.',
            'prix' => 29,
            'devise' => 'EUR',
            'duree' => 1,
            'duree_unite' => 'mois',
            'position' => 2,
            'statut' => 'actif',
            'trial' => true,
            'trial_duration' => 7,
            'features' => [
                '1 espace de travail dédié',
                'Jusqu\'à 5 comptes sociaux',
                'Briefing texte & image par IA',
                'Sélection multi-comptes',
                'Publication automatique',
            ],
        ]);

        Plan::create([
            'nom' => 'Premium',
            'type' => 'agence',
            'description' => 'Conçu pour les équipes et agences marketing.',
            'prix' => 59,
            'devise' => 'EUR',
            'duree' => 1,
            'duree_unite' => 'mois',
            'position' => 3,
            'statut' => 'actif',
            'trial' => true,
            'trial_duration' => 7,
            'features' => [
                'Espaces de travail illimités',
                'Comptes sociaux illimités',
                'IA prioritaire pour vos scripts',
                'Module d\'analytics avancé',
                'Support prioritaire 7j/7',
            ],
        ]);
    }
}