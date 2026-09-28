<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comptes_sociaux', function (Blueprint $table) {
            $table->text('avatar_url')->nullable()->change();
            $table->text('access_token')->nullable()->change();
$table->text('refresh_token')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('comptes_sociaux', function (Blueprint $table) {
            $table->string('avatar_url')->nullable()->change();
            $table->text('access_token')->nullable()->change();
$table->text('refresh_token')->nullable()->change();
        });
    }
};