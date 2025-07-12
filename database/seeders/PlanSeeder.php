<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basique',
                'slug' => 'basic',
                'description' => 'Parfait pour commencer votre activité en ligne',
                'price' => 15000, // 15,000 XAF
                'duration_days' => 30,
                'features' => [
                    '50 produits maximum',
                    '100 commandes par mois',
                    '1 GB de stockage',
                    'Support par email',
                    'Thèmes de base',
                    'Intégration Mobile Money'
                ],
                'max_products' => 50,
                'max_orders' => 100,
                'max_storage_mb' => 1024,
                'has_analytics' => false,
                'has_priority_support' => false,
                'has_custom_domain' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Professionnel',
                'slug' => 'pro',
                'description' => 'Idéal pour les entreprises en croissance',
                'price' => 35000, // 35,000 XAF
                'duration_days' => 30,
                'features' => [
                    '500 produits maximum',
                    'Commandes illimitées',
                    '10 GB de stockage',
                    'Support prioritaire',
                    'Analytiques avancées',
                    'Thèmes premium',
                    'Intégration Visa/Mastercard',
                    'Rapports détaillés'
                ],
                'max_products' => 500,
                'max_orders' => 0, // Illimité
                'max_storage_mb' => 10240,
                'has_analytics' => true,
                'has_priority_support' => true,
                'has_custom_domain' => false,
                'is_active' => true,
                'is_popular' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Entreprise',
                'slug' => 'enterprise',
                'description' => 'Solution complète pour les grandes entreprises',
                'price' => 75000, // 75,000 XAF
                'duration_days' => 30,
                'features' => [
                    'Produits illimités',
                    'Commandes illimitées',
                    '100 GB de stockage',
                    'Support téléphonique prioritaire',
                    'Analytiques complètes',
                    'Thèmes personnalisés',
                    'Toutes les intégrations',
                    'API avancée',
                    'Domaine personnalisé',
                    'Formation dédiée'
                ],
                'max_products' => 0, // Illimité
                'max_orders' => 0, // Illimité
                'max_storage_mb' => 102400,
                'has_analytics' => true,
                'has_priority_support' => true,
                'has_custom_domain' => true,
                'is_active' => true,
                'sort_order' => 3,
            ]
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }
    }
}
