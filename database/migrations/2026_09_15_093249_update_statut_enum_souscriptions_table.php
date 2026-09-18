<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL : on utilise un type VARCHAR avec une contrainte CHECK.
            DB::statement("
                ALTER TABLE souscriptions
                DROP CONSTRAINT IF EXISTS souscriptions_statut_check
            ");

            DB::statement("
                ALTER TABLE souscriptions
                ALTER COLUMN statut TYPE VARCHAR(50)
                USING statut::VARCHAR
            ");

            DB::statement("
                ALTER TABLE souscriptions
                ALTER COLUMN statut SET DEFAULT 'en_attente'
            ");

            DB::statement("
                ALTER TABLE souscriptions
                ALTER COLUMN statut SET NOT NULL
            ");

            DB::statement("
                ALTER TABLE souscriptions
                ADD CONSTRAINT souscriptions_statut_check
                CHECK (
                    statut IN (
                        'en_attente',
                        'actif',
                        'renouvelee',
                        'expiree'
                    )
                )
            ");
        } elseif ($driver === 'mysql') {
            // MySQL : conserver la syntaxe ENUM existante.
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
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("
                ALTER TABLE souscriptions
                DROP CONSTRAINT IF EXISTS souscriptions_statut_check
            ");

            DB::statement("
                ALTER TABLE souscriptions
                ALTER COLUMN statut TYPE VARCHAR(50)
                USING statut::VARCHAR
            ");

            DB::statement("
                ALTER TABLE souscriptions
                ALTER COLUMN statut SET DEFAULT 'en_attente'
            ");

            DB::statement("
                ALTER TABLE souscriptions
                ALTER COLUMN statut SET NOT NULL
            ");

            DB::statement("
                ALTER TABLE souscriptions
                ADD CONSTRAINT souscriptions_statut_check
                CHECK (
                    statut IN (
                        'en_attente',
                        'actif',
                        'expiree'
                    )
                )
            ");
        } elseif ($driver === 'mysql') {
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
    }
};