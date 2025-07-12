<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

/**
 * @OA\Schema(
 *     schema="Order",
 *     type="object",
 *     title="Commande",
 *     @OA\Property(property="id", type="integer", example=101),
 *     @OA\Property(property="order_number", type="string", example="KEV-202507-0001"),
 *     @OA\Property(property="user_id", type="integer", example=12),
 *     @OA\Property(property="subtotal", type="number", format="float", example=50000),
 *     @OA\Property(property="tax_amount", type="number", format="float", example=4500),
 *     @OA\Property(property="shipping_amount", type="number", format="float", example=3000),
 *     @OA\Property(property="discount_amount", type="number", format="float", example=1000),
 *     @OA\Property(property="total_amount", type="number", format="float", example=56500),
 *     @OA\Property(property="currency", type="string", example="XAF"),
 *     @OA\Property(property="status", type="string", enum={"pending", "confirmed", "processing", "shipped", "delivered", "cancelled", "refunded"}, example="confirmed"),
 *     @OA\Property(property="payment_status", type="string", enum={"pending", "paid", "failed", "refunded", "partial"}, example="paid"),
 *     @OA\Property(
 *         property="shipping_address",
 *         type="object",
 *         example={
 *             "name": "Jean Mabiala",
 *             "address": "123 Rue de la Paix",
 *             "city": "Libreville",
 *             "phone": "+241123456789"
 *         }
 *     ),
 *     @OA\Property(
 *         property="billing_address",
 *         type="object",
 *         example={
 *             "name": "Jean Mabiala",
 *             "address": "123 Rue de la Paix",
 *             "city": "Libreville",
 *             "phone": "+241123456789"
 *         }
 *     ),
 *     @OA\Property(property="shipping_method", type="string", example="Express"),
 *     @OA\Property(property="shipped_at", type="string", format="date-time", nullable=true, example="2025-07-15T12:00:00Z"),
 *     @OA\Property(property="delivered_at", type="string", format="date-time", nullable=true, example="2025-07-16T15:00:00Z"),
 *     @OA\Property(property="tracking_number", type="string", nullable=true, example="TRACK123456789"),
 *     @OA\Property(property="customer_email", type="string", example="jean@example.com"),
 *     @OA\Property(property="customer_phone", type="string", example="+241123456789"),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Laisser au gardien si absent."),
 *     @OA\Property(property="admin_notes", type="string", nullable=true, example="Commande prioritaire."),
 *     @OA\Property(property="metadata", type="object", example={"source": "mobile_app"}),
 *     @OA\Property(property="items_count", type="integer", readOnly=true, example=3),
 *     @OA\Property(property="can_be_cancelled", type="boolean", readOnly=true, example=true),
 *     @OA\Property(property="can_be_shipped", type="boolean", readOnly=true, example=false),
 *     @OA\Property(property="formatted_total", type="string", readOnly=true, example="56 500 XAF"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-07-10T08:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-07-10T10:00:00Z")
 * )
 */
class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'subtotal',
        'tax_amount',
        'shipping_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'status',
        'payment_status',
        'shipping_address',
        'billing_address',
        'shipping_method',
        'shipped_at',
        'delivered_at',
        'tracking_number',
        'customer_email',
        'customer_phone',
        'notes',
        'admin_notes',
        'metadata',
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'metadata' => 'array',
    ];

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    // Scopes
    public function scopeByStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopeByPaymentStatus(Builder $query, string $paymentStatus): void
    {
        $query->where('payment_status', $paymentStatus);
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    public function scopePaid(Builder $query): void
    {
        $query->where('payment_status', 'paid');
    }

    public function scopeRecent(Builder $query): void
    {
        $query->orderBy('created_at', 'desc');
    }

    // Accessors
    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->total_amount, 0, ',', ' ') . ' ' . $this->currency;
    }

    public function getStatusBadgeAttribute(): array
    {
        $badges = [
            'pending' => ['color' => 'yellow', 'text' => 'En attente'],
            'confirmed' => ['color' => 'blue', 'text' => 'Confirmée'],
            'processing' => ['color' => 'purple', 'text' => 'En traitement'],
            'shipped' => ['color' => 'indigo', 'text' => 'Expédiée'],
            'delivered' => ['color' => 'green', 'text' => 'Livrée'],
            'cancelled' => ['color' => 'red', 'text' => 'Annulée'],
            'refunded' => ['color' => 'gray', 'text' => 'Remboursée'],
        ];

        return $badges[$this->status] ?? ['color' => 'gray', 'text' => 'Inconnu'];
    }

    public function getPaymentStatusBadgeAttribute(): array
    {
        $badges = [
            'pending' => ['color' => 'yellow', 'text' => 'En attente'],
            'paid' => ['color' => 'green', 'text' => 'Payée'],
            'failed' => ['color' => 'red', 'text' => 'Échec'],
            'refunded' => ['color' => 'gray', 'text' => 'Remboursée'],
            'partial' => ['color' => 'orange', 'text' => 'Partiel'],
        ];

        return $badges[$this->payment_status] ?? ['color' => 'gray', 'text' => 'Inconnu'];
    }

    public function getItemsCountAttribute(): int
    {
        return $this->items()->sum('quantity');
    }

    public function getCanBeCancelledAttribute(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    public function getCanBeShippedAttribute(): bool
    {
        return $this->status === 'processing' && $this->payment_status === 'paid';
    }

    // Méthodes utilitaires
    public function generateOrderNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $lastOrder = static::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastOrder ?
            ((int) substr($lastOrder->order_number, -4)) + 1 : 1;

        return "KEV-{$year}{$month}-" . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function calculateTotals(): void
    {
        $this->subtotal = $this->items()->sum('total_price');
        $this->total_amount = $this->subtotal + $this->tax_amount + $this->shipping_amount - $this->discount_amount;
        $this->save();
    }

    public function confirm(): void
    {
        $this->update(['status' => 'confirmed']);
    }

    public function startProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function ship(string $trackingNumber = null): void
    {
        $this->update([
            'status' => 'shipped',
            'shipped_at' => now(),
            'tracking_number' => $trackingNumber,
        ]);
    }

    public function deliver(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        if (!$this->can_be_cancelled) {
            throw new \Exception('Cette commande ne peut pas être annulée');
        }

        $this->update(['status' => 'cancelled']);

        // Remettre en stock les produits
        foreach ($this->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->incrementStock($item->quantity);
            }
        }
    }

    public function markAsPaid(): void
    {
        $this->update(['payment_status' => 'paid']);

        if ($this->status === 'pending') {
            $this->confirm();
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = $order->generateOrderNumber();
            }
        });
    }
}
