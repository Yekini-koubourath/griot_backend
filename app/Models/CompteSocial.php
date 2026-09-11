<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompteSocial extends Model
{
    use HasFactory;

    protected $table = 'comptes_sociaux';

    protected $fillable = [
        'project_id', 'user_id', 'reseau', 'compte_id',
        'nom_affichage', 'nom_utilisateur', 'avatar_url',
        'access_token', 'refresh_token', 'token_expires_at',
        'meta', 'statut',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'meta' => 'array',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function estExpire(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }
}