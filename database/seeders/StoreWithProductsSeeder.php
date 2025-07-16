<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StoreWithProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * This seeder creates a complete store ecosystem with proper relationships
     */
    public function run(): void
    {
        $this->command->info('🏪 Creating comprehensive store ecosystem...');

        // Create specific store scenarios
        $this->createFashionStore();
        $this->createElectronicsStore();
        $this->createBeautyStore();
        $this->createFoodStore();
        $this->createHandicraftsStore();

        // Create additional random stores
        $this->createRandomStores(10);

        $this->displayStatistics();
    }

    /**
     * Create a fashion store with clothing products
     */
    private function createFashionStore(): void
    {
        $owner = User::create([
            'first_name' => 'Fatoumata',
            'last_name' => 'DIALLO',
            'email' => 'fatoumata.diallo@fashion.ga',
            'phone' => '+24101111111',
            'whatsapp_number' => '+24101111111',
            'business_name' => 'Fashion Fatoumata',
            'business_type' => 'Vêtements et mode',
            'city' => 'Libreville',
            'address' => 'Quartier Batterie IV, Rue de la Mode',
            'selected_plan' => 'pro',
            'agree_to_terms' => true,
            'is_admin' => false,
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
        ]);

        $store = Store::create([
            'name' => 'Fashion Fatoumata',
            'slug' => 'fashion-fatoumata',
            'whatsapp_number' => '+24101111111',
            'description' => 'Boutique de mode féminine et masculine. Vêtements traditionnels et modernes, accessoires de mode.',
            'user_id' => $owner->id,
            'is_active' => true,
        ]);

        $store->addUser($owner, 'owner');

        // Create fashion products
        $fashionProducts = [
            ['name' => 'Robe Traditionnelle Gabonaise', 'price' => 75000, 'category' => 'Vêtements'],
            ['name' => 'Costume Homme Élégant', 'price' => 125000, 'category' => 'Vêtements'],
            ['name' => 'Sac à Main Cuir', 'price' => 35000, 'category' => 'Accessoires'],
            ['name' => 'Chaussures Femme Talon', 'price' => 45000, 'category' => 'Chaussures'],
            ['name' => 'Bijoux Traditionnels', 'price' => 25000, 'category' => 'Bijoux'],
            ['name' => 'Chemise Homme Coton', 'price' => 18000, 'category' => 'Vêtements'],
            ['name' => 'Pantalon Femme Slim', 'price' => 28000, 'category' => 'Vêtements'],
            ['name' => 'Écharpe Soie Colorée', 'price' => 15000, 'category' => 'Accessoires'],
        ];

        foreach ($fashionProducts as $productData) {
            Product::factory()->forStore($store)->create([
                'name' => $productData['name'],
                'price' => $productData['price'],
                'status' => 'active',
                'published_at' => now()->subDays(rand(1, 30)),
                'is_featured' => rand(1, 100) <= 30,
            ]);
        }

        // Add staff
        $manager = User::factory()->create(['business_name' => 'Fashion Assistant']);
        $store->addUser($manager, 'manager', ['manage_products', 'view_orders']);
    }

    /**
     * Create an electronics store
     */
    private function createElectronicsStore(): void
    {
        $owner = User::create([
            'first_name' => 'Ibrahim',
            'last_name' => 'HASSAN',
            'email' => 'ibrahim.hassan@electronics.ga',
            'phone' => '+24102222222',
            'whatsapp_number' => '+24102222222',
            'business_name' => 'Electronics Ibrahim',
            'business_type' => 'Technologie et électronique',
            'city' => 'Port-Gentil',
            'address' => 'Zone commerciale, Avenue de la Technologie',
            'selected_plan' => 'enterprise',
            'agree_to_terms' => true,
            'is_admin' => false,
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
        ]);

        $store = Store::create([
            'name' => 'Electronics Ibrahim',
            'slug' => 'electronics-ibrahim',
            'whatsapp_number' => '+24102222222',
            'description' => 'Spécialiste en appareils électroniques, smartphones, ordinateurs, TV et accessoires high-tech.',
            'user_id' => $owner->id,
            'is_active' => true,
        ]);

        $store->addUser($owner, 'owner');

        // Create electronics products
        $electronicsProducts = [
            ['name' => 'Smartphone Samsung Galaxy', 'price' => 450000, 'category' => 'Électronique'],
            ['name' => 'Ordinateur Portable Dell', 'price' => 750000, 'category' => 'Électronique'],
            ['name' => 'TV LED 55 pouces', 'price' => 850000, 'category' => 'Électronique'],
            ['name' => 'Écouteurs Bluetooth', 'price' => 25000, 'category' => 'Électronique'],
            ['name' => 'Chargeur Portable', 'price' => 15000, 'category' => 'Électronique'],
            ['name' => 'Tablette iPad', 'price' => 350000, 'category' => 'Électronique'],
            ['name' => 'Enceinte Bluetooth', 'price' => 45000, 'category' => 'Électronique'],
            ['name' => 'Clavier Gaming', 'price' => 35000, 'category' => 'Électronique'],
        ];

        foreach ($electronicsProducts as $productData) {
            Product::factory()->forStore($store)->create([
                'name' => $productData['name'],
                'price' => $productData['price'],
                'status' => 'active',
                'published_at' => now()->subDays(rand(1, 30)),
                'is_featured' => rand(1, 100) <= 40,
                'track_inventory' => true,
                'stock_quantity' => rand(5, 50),
            ]);
        }

        // Add staff
        $technician = User::factory()->create(['business_name' => 'Tech Support']);
        $store->addUser($technician, 'staff', ['view_products', 'update_order_status']);
    }

    /**
     * Create a beauty store
     */
    private function createBeautyStore(): void
    {
        $owner = User::create([
            'first_name' => 'Grace',
            'last_name' => 'MOUSSAVOU',
            'email' => 'grace.moussavou@beauty.ga',
            'phone' => '+24103333333',
            'whatsapp_number' => '+24103333333',
            'business_name' => 'Beauty Grace',
            'business_type' => 'Beauté et cosmétiques',
            'city' => 'Franceville',
            'address' => 'Centre-ville, Boulevard de la Beauté',
            'selected_plan' => 'basic',
            'agree_to_terms' => true,
            'is_admin' => false,
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
        ]);

        $store = Store::create([
            'name' => 'Beauty Grace',
            'slug' => 'beauty-grace',
            'whatsapp_number' => '+24103333333',
            'description' => 'Produits de beauté naturels et cosmétiques. Soins du visage, maquillage, parfums et accessoires beauté.',
            'user_id' => $owner->id,
            'is_active' => true,
        ]);

        $store->addUser($owner, 'owner');

        // Create beauty products
        $beautyProducts = [
            ['name' => 'Crème Hydratante Visage', 'price' => 18000, 'category' => 'Beauté'],
            ['name' => 'Parfum Essence Florale', 'price' => 35000, 'category' => 'Beauté'],
            ['name' => 'Rouge à Lèvres Mat', 'price' => 8000, 'category' => 'Beauté'],
            ['name' => 'Mascara Waterproof', 'price' => 12000, 'category' => 'Beauté'],
            ['name' => 'Fond de Teint Liquide', 'price' => 15000, 'category' => 'Beauté'],
            ['name' => 'Savon Naturel Karité', 'price' => 5000, 'category' => 'Beauté'],
            ['name' => 'Huile Essentielle Lavande', 'price' => 12000, 'category' => 'Beauté'],
            ['name' => 'Lotion Corporelle', 'price' => 10000, 'category' => 'Beauté'],
        ];

        foreach ($beautyProducts as $productData) {
            Product::factory()->forStore($store)->create([
                'name' => $productData['name'],
                'price' => $productData['price'],
                'status' => 'active',
                'published_at' => now()->subDays(rand(1, 30)),
                'is_featured' => rand(1, 100) <= 25,
            ]);
        }
    }

    /**
     * Create a food store
     */
    private function createFoodStore(): void
    {
        $owner = User::create([
            'first_name' => 'Marcel',
            'last_name' => 'BOUNGUENDZA',
            'email' => 'marcel.bounguendza@food.ga',
            'phone' => '+24104444444',
            'whatsapp_number' => '+24104444444',
            'business_name' => 'Alimentation Marcel',
            'business_type' => 'Alimentation et boissons',
            'city' => 'Oyem',
            'address' => 'Marché central, Pavillon A',
            'selected_plan' => 'pro',
            'agree_to_terms' => true,
            'is_admin' => false,
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
        ]);

        $store = Store::create([
            'name' => 'Alimentation Marcel',
            'slug' => 'alimentation-marcel',
            'whatsapp_number' => '+24104444444',
            'description' => 'Produits alimentaires frais et de qualité. Épicerie fine, produits locaux et spécialités gabonaises.',
            'user_id' => $owner->id,
            'is_active' => true,
        ]);

        $store->addUser($owner, 'owner');

        // Create food products
        $foodProducts = [
            ['name' => 'Café Arabica Bio', 'price' => 8000, 'category' => 'Alimentation'],
            ['name' => 'Miel Naturel Local', 'price' => 12000, 'category' => 'Alimentation'],
            ['name' => 'Chocolat Noir 70%', 'price' => 6000, 'category' => 'Alimentation'],
            ['name' => 'Thé Vert Premium', 'price' => 7000, 'category' => 'Alimentation'],
            ['name' => 'Épices Traditionnelles', 'price' => 5000, 'category' => 'Alimentation'],
            ['name' => 'Huile de Palme Pure', 'price' => 10000, 'category' => 'Alimentation'],
            ['name' => 'Farine de Manioc', 'price' => 4000, 'category' => 'Alimentation'],
            ['name' => 'Sauce Piment Maison', 'price' => 3000, 'category' => 'Alimentation'],
        ];

        foreach ($foodProducts as $productData) {
            Product::factory()->forStore($store)->create([
                'name' => $productData['name'],
                'price' => $productData['price'],
                'status' => 'active',
                'published_at' => now()->subDays(rand(1, 30)),
                'track_inventory' => true,
                'stock_quantity' => rand(20, 100),
                'min_stock_level' => 5,
            ]);
        }
    }

    /**
     * Create a handicrafts store
     */
    private function createHandicraftsStore(): void
    {
        $owner = User::create([
            'first_name' => 'Paulette',
            'last_name' => 'MBOULA',
            'email' => 'paulette.mboula@handicrafts.ga',
            'phone' => '+24105555555',
            'whatsapp_number' => '+24105555555',
            'business_name' => 'Artisanat Paulette',
            'business_type' => 'Artisanat local',
            'city' => 'Lambaréné',
            'address' => 'Village Artisanal, Route de la Tradition',
            'selected_plan' => 'basic',
            'agree_to_terms' => true,
            'is_admin' => false,
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
        ]);

        $store = Store::create([
            'name' => 'Artisanat Paulette',
            'slug' => 'artisanat-paulette',
            'whatsapp_number' => '+24105555555',
            'description' => 'Objets d\'art et artisanat traditionnel gabonais. Sculptures, masques, bijoux et décoration authentique.',
            'user_id' => $owner->id,
            'is_active' => true,
        ]);

        $store->addUser($owner, 'owner');

        // Create handicraft products
        $handicraftProducts = [
            ['name' => 'Masque Traditionnel Fang', 'price' => 85000, 'category' => 'Artisanat'],
            ['name' => 'Sculpture Bois Ébène', 'price' => 120000, 'category' => 'Artisanat'],
            ['name' => 'Bracelet Perles Traditionnelles', 'price' => 15000, 'category' => 'Artisanat'],
            ['name' => 'Tambour Artisanal', 'price' => 65000, 'category' => 'Artisanat'],
            ['name' => 'Panier Tressé Main', 'price' => 25000, 'category' => 'Artisanat'],
            ['name' => 'Collier Cauris', 'price' => 18000, 'category' => 'Artisanat'],
            ['name' => 'Statuette Ancêtre', 'price' => 95000, 'category' => 'Artisanat'],
            ['name' => 'Tapis Raphia', 'price' => 45000, 'category' => 'Artisanat'],
        ];

        foreach ($handicraftProducts as $productData) {
            Product::factory()->forStore($store)->create([
                'name' => $productData['name'],
                'price' => $productData['price'],
                'status' => 'active',
                'published_at' => now()->subDays(rand(1, 30)),
                'is_featured' => rand(1, 100) <= 50,
                'track_inventory' => true,
                'stock_quantity' => rand(1, 10), // Artisanal products are limited
                'min_stock_level' => 1,
            ]);
        }
    }

    /**
     * Create random stores using factories
     */
    private function createRandomStores(int $count): void
    {
        $stores = Store::factory()->count($count)->create();
        
        foreach ($stores as $store) {
            // Add owner
            $store->addUser($store->user, 'owner');
            
            // Create products for each store
            $productCount = rand(5, 25);
            Product::factory()
                ->count($productCount)
                ->forStore($store)
                ->create()
                ->each(function ($product) {
                    // 75% chance of being published
                    if (rand(1, 100) <= 75) {
                        $product->update([
                            'status' => 'active',
                            'published_at' => now()->subDays(rand(1, 180)),
                        ]);
                    }
                });
        }
    }

    /**
     * Display statistics about created data
     */
    private function displayStatistics(): void
    {
        $storeCount = Store::count();
        $userCount = User::count();
        $productCount = Product::count();
        $activeProducts = Product::where('status', 'active')->count();
        $featuredProducts = Product::where('is_featured', true)->count();
        
        $this->command->info("📊 Seeding completed successfully!");
        $this->command->line("🏪 Stores created: {$storeCount}");
        $this->command->line("👥 Users created: {$userCount}");
        $this->command->line("📦 Products created: {$productCount}");
        $this->command->line("✅ Active products: {$activeProducts}");
        $this->command->line("⭐ Featured products: {$featuredProducts}");
        
        $this->command->info("🎉 You can now test the store management system!");
    }
}