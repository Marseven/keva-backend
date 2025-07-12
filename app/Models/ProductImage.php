<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="ProductImage",
 *     type="object",
 *     title="Image du produit",
 *     @OA\Property(property="id", type="integer", example=301),
 *     @OA\Property(property="product_id", type="integer", example=1001),
 *     @OA\Property(property="image_path", type="string", example="products/tshirt1.jpg"),
 *     @OA\Property(property="alt_text", type="string", example="Vue de face du T-shirt KEVA Premium"),
 *     @OA\Property(property="sort_order", type="integer", example=1),
 *     @OA\Property(property="is_primary", type="boolean", example=true),
 *     @OA\Property(property="metadata", type="object", example={"source": "mobile_app"}),
 *     @OA\Property(property="image_url", type="string", readOnly=true, example="https://keva.test/storage/products/tshirt1.jpg"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-07-10T09:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-07-10T10:00:00Z")
 * )
 */
class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'image_path',
        'alt_text',
        'sort_order',
        'is_primary',
        'metadata',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'metadata' => 'array',
    ];

    // Relations
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Accessors
    public function getImageUrlAttribute(): string
    {
        return asset('storage/' . $this->image_path);
    }

    // Scopes
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
