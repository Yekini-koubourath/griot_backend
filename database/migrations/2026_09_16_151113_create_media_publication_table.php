<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_publication', function (Blueprint $table) {
            $table->id();

            $table->foreignId('publication_id')
                ->constrained('publications')
                ->cascadeOnDelete();

            $table->foreignId('media_id')
                ->constrained('media')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique([
                'publication_id',
                'media_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_publication');
    }
};