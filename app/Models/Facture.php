<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Facture extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'souscription_id',
        'numero',
        'reference_paiement',
        'montant',
        'devise',
        'statut',
        'date_facture',
        'pdf_url',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_facture' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function souscription(): BelongsTo
    {
        return $this->belongsTo(Souscription::class);
    }
}