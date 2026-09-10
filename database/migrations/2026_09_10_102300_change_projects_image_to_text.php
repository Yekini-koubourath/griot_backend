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
        // Use raw statement only for MySQL / PostgreSQL. Skip for SQLite (test env).
        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'pgsql'])) {
            DB::statement('ALTER TABLE `projects` MODIFY `image` TEXT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'pgsql'])) {
            DB::statement('ALTER TABLE `projects` MODIFY `image` VARCHAR(255) NULL');
        }
    }
};
