<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE projects ALTER COLUMN image TYPE TEXT'
            );

            DB::statement(
                'ALTER TABLE projects ALTER COLUMN image DROP NOT NULL'
            );
        } elseif ($driver === 'mysql') {
            DB::statement(
                'ALTER TABLE `projects` MODIFY `image` TEXT NULL'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE projects ALTER COLUMN image TYPE VARCHAR(255)'
            );

            DB::statement(
                'ALTER TABLE projects ALTER COLUMN image DROP NOT NULL'
            );
        } elseif ($driver === 'mysql') {
            DB::statement(
                'ALTER TABLE `projects` MODIFY `image` VARCHAR(255) NULL'
            );
        }
    }
};