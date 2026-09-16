<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaFolder extends Model
{
    protected $fillable = [
        'user_id',
        'name',
    ];

    /**
     * Le dossier appartient à un utilisateur.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Le dossier contient plusieurs médias.
     */
    public function medias(): HasMany
    {
        return $this->hasMany(Media::class, 'folder_id');
    }
}