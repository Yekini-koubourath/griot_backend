<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function souscriptions(): HasMany
    {
        return $this->hasMany(Souscription::class);
    }

    public function souscriptionActive()
    {
        return $this->souscriptions()
            ->where('statut', 'actif')
            ->where(function ($q) {
                $q->whereNull('date_fin')
                    ->orWhere('date_fin', '>', now());
            })
            ->latest()
            ->first();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

  public function publications(): HasMany
{
    return $this->hasMany(Publication::class);
}

public function medias(): HasMany
{
    return $this->hasMany(Media::class);
}

public function mediaFolders(): HasMany
{
    return $this->hasMany(MediaFolder::class);
}

public function campaigns()
{
    return $this->hasMany(Campaign::class);
}
}
