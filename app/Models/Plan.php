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
}