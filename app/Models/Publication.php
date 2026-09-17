<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Publication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'title',
        'content',
        'network',
        'status',
        'date',
        'time',
        'image',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    /**
     * Utilisateur propriétaire.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Projet.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Médias utilisés dans la publication.
     */
    public function medias(): BelongsToMany
    {
        return $this->belongsToMany(
            Media::class,
            'media_publication'
        )->withTimestamps();
    }
}