<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * @OA\Schema(
 *     schema="Plan",
 *     type="object",
 *     title="Plan d'abonnement",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Basic"),
 *     @OA\Property(property="slug", type="string", example="basic"),
 *     @OA\Property(property="description", type="string", example="Accès aux fonctionnalités de base"),
 *     @OA\Property(property="price", type="number", format="float", example=5000),
 *     @OA\Property(property="currency", type="string", example="XAF"),
 *     @OA\Property(property="features", type="array", @OA\Items(type="string"), example={"analytics", "support"}),
 *     @OA\Property(property="duration_days", type="integer", example=30),
 *     @OA\Property(property="max_products", type="integer", example=100),
 *     @OA\Property(property="max_orders", type="integer", example=1000),
 *     @OA\Property(property="max_storage_mb", type="integer", example=512),
 *     @OA\Property(property="has_analytics", type="boolean", example=true),
 *     @OA\Property(property="has_priority_support", type="boolean", example=false),
 *     @OA\Property(property="has_custom_domain", type="boolean", example=false),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="is_popular", type="boolean", example=false),
 *     @OA\Property(property="sort_order", type="integer", example=1),
 *     @OA\Property(property="discount_percentage", type="number", format="float", example=10),
 *     @OA\Property(property="discount_expires_at", type="string", format="date-time", example="2025-08-01T00:00:00Z"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T12:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-01T12:00:00Z")
 * )
 */
class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'duration_days',
        'currency',
        'features',
        'max_products',
        'max_orders',
        'max_storage_mb',
        'has_analytics',
        'has_priority_support',
        'has_custom_domain',
        'is_active',
        'is_popular',
        'sort_order',
        'discount_percentage',
        'discount_expires_at',
    ];

    protected $casts = [
        'features' => 'array',
        'has_analytics' => 'boolean',
        'has_priority_support' => 'boolean',
        'has_custom_domain' => 'boolean',
        'is_active' => 'boolean',
        'is_popular' => 'boolean',
        'discount_expires_at' => 'datetime',
    ];

    // Relations
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'selected_plan', 'slug');
    }

    // Scopes
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopePopular(Builder $query): void
    {
        $query->where('is_popular', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('price');
    }

    // Accessors
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 0, ',', ' ') . ' ' . $this->currency;
    }

    public function getDiscountedPriceAttribute(): ?float
    {
        if (!$this->discount_percentage || $this->discount_expires_at?->isPast()) {
            return null;
        }

        return $this->price * (1 - $this->discount_percentage / 100);
    }

    public function getFinalPriceAttribute(): float
    {
        return $this->discounted_price ?? $this->price;
    }

    public function getIsOnSaleAttribute(): bool
    {
        return $this->discount_percentage > 0 &&
            $this->discount_expires_at &&
            $this->discount_expires_at->isFuture();
    }

    // Méthodes utilitaires
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    public function isUnlimitedProducts(): bool
    {
        return $this->max_products === 0;
    }

    public function isUnlimitedOrders(): bool
    {
        return $this->max_orders === 0;
    }
}
