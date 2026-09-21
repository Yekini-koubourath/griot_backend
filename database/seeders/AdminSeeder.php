<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@griot.ai'],
            [
                'name' => 'admin',
                'password' => Hash::make('Admin123'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // Si l'admin existe déjà, on s'assure simplement
        // qu'il possède bien les droits admin et que son email est vérifié.
        $admin->forceFill([
            'role' => 'admin',
            'email_verified_at' => $admin->email_verified_at ?? now(),
        ])->save();
    }
}