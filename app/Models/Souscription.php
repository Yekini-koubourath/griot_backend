<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Souscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'date_debut',
        'date_fin',
        'date_validation',
        'statut',
        'mode_paiement',
        'reference_paiement',
        'montant',
        'devise',
        'quantite',
        'details_paiement',
    ];

    protected $casts = [
        'date_debut' => 'datetime',
        'date_fin' => 'datetime',
        'date_validation' => 'datetime',
        'details_paiement' => 'array',
        'montant' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Vérifie si cette souscription donne actuellement accès.
     */
    public function estActive(): bool
    {
        if (!in_array($this->statut, ['actif', 'renouvelee'], true)) {
            return false;
        }

        $maintenant = now();

        if ($this->date_debut && $maintenant->lt($this->date_debut)) {
            return false;
        }

        if ($this->date_fin && $maintenant->gt($this->date_fin)) {
            return false;
        }

        return true;
    }
}