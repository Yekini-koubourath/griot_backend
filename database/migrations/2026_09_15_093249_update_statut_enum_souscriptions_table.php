<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE souscriptions
            MODIFY statut ENUM(
                'en_attente',
                'actif',
                'renouvelee',
                'expiree'
            )
            NOT NULL
            DEFAULT 'en_attente'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE souscriptions
            MODIFY statut ENUM(
                'en_attente',
                'actif',
                'expiree'
            )
            NOT NULL
            DEFAULT 'en_attente'
        ");
    }
};