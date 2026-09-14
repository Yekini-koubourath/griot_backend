<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * Utilisateur propriétaire de la publication.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Projet auquel appartient la publication.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}