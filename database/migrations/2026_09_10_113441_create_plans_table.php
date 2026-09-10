<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('nom');                                   // Gratuit, Middle, Premium
            $table->enum('type', ['particulier', 'entreprise', 'agence'])->default('particulier');
            $table->text('description')->nullable();
            $table->decimal('prix', 10, 2)->default(0);               // prix de référence
            $table->string('devise', 3)->default('EUR');              // devise de référence du prix stocké
            $table->unsignedInteger('duree')->default(1);             // ex: 1
            $table->enum('duree_unite', ['jour', 'mois', 'annee'])->default('mois');
            $table->unsignedInteger('position')->default(0);          // ordre d'affichage
            $table->enum('statut', ['actif', 'inactif'])->default('actif');
            $table->boolean('trial')->default(false);
            $table->unsignedInteger('trial_duration')->nullable();    // jours d'essai
            $table->json('features')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};