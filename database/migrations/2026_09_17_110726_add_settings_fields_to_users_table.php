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
        Schema::table('users', function (Blueprint $table) {
            // Informations du profil
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->after('email');
            $table->string('company')->nullable()->after('phone');
            $table->string('avatar')->nullable()->after('company');

            // Préférences
            $table->string('language')->default('Français')->after('avatar');
            $table->string('timezone')->default('GMT +1')->after('language');

            // Notifications
            $table->boolean('notification_publications')->default(true)->after('timezone');
            $table->boolean('notification_reminders')->default(true)->after('notification_publications');
            $table->boolean('notification_analytics')->default(false)->after('notification_reminders');
            $table->boolean('notification_marketing')->default(false)->after('notification_analytics');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'phone',
                'company',
                'avatar',
                'language',
                'timezone',
                'notification_publications',
                'notification_reminders',
                'notification_analytics',
                'notification_marketing',
            ]);
        });
    }
};