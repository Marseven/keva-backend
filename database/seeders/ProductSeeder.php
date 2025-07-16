<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\Category;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // This seeder can be run independently or as part of StoreSeeder
        // It will only create products if stores exist
        
        $stores = Store::all();
        
        if ($stores->isEmpty()) {
            $this->command->warn('No stores found. Please run StoreSeeder first.');
            return;
        }

        $this->command->info('Creating additional products for existing stores...');

        // Create some featured products for each store
        foreach ($stores as $store) {
            // Create 2-3 featured products per store
            $featuredCount = rand(2, 3);
            
            Product::factory()
                ->count($featuredCount)
                ->forStore($store)
                ->featured()
                ->published()
                ->create();
        }

        // Create some discounted products (products with compare_price)
        $discountedProducts = Product::factory()
            ->count(50)
            ->create()
            ->each(function ($product) {
                // Set store and user based on random existing store
                $store = Store::inRandomOrder()->first();
                $product->update([
                    'store_id' => $store->id,
                    'user_id' => $store->user_id,
                    'compare_price' => $product->price + rand(5000, 50000),
                    'status' => 'active',
                    'published_at' => now()->subDays(rand(1, 30)),
                ]);
            });

        // Create some out-of-stock products
        $outOfStockProducts = Product::factory()
            ->count(30)
            ->outOfStock()
            ->create()
            ->each(function ($product) {
                // Set store and user based on random existing store
                $store = Store::inRandomOrder()->first();
                $product->update([
                    'store_id' => $store->id,
                    'user_id' => $store->user_id,
                    'status' => 'active',
                    'published_at' => now()->subDays(rand(1, 60)),
                ]);
            });

        // Create products with specific categories
        $categories = Category::all();
        if ($categories->isNotEmpty()) {
            foreach ($categories->take(5) as $category) {
                Product::factory()
                    ->count(rand(8, 15))
                    ->published()
                    ->create()
                    ->each(function ($product) use ($category) {
                        // Set store and user based on random existing store
                        $store = Store::inRandomOrder()->first();
                        $product->update([
                            'store_id' => $store->id,
                            'user_id' => $store->user_id,
                            'category_id' => $category->id,
                        ]);
                    });
            }
        }

        $totalProducts = Product::count();
        $this->command->info("Total products created: {$totalProducts}");
        
        // Display some statistics
        $activeProducts = Product::where('status', 'active')->count();
        $featuredProducts = Product::where('is_featured', true)->count();
        $outOfStockProducts = Product::where('stock_quantity', 0)->where('allow_backorder', false)->count();
        
        $this->command->info("Active products: {$activeProducts}");
        $this->command->info("Featured products: {$featuredProducts}");
        $this->command->info("Out of stock products: {$outOfStockProducts}");
    }
}