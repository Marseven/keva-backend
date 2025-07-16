<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create some store owners first
        $storeOwners = [
            [
                'first_name' => 'Aminata',
                'last_name' => 'NTOUTOUME',
                'email' => 'aminata.ntoutoume@example.com',
                'phone' => '+24101234567',
                'whatsapp_number' => '+24101234567',
                'business_name' => 'Boutique Aminata Mode',
                'business_type' => 'Vêtements et mode',
                'city' => 'Libreville',
                'address' => 'Quartier Glass, Avenue des Martyrs',
                'selected_plan' => 'pro',
                'password' => Hash::make('password123'),
            ],
            [
                'first_name' => 'Bernard',
                'last_name' => 'MBOUMBA',
                'email' => 'bernard.mboumba@example.com',
                'phone' => '+24102345678',
                'whatsapp_number' => '+24102345678',
                'business_name' => 'Électronique Bernard',
                'business_type' => 'Technologie et électronique',
                'city' => 'Port-Gentil',
                'address' => 'Zone industrielle, Rue des Techniciens',
                'selected_plan' => 'enterprise',
                'password' => Hash::make('password123'),
            ],
            [
                'first_name' => 'Clarisse',
                'last_name' => 'OYANE',
                'email' => 'clarisse.oyane@example.com',
                'phone' => '+24103456789',
                'whatsapp_number' => '+24103456789',
                'business_name' => 'Beauté Clarisse',
                'business_type' => 'Beauté et cosmétiques',
                'city' => 'Franceville',
                'address' => 'Centre-ville, Boulevard de la Paix',
                'selected_plan' => 'basic',
                'password' => Hash::make('password123'),
            ],
            [
                'first_name' => 'David',
                'last_name' => 'NGUEMA',
                'email' => 'david.nguema@example.com',
                'phone' => '+24104567890',
                'whatsapp_number' => '+24104567890',
                'business_name' => 'Alimentation David',
                'business_type' => 'Alimentation et boissons',
                'city' => 'Oyem',
                'address' => 'Marché central, Allée des Commerçants',
                'selected_plan' => 'pro',
                'password' => Hash::make('password123'),
            ],
            [
                'first_name' => 'Esther',
                'last_name' => 'MICKALA',
                'email' => 'esther.mickala@example.com',
                'phone' => '+24105678901',
                'whatsapp_number' => '+24105678901',
                'business_name' => 'Artisanat Esther',
                'business_type' => 'Artisanat local',
                'city' => 'Lambaréné',
                'address' => 'Quartier Artisanal, Route de la Tradition',
                'selected_plan' => 'basic',
                'password' => Hash::make('password123'),
            ],
        ];

        $createdUsers = [];
        foreach ($storeOwners as $ownerData) {
            $ownerData['agree_to_terms'] = true;
            $ownerData['is_admin'] = false;
            $ownerData['is_active'] = true;
            $ownerData['email_verified_at'] = now();

            $user = User::updateOrCreate(
                ['email' => $ownerData['email']],
                $ownerData
            );
            $createdUsers[] = $user;
        }

        // Create stores for each owner
        $storeData = [
            [
                'name' => 'Boutique Aminata Mode',
                'whatsapp_number' => '+24101234567',
                'description' => 'Boutique spécialisée dans les vêtements traditionnels et modernes pour femmes. Qualité et élégance garanties.',
            ],
            [
                'name' => 'Électronique Bernard',
                'whatsapp_number' => '+24102345678',
                'description' => 'Votre spécialiste en appareils électroniques, smartphones, ordinateurs et accessoires high-tech.',
            ],
            [
                'name' => 'Beauté Clarisse',
                'whatsapp_number' => '+24103456789',
                'description' => 'Produits de beauté et cosmétiques naturels. Prenez soin de votre peau avec nos produits authentiques.',
            ],
            [
                'name' => 'Alimentation David',
                'whatsapp_number' => '+24104567890',
                'description' => 'Produits alimentaires frais et de qualité. Épicerie fine et spécialités locales.',
            ],
            [
                'name' => 'Artisanat Esther',
                'whatsapp_number' => '+24105678901',
                'description' => 'Objets d\'art et artisanat traditionnel gabonais. Pièces uniques faites à la main.',
            ],
        ];

        $createdStores = [];
        foreach ($storeData as $index => $data) {
            $store = Store::create([
                'name' => $data['name'],
                'slug' => \Illuminate\Support\Str::slug($data['name']),
                'whatsapp_number' => $data['whatsapp_number'],
                'description' => $data['description'],
                'user_id' => $createdUsers[$index]->id,
                'is_active' => true,
            ]);

            // Add the owner to the store with 'owner' role
            $store->addUser($createdUsers[$index], 'owner');
            $createdStores[] = $store;
        }

        // Create additional stores using factory
        $additionalStores = Store::factory()->count(10)->create();
        foreach ($additionalStores as $store) {
            // Add the store creator as owner
            $store->addUser($store->user, 'owner');
        }

        $allStores = collect($createdStores)->concat($additionalStores);

        // Create products for each store
        foreach ($allStores as $store) {
            $productCount = rand(5, 20); // Each store gets 5-20 products
            
            Product::factory()
                ->count($productCount)
                ->forStore($store)
                ->create()
                ->each(function ($product) use ($store) {
                    // 80% chance the product is published
                    if (rand(1, 100) <= 80) {
                        $product->update([
                            'status' => 'active',
                            'published_at' => now()->subDays(rand(1, 365)),
                        ]);
                    }
                });
        }

        // Create some staff members for larger stores
        $largerStores = $allStores->take(8); // First 8 stores get staff
        foreach ($largerStores as $store) {
            // Create 1-3 staff members per store
            $staffCount = rand(1, 3);
            
            for ($i = 0; $i < $staffCount; $i++) {
                $staffMember = User::factory()->create([
                    'selected_plan' => 'basic',
                    'is_admin' => false,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);

                $roles = ['admin', 'manager', 'staff'];
                $role = $roles[array_rand($roles)];
                
                $permissions = $this->getPermissionsForRole($role);
                
                $store->addUser($staffMember, $role, $permissions);
            }
        }

        $this->command->info('Created ' . $allStores->count() . ' stores with products and staff members.');
    }

    /**
     * Get permissions array for a given role.
     */
    private function getPermissionsForRole(string $role): array
    {
        return match ($role) {
            'admin' => [
                'manage_products',
                'manage_orders',
                'view_analytics',
                'manage_settings',
                'manage_staff',
            ],
            'manager' => [
                'manage_products',
                'manage_orders',
                'view_analytics',
            ],
            'staff' => [
                'view_products',
                'view_orders',
                'update_order_status',
            ],
            default => [],
        };
    }
}