<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="Cart",
 *     type="object",
 *     title="Panier",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=10),
 *     @OA\Property(property="session_id", type="string", example="abcd1234"),
 *     @OA\Property(property="product_id", type="integer", example=42),
 *     @OA\Property(property="quantity", type="integer", example=2),
 *     @OA\Property(property="unit_price", type="number", format="float", example=2500),
 *     @OA\Property(property="product_options", type="array", @OA\Items(type="string"), example={"taille:M", "couleur:bleu"}),
 *     @OA\Property(property="total_price", type="number", format="float", readOnly=true, example=5000),
 *     @OA\Property(property="formatted_total_price", type="string", readOnly=true, example="5 000 XAF"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T12:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-01T12:00:00Z")
 * )
 * 
 * @OA\Schema(
 *     schema="CartItem",
 *     type="object",
 *     title="Article du panier",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="product_id", type="integer", example=42),
 *     @OA\Property(property="quantity", type="integer", example=2),
 *     @OA\Property(property="unit_price", type="number", format="float", example=2500),
 *     @OA\Property(property="total_price", type="number", format="float", example=5000),
 *     @OA\Property(property="formatted_total_price", type="string", example="5 000 XAF"),
 *     @OA\Property(property="product_options", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="product", ref="#/components/schemas/Product")
 * )
 * 
 * @OA\Schema(
 *     schema="CartTotals",
 *     type="object",
 *     title="Totaux du panier",
 *     @OA\Property(property="subtotal", type="number", format="float", example=15000),
 *     @OA\Property(property="tax_amount", type="number", format="float", example=1500),
 *     @OA\Property(property="shipping_amount", type="number", format="float", example=2000),
 *     @OA\Property(property="total_amount", type="number", format="float", example=18500),
 *     @OA\Property(property="formatted_subtotal", type="string", example="15 000 XAF"),
 *     @OA\Property(property="formatted_tax", type="string", example="1 500 XAF"),
 *     @OA\Property(property="formatted_shipping", type="string", example="2 000 XAF"),
 *     @OA\Property(property="formatted_total", type="string", example="18 500 XAF"),
 *     @OA\Property(property="items_count", type="integer", example=3)
 * )
 */
class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'product_id',
        'quantity',
        'unit_price',
        'product_options',
    ];

    protected $casts = [
        'product_options' => 'array',
    ];

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Accessors
    public function getTotalPriceAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }

    public function getFormattedTotalPriceAttribute(): string
    {
        return number_format($this->total_price, 0, ',', ' ') . ' XAF';
    }

    // Méthodes utilitaires
    public function updateQuantity(int $quantity): void
    {
        $this->update(['quantity' => $quantity]);
    }

    public function incrementQuantity(int $quantity = 1): void
    {
        $this->increment('quantity', $quantity);
    }

    public function decrementQuantity(int $quantity = 1): void
    {
        $newQuantity = $this->quantity - $quantity;

        if ($newQuantity <= 0) {
            $this->delete();
        } else {
            $this->update(['quantity' => $newQuantity]);
        }
    }
}
