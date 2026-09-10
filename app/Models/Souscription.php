<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Souscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'plan_id', 'date_debut', 'date_fin', 'statut',
        'mode_paiement', 'reference_paiement', 'montant', 'devise',
        'quantite', 'details_paiement',
    ];

    protected $casts = [
        'date_debut' => 'datetime',
        'date_fin' => 'datetime',
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

    public function estActive(): bool
    {
        return $this->statut === 'actif'
            && (is_null($this->date_fin) || $this->date_fin->isFuture());
    }
}