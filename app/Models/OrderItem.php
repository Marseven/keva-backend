<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="OrderItem",
 *     type="object",
 *     title="Article de commande",
 *     @OA\Property(property="id", type="integer", example=301),
 *     @OA\Property(property="order_id", type="integer", example=101),
 *     @OA\Property(property="product_id", type="integer", example=42),
 *     @OA\Property(property="product_name", type="string", example="T-shirt KEVA Premium"),
 *     @OA\Property(property="product_sku", type="string", example="TSHIRT-KEVA-001"),
 *     @OA\Property(property="quantity", type="integer", example=2),
 *     @OA\Property(property="unit_price", type="number", format="float", example=10000),
 *     @OA\Property(property="total_price", type="number", format="float", example=20000),
 *     @OA\Property(property="product_options", type="array", @OA\Items(type="string"), example={"Taille:M", "Couleur:Bleu"}),
 *     @OA\Property(
 *         property="product_snapshot",
 *         type="object",
 *         example={
 *             "name": "T-shirt KEVA Premium",
 *             "description": "100% coton bio",
 *             "image": "products/tshirt.jpg",
 *             "price": 10000,
 *             "sku": "TSHIRT-KEVA-001"
 *         }
 *     ),
 *     @OA\Property(property="formatted_unit_price", type="string", readOnly=true, example="10 000 XAF"),
 *     @OA\Property(property="formatted_total_price", type="string", readOnly=true, example="20 000 XAF"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-07-10T10:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-07-10T10:05:00Z")
 * )
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_sku',
        'quantity',
        'unit_price',
        'total_price',
        'product_options',
        'product_snapshot',
    ];

    protected $casts = [
        'product_options' => 'array',
        'product_snapshot' => 'array',
    ];

    // Relations
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Accessors
    public function getFormattedUnitPriceAttribute(): string
    {
        return number_format($this->unit_price, 0, ',', ' ') . ' XAF';
    }

    public function getFormattedTotalPriceAttribute(): string
    {
        return number_format($this->total_price, 0, ',', ' ') . ' XAF';
    }

    // Méthodes utilitaires
    public function calculateTotal(): void
    {
        $this->total_price = $this->quantity * $this->unit_price;
        $this->save();
    }

    public function createFromCartItem(Cart $cartItem): array
    {
        $product = $cartItem->product;

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => $cartItem->quantity,
            'unit_price' => $cartItem->unit_price,
            'total_price' => $cartItem->total_price,
            'product_options' => $cartItem->product_options,
            'product_snapshot' => [
                'name' => $product->name,
                'description' => $product->short_description,
                'image' => $product->featured_image,
                'price' => $product->price,
                'sku' => $product->sku,
            ],
        ];
    }
}
