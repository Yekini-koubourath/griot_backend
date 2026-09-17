<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'status',
        'members',
        'image',
    ];

    /**
     * Le projet appartient à un utilisateur.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Comptes sociaux du projet.
     */
    public function comptesSociaux(): HasMany
    {
        return $this->hasMany(CompteSocial::class);
    }

    /**
     * Publications du projet.
     */
    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function campaigns()
{
    return $this->hasMany(Campaign::class);
}
}