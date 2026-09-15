<?php

namespace App\Console\Commands;

use App\Models\Souscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Expire les souscriptions terminées et active les renouvellements arrivés à échéance';

    public function handle(): int
    {
        $maintenant = now();

        /*
        |--------------------------------------------------------------------------
        | 1. Expirer les abonnements actifs dont la période est terminée
        |--------------------------------------------------------------------------
        */

        $expirees = Souscription::where('statut', 'actif')
            ->whereNotNull('date_fin')
            ->where('date_fin', '<', $maintenant)
            ->update([
                'statut' => 'expiree',
            ]);

        /*
        |--------------------------------------------------------------------------
        | 2. Activer les renouvellements dont la date de début est arrivée
        |--------------------------------------------------------------------------
        */

        $renouvellements = Souscription::where('statut', 'renouvelee')
            ->whereNotNull('date_debut')
            ->where('date_debut', '<=', $maintenant)
            ->get();

        $activees = 0;

        foreach ($renouvellements as $souscription) {
            /*
             * On s'assure qu'il n'existe pas une autre souscription
             * active qui chevauche cette période.
             */
            $autreActive = Souscription::where('user_id', $souscription->user_id)
                ->where('id', '!=', $souscription->id)
                ->where('statut', 'actif')
                ->whereNotNull('date_fin')
                ->where('date_fin', '>=', $souscription->date_debut)
                ->exists();

            if ($autreActive) {
                continue;
            }

            $souscription->update([
                'statut' => 'actif',
            ]);

            $activees++;
        }

        /*
        |--------------------------------------------------------------------------
        | Résultat
        |--------------------------------------------------------------------------
        */

        $this->info(
            $expirees . ' souscription(s) expirée(s).'
        );

        $this->info(
            $activees . ' renouvellement(s) activé(s).'
        );
        
        return self::SUCCESS;
    }
}