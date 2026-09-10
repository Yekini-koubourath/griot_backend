<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('souscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->timestamp('date_debut')->nullable();
            $table->timestamp('date_fin')->nullable();
            $table->enum('statut', ['actif', 'en_attente', 'expiree'])->default('en_attente');
            $table->enum('mode_paiement', ['carte', 'mobile_money', 'virement'])->nullable();
            $table->string('reference_paiement')->nullable();
            $table->decimal('montant', 10, 2)->default(0);
            $table->string('devise', 3)->default('EUR');
            $table->unsignedInteger('quantite')->default(1);          // nb de périodes achetées
            $table->json('details_paiement')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('souscriptions');
    }
};