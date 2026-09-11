<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function comptesSociaux(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(CompteSocial::class);
}
}