<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use App\Models\Store;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $productNames = [
            'T-shirt Premium',
            'Pantalon Classique',
            'Robe Élégante',
            'Chaussures Confort',
            'Sac à Main',
            'Montre Moderne',
            'Parfum Essence',
            'Crème Visage',
            'Smartphone',
            'Écouteurs Bluetooth',
            'Ordinateur Portable',
            'Tablette',
            'Livre Guide',
            'Cahier Premium',
            'Stylo Luxe',
            'Café Arabica',
            'Thé Vert',
            'Chocolat Noir',
            'Huile Essentielle',
            'Savon Naturel',
            'Bijou Artisanal',
            'Décoration Maison',
            'Plante Verte',
            'Jouet Éducatif',
            'Équipement Sport',
        ];

        $adjectives = [
            'Premium', 'Classique', 'Moderne', 'Élégant', 'Confort', 
            'Luxe', 'Naturel', 'Artisanal', 'Traditionnel', 'Innovant',
            'Authentique', 'Qualité', 'Durable', 'Écologique', 'Unique'
        ];

        $productName = $this->faker->randomElement($productNames) . ' ' . $this->faker->randomElement($adjectives);
        $basePrice = $this->faker->numberBetween(5000, 500000); // Price in XAF
        $comparePrice = $this->faker->boolean(30) ? $basePrice + $this->faker->numberBetween(5000, 50000) : null;

        $stockQuantity = $this->faker->numberBetween(0, 100);
        $trackInventory = $this->faker->boolean(80); // 80% of products track inventory

        $conditions = ['new', 'used', 'refurbished'];
        $statuses = ['active', 'draft', 'inactive'];

        return [
            'user_id' => User::factory(),
            'category_id' => Category::inRandomOrder()->first()?->id ?? 1,
            'store_id' => Store::factory(),
            'name' => $productName,
            'slug' => Str::slug($productName),
            'description' => $this->faker->paragraph(3),
            'short_description' => $this->faker->sentence(),
            'sku' => strtoupper(Str::random(8)),
            'price' => $basePrice,
            'compare_price' => $comparePrice,
            'cost_price' => $basePrice * 0.6, // 60% of selling price
            'currency' => 'XAF',
            'track_inventory' => $trackInventory,
            'stock_quantity' => $trackInventory ? $stockQuantity : null,
            'min_stock_level' => $trackInventory ? $this->faker->numberBetween(1, 10) : null,
            'allow_backorder' => $this->faker->boolean(20), // 20% allow backorder
            'weight' => $this->faker->randomFloat(2, 0.1, 5), // Weight in kg
            'dimensions' => [
                'width' => $this->faker->numberBetween(5, 50),
                'height' => $this->faker->numberBetween(5, 50),
                'depth' => $this->faker->numberBetween(5, 50),
            ],
            'condition' => $this->faker->randomElement($conditions),
            'featured_image' => null, // Will be handled separately if needed
            'gallery_images' => [],
            'video_url' => $this->faker->boolean(10) ? 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' : null,
            'meta_title' => $productName . ' - KEVA',
            'meta_description' => $this->faker->sentence(),
            'tags' => $this->faker->words(3),
            'attributes' => $this->generateAttributes(),
            'variants' => $this->generateVariants(),
            'status' => $this->faker->randomElement($statuses),
            'is_featured' => $this->faker->boolean(15), // 15% are featured
            'is_digital' => $this->faker->boolean(5), // 5% are digital products
            'published_at' => $this->faker->boolean(80) ? $this->faker->dateTimeBetween('-1 year', 'now') : null,
            'views_count' => $this->faker->numberBetween(0, 1000),
            'sales_count' => $this->faker->numberBetween(0, 50),
            'average_rating' => $this->faker->randomFloat(1, 1, 5),
            'reviews_count' => $this->faker->numberBetween(0, 20),
        ];
    }

    /**
     * Generate random attributes for the product.
     */
    private function generateAttributes(): array
    {
        $attributes = [];
        
        // Common attributes
        $possibleAttributes = [
            'couleur' => ['Noir', 'Blanc', 'Rouge', 'Bleu', 'Vert', 'Jaune', 'Rose', 'Violet'],
            'taille' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
            'matière' => ['Coton', 'Polyester', 'Laine', 'Soie', 'Lin', 'Cuir'],
            'marque' => ['Premium', 'Classique', 'Moderne', 'Artisanal', 'Traditionnel'],
        ];

        // Randomly select 1-3 attributes
        $selectedAttributes = $this->faker->randomElements(array_keys($possibleAttributes), $this->faker->numberBetween(1, 3));
        
        foreach ($selectedAttributes as $attribute) {
            $attributes[$attribute] = $this->faker->randomElement($possibleAttributes[$attribute]);
        }

        return $attributes;
    }

    /**
     * Generate random variants for the product.
     */
    private function generateVariants(): array
    {
        if (!$this->faker->boolean(30)) { // 30% chance of having variants
            return [];
        }

        $variants = [];
        $sizes = ['S', 'M', 'L', 'XL'];
        $colors = ['Noir', 'Blanc', 'Rouge', 'Bleu'];

        foreach ($sizes as $size) {
            foreach ($colors as $color) {
                if ($this->faker->boolean(60)) { // 60% chance for each variant
                    $variants[] = [
                        'taille' => $size,
                        'couleur' => $color,
                        'stock' => $this->faker->numberBetween(0, 20),
                        'price_adjustment' => $this->faker->numberBetween(-5000, 5000),
                    ];
                }
            }
        }

        return $variants;
    }

    /**
     * Indicate that the product is active and published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'published_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    /**
     * Indicate that the product is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    /**
     * Indicate that the product is out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 0,
            'allow_backorder' => false,
        ]);
    }

    /**
     * Create a product for a specific store.
     */
    public function forStore(Store $store): static
    {
        return $this->state(fn (array $attributes) => [
            'store_id' => $store->id,
            'user_id' => $store->user_id,
        ]);
    }

    /**
     * Create a product for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}