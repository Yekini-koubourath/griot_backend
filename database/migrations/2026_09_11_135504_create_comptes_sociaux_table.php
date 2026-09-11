<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comptes_sociaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('reseau', ['tiktok', 'facebook', 'instagram', 'linkedin', 'x']);
            $table->string('compte_id');          // ID du compte côté plateforme (open_id TikTok, etc.)
            $table->string('nom_affichage')->nullable();
            $table->string('nom_utilisateur')->nullable(); // @handle
            $table->string('avatar_url')->nullable();
            $table->text('access_token');          // chiffré via cast
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('meta')->nullable();       // scopes, données brutes utiles
            $table->enum('statut', ['actif', 'expire', 'revoque'])->default('actif');
            $table->timestamps();

            $table->unique(['project_id', 'reseau', 'compte_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comptes_sociaux');
    }
};