<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Créer un utilisateur admin
        User::updateOrCreate(
            ['email' => 'admin@keva.ga'],
            [
                'first_name' => 'Admin',
                'last_name' => 'KEVA',
                'email' => 'admin@keva.ga',
                'phone' => '+24177000000',
                'whatsapp_number' => '+24177000000',
                'business_name' => 'KEVA Administration',
                'business_type' => 'Services professionnels',
                'city' => 'Libreville',
                'address' => 'Immeuble KEVA, Boulevard Triomphal',
                'selected_plan' => 'enterprise',
                'agree_to_terms' => true,
                'is_admin' => true,
                'is_active' => true,
                'email_verified_at' => now(),
                'password' => Hash::make('admin123'),
            ]
        );

        // Créer un utilisateur de test
        User::updateOrCreate(
            ['email' => 'test@keva.ga'],
            [
                'first_name' => 'John',
                'last_name' => 'DOE',
                'email' => 'test@keva.ga',
                'phone' => '+24177123456',
                'whatsapp_number' => '+24177123456',
                'business_name' => 'Boutique Test',
                'business_type' => 'Vêtements et mode',
                'city' => 'Libreville',
                'address' => '123 Avenue de la Liberté',
                'selected_plan' => 'pro',
                'agree_to_terms' => true,
                'is_admin' => false,
                'is_active' => true,
                'email_verified_at' => now(),
                'password' => Hash::make('test123'),
            ]
        );

        // Créer quelques utilisateurs supplémentaires
        $users = [
            [
                'first_name' => 'Marie',
                'last_name' => 'MBOUROU',
                'email' => 'marie.mbourou@example.com',
                'phone' => '+24106789012',
                'business_name' => 'Chez Marie Cosmétiques',
                'business_type' => 'Beauté et cosmétiques',
                'city' => 'Port-Gentil',
                'selected_plan' => 'basic',
            ],
            [
                'first_name' => 'Pierre',
                'last_name' => 'NDONG',
                'email' => 'pierre.ndong@example.com',
                'phone' => '+24107345678',
                'business_name' => 'Tech Pierre',
                'business_type' => 'Technologie et électronique',
                'city' => 'Franceville',
                'selected_plan' => 'pro',
            ],
            [
                'first_name' => 'Sandrine',
                'last_name' => 'OBAME',
                'email' => 'sandrine.obame@example.com',
                'phone' => '+24106234567',
                'business_name' => 'Alimentation Sandrine',
                'business_type' => 'Alimentation et boissons',
                'city' => 'Oyem',
                'selected_plan' => 'basic',
            ]
        ];

        foreach ($users as $userData) {
            $userData['whatsapp_number'] = $userData['phone'];
            $userData['address'] = 'Adresse fictive pour ' . $userData['business_name'];
            $userData['agree_to_terms'] = true;
            $userData['is_admin'] = false;
            $userData['is_active'] = true;
            $userData['email_verified_at'] = now();
            $userData['password'] = Hash::make('password123');

            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
