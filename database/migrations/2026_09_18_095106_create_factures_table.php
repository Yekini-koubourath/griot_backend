<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factures', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('souscription_id')
                ->constrained('souscriptions')
                ->cascadeOnDelete();

            $table->string('numero')->unique();

            $table->string('reference_paiement')->nullable();

            $table->decimal('montant', 10, 2)->default(0);

            $table->string('devise', 3)->default('EUR');

            $table->enum('statut', [
                'en_attente',
                'payee',
                'annulee',
            ])->default('en_attente');

            $table->timestamp('date_facture')->nullable();

            $table->string('pdf_url')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factures');
    }
};