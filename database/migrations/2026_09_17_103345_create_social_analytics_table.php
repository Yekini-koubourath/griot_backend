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
        Schema::create('social_analytics', function (Blueprint $table) {
            $table->id();

            // Utilisateur propriétaire des statistiques
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Publication concernée
            $table->foreignId('publication_id')
                ->nullable()
                ->constrained('publications')
                ->nullOnDelete();

            // Projet concerné
            $table->foreignId('project_id')
                ->nullable()
                ->constrained('projects')
                ->nullOnDelete();

            // Réseau social
            $table->string('network');

            // Date des statistiques
            $table->date('date');

            // Statistiques principales
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('likes')->default(0);
            $table->unsignedBigInteger('comments')->default(0);
            $table->unsignedBigInteger('shares')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);

            // Taux d'engagement en pourcentage
            $table->decimal('engagement_rate', 5, 2)->default(0);

            $table->timestamps();

            // Évite les doublons pour une même publication,
            // un même réseau et une même date.
            $table->unique(
                ['publication_id', 'network', 'date'],
                'social_analytics_publication_network_date_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_analytics');
    }
};