<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    protected $fillable = [
        'user_id',
        'folder_id',
        'name',
        'original_name',
        'type',
        'mime_type',
        'size',
        'path',
    ];

    /**
     * Le média appartient à un utilisateur.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Le média peut appartenir à un dossier.
     */

    public function folder(): BelongsTo
{
    return $this->belongsTo(MediaFolder::class, 'folder_id');
}
}