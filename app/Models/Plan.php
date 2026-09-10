<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom', 'type', 'description', 'prix', 'devise',
        'duree', 'duree_unite', 'position', 'statut',
        'trial', 'trial_duration', 'features',
    ];

    protected $casts = [
        'features' => 'array',
        'trial' => 'boolean',
        'prix' => 'decimal:2',
    ];

    public function souscriptions(): HasMany
    {
        return $this->hasMany(Souscription::class);
    }

    public function isGratuit(): bool
    {
        return (float) $this->prix === 0.0;
    }

    // À ajouter dans la classe Plan

public function prixMensuelBase(): float
{
    return match ($this->duree_unite) {
        'jour' => round($this->prix * 30 / max($this->duree, 1), 2),
        'annee' => round($this->prix / (12 * max($this->duree, 1)), 2),
        default => round($this->prix / max($this->duree, 1), 2),
    };
}

public function prixPourUnite(string $unite): float
{
    $mensuel = $this->prixMensuelBase();

    return match ($unite) {
        'jour' => round($mensuel / 30, 2),
        'annee' => round($mensuel * 10, 2), // 2 mois offerts sur l'annuel
        default => round($mensuel, 2),
    };
}
}