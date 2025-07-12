<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *     schema="Product",
 *     type="object",
 *     title="Produit",
 *     @OA\Property(property="id", type="integer", example=1001),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="category_id", type="integer", example=3),
 *     @OA\Property(property="name", type="string", example="T-shirt KEVA Premium"),
 *     @OA\Property(property="slug", type="string", example="tshirt-keva-premium"),
 *     @OA\Property(property="description", type="string", example="Un t-shirt confortable en coton bio."),
 *     @OA\Property(property="short_description", type="string", example="100% coton bio."),
 *     @OA\Property(property="sku", type="string", example="KEVA-TSHIRT-001"),
 *     @OA\Property(property="price", type="number", format="float", example=10000),
 *     @OA\Property(property="compare_price", type="number", format="float", nullable=true, example=12000),
 *     @OA\Property(property="cost_price", type="number", format="float", nullable=true, example=6000),
 *     @OA\Property(property="currency", type="string", example="XAF"),
 *     @OA\Property(property="track_inventory", type="boolean", example=true),
 *     @OA\Property(property="stock_quantity", type="integer", example=50),
 *     @OA\Property(property="min_stock_level", type="integer", example=5),
 *     @OA\Property(property="allow_backorder", type="boolean", example=false),
 *     @OA\Property(property="weight", type="number", format="float", nullable=true, example=0.2),
 *     @OA\Property(
 *         property="dimensions",
 *         type="object",
 *         example={"width": 30, "height": 2, "depth": 40}
 *     ),
 *     @OA\Property(property="condition", type="string", example="new"),
 *     @OA\Property(property="featured_image", type="string", example="products/tshirt.jpg"),
 *     @OA\Property(property="gallery_images", type="array", @OA\Items(type="string"), example={"products/tshirt1.jpg", "products/tshirt2.jpg"}),
 *     @OA\Property(property="video_url", type="string", nullable=true, example="https://www.youtube.com/watch?v=abc123"),
 *     @OA\Property(property="meta_title", type="string", example="T-shirt KEVA - Boutique"),
 *     @OA\Property(property="meta_description", type="string", example="Achetez ce t-shirt confortable sur KEVA."),
 *     @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"tshirt", "mode", "keva"}),
 *     @OA\Property(property="attributes", type="object", example={"taille": "M", "couleur": "Bleu"}),
 *     @OA\Property(property="variants", type="array", @OA\Items(type="object"), example={{"taille": "M", "stock": 10}, {"taille": "L", "stock": 5}}),
 *     @OA\Property(property="status", type="string", example="active"),
 *     @OA\Property(property="is_featured", type="boolean", example=true),
 *     @OA\Property(property="is_digital", type="boolean", example=false),
 *     @OA\Property(property="published_at", type="string", format="date-time", nullable=true, example="2025-07-10T08:00:00Z"),
 *     @OA\Property(property="views_count", type="integer", example=100),
 *     @OA\Property(property="sales_count", type="integer", example=25),
 *     @OA\Property(property="average_rating", type="number", format="float", example=4.6),
 *     @OA\Property(property="reviews_count", type="integer", example=8),
 *     @OA\Property(property="formatted_price", type="string", readOnly=true, example="10 000 XAF"),
 *     @OA\Property(property="formatted_compare_price", type="string", nullable=true, readOnly=true, example="12 000 XAF"),
 *     @OA\Property(property="discount_percentage", type="integer", nullable=true, readOnly=true, example=17),
 *     @OA\Property(property="is_on_sale", type="boolean", readOnly=true, example=true),
 *     @OA\Property(property="is_in_stock", type="boolean", readOnly=true, example=true),
 *     @OA\Property(property="is_low_stock", type="boolean", readOnly=true, example=false),
 *     @OA\Property(property="stock_status", type="string", readOnly=true, example="in_stock"),
 *     @OA\Property(property="featured_image_url", type="string", readOnly=true, example="https://keva.test/storage/products/tshirt.jpg"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-07-09T09:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-07-10T12:00:00Z")
 * )
 */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'slug',
        'description',
        'short_description',
        'sku',
        'price',
        'compare_price',
        'cost_price',
        'currency',
        'track_inventory',
        'stock_quantity',
        'min_stock_level',
        'allow_backorder',
        'weight',
        'dimensions',
        'condition',
        'featured_image',
        'gallery_images',
        'video_url',
        'meta_title',
        'meta_description',
        'tags',
        'attributes',
        'variants',
        'status',
        'is_featured',
        'is_digital',
        'published_at',
        'views_count',
        'sales_count',
        'average_rating',
        'reviews_count',
    ];

    protected $casts = [
        'dimensions' => 'array',
        'gallery_images' => 'array',
        'tags' => 'array',
        'attributes' => 'array',
        'variants' => 'array',
        'track_inventory' => 'boolean',
        'allow_backorder' => 'boolean',
        'is_featured' => 'boolean',
        'is_digital' => 'boolean',
        'published_at' => 'datetime',
    ];

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    // Scopes
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeFeatured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'active')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeInStock(Builder $query): void
    {
        $query->where(function ($q) {
            $q->where('track_inventory', false)
                ->orWhere('stock_quantity', '>', 0)
                ->orWhere('allow_backorder', true);
        });
    }

    public function scopeByCategory(Builder $query, $categoryId): void
    {
        $query->where('category_id', $categoryId);
    }

    public function scopeByUser(Builder $query, $userId): void
    {
        $query->where('user_id', $userId);
    }

    public function scopeSearch(Builder $query, string $search): void
    {
        $query->whereFullText(['name', 'description', 'short_description'], $search)
            ->orWhere('name', 'like', "%{$search}%")
            ->orWhere('sku', 'like', "%{$search}%");
    }

    // Accessors
    public function getFeaturedImageUrlAttribute(): ?string
    {
        return $this->featured_image ? asset('storage/' . $this->featured_image) : null;
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 0, ',', ' ') . ' ' . $this->currency;
    }

    public function getFormattedComparePriceAttribute(): ?string
    {
        return $this->compare_price
            ? number_format($this->compare_price, 0, ',', ' ') . ' ' . $this->currency
            : null;
    }

    public function getDiscountPercentageAttribute(): ?int
    {
        if (!$this->compare_price || $this->compare_price <= $this->price) {
            return null;
        }

        return round((($this->compare_price - $this->price) / $this->compare_price) * 100);
    }

    public function getIsOnSaleAttribute(): bool
    {
        return $this->compare_price && $this->compare_price > $this->price;
    }

    public function getIsInStockAttribute(): bool
    {
        if (!$this->track_inventory) return true;

        return $this->stock_quantity > 0 || $this->allow_backorder;
    }

    public function getIsLowStockAttribute(): bool
    {
        if (!$this->track_inventory) return false;

        return $this->stock_quantity <= $this->min_stock_level && $this->stock_quantity > 0;
    }

    public function getStockStatusAttribute(): string
    {
        if (!$this->track_inventory) return 'unlimited';

        if ($this->stock_quantity <= 0) {
            return $this->allow_backorder ? 'backorder' : 'out_of_stock';
        }

        if ($this->stock_quantity <= $this->min_stock_level) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    // Méthodes utilitaires
    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    public function incrementSales(int $quantity = 1): void
    {
        $this->increment('sales_count', $quantity);
    }

    public function decrementStock(int $quantity): bool
    {
        if (!$this->track_inventory) return true;

        if ($this->stock_quantity < $quantity && !$this->allow_backorder) {
            return false;
        }

        $this->decrement('stock_quantity', $quantity);
        return true;
    }

    public function incrementStock(int $quantity): void
    {
        if ($this->track_inventory) {
            $this->increment('stock_quantity', $quantity);
        }
    }

    public function generateSku(): string
    {
        return strtoupper(Str::random(8));
    }

    public function publish(): void
    {
        $this->update([
            'status' => 'active',
            'published_at' => now(),
        ]);
    }

    public function unpublish(): void
    {
        $this->update([
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }

            if (empty($product->sku)) {
                $product->sku = $product->generateSku();
            }
        });
    }
}
