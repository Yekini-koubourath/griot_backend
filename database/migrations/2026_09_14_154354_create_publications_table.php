<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('publications', function (Blueprint $table) {
            $table->id();

            // Utilisateur propriétaire de la publication
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Projet auquel appartient la publication
            $table->foreignId('project_id')
                ->nullable()
                ->constrained('projects')
                ->nullOnDelete();

            $table->string('title');

            $table->longText('content');

            // Facebook, Instagram, LinkedIn, TikTok, Google Business, X
            $table->string('network');

            // Publiée, Programmée, Brouillon, Échec
            $table->string('status')->default('Brouillon');

            $table->date('date')->nullable();

            $table->time('time')->nullable();

            // Pour le moment on garde le chemin / l'image sous forme de texte.
            $table->longText('image')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publications');
    }
};