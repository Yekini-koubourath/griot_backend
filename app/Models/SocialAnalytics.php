<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAnalytics extends Model
{
    use HasFactory;

    protected $table = 'social_analytics';

    protected $fillable = [
        'user_id',
        'publication_id',
        'project_id',
        'network',
        'date',
        'reach',
        'impressions',
        'likes',
        'comments',
        'shares',
        'clicks',
        'engagement_rate',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'reach' => 'integer',
        'impressions' => 'integer',
        'likes' => 'integer',
        'comments' => 'integer',
        'shares' => 'integer',
        'clicks' => 'integer',
        'engagement_rate' => 'decimal:2',
    ];

    /**
     * Utilisateur propriétaire des statistiques.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Publication concernée.
     */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    /**
     * Projet concerné.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}