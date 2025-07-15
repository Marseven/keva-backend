<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *     schema="Store",
 *     type="object",
 *     title="Store",
 *     description="Store model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="name", type="string", example="Ma Boutique"),
 *     @OA\Property(property="slug", type="string", example="ma-boutique"),
 *     @OA\Property(property="whatsapp_number", type="string", example="+24177123456"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Description de ma boutique"),
 *     @OA\Property(property="user_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-15T10:30:00Z"),
 *     @OA\Property(property="user", ref="#/components/schemas/User"),
 *     @OA\Property(property="products", type="array", @OA\Items(ref="#/components/schemas/Product")),
 *     @OA\Property(property="products_count", type="integer", example=25)
 * )
 */
class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'whatsapp_number',
        'description',
        'user_id',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'products_count'
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug when creating a store
        static::creating(function ($store) {
            if (empty($store->slug)) {
                $store->slug = Str::slug($store->name);
                
                // Ensure slug is unique
                $originalSlug = $store->slug;
                $counter = 1;
                while (static::where('slug', $store->slug)->exists()) {
                    $store->slug = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }
        });

        // Update slug when updating name
        static::updating(function ($store) {
            if ($store->isDirty('name') && empty($store->slug)) {
                $store->slug = Str::slug($store->name);
                
                // Ensure slug is unique
                $originalSlug = $store->slug;
                $counter = 1;
                while (static::where('slug', $store->slug)->where('id', '!=', $store->id)->exists()) {
                    $store->slug = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }
        });
    }

    /**
     * Get the user that owns the store (legacy - kept for backward compatibility).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the users that are associated with the store.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_user')
            ->withPivot(['role', 'is_active', 'permissions', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Get the active users associated with the store.
     */
    public function activeUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_user')
            ->withPivot(['role', 'is_active', 'permissions', 'joined_at'])
            ->withTimestamps()
            ->wherePivot('is_active', true);
    }

    /**
     * Get store owners.
     */
    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_user')
            ->withPivot(['role', 'is_active', 'permissions', 'joined_at'])
            ->withTimestamps()
            ->wherePivot('role', 'owner')
            ->wherePivot('is_active', true);
    }

    /**
     * Get store administrators.
     */
    public function administrators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_user')
            ->withPivot(['role', 'is_active', 'permissions', 'joined_at'])
            ->withTimestamps()
            ->wherePivot('role', 'admin')
            ->wherePivot('is_active', true);
    }

    /**
     * Get store managers.
     */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_user')
            ->withPivot(['role', 'is_active', 'permissions', 'joined_at'])
            ->withTimestamps()
            ->wherePivot('role', 'manager')
            ->wherePivot('is_active', true);
    }

    /**
     * Get store staff.
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_user')
            ->withPivot(['role', 'is_active', 'permissions', 'joined_at'])
            ->withTimestamps()
            ->wherePivot('role', 'staff')
            ->wherePivot('is_active', true);
    }

    /**
     * Get the products for the store.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the active products for the store.
     */
    public function activeProducts(): HasMany
    {
        return $this->hasMany(Product::class)->where('status', 'active');
    }

    /**
     * Get the orders for the store.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(\App\Models\Order::class);
    }

    /**
     * Get the active orders for the store.
     */
    public function activeOrders(): HasMany
    {
        return $this->hasMany(\App\Models\Order::class)->whereNotIn('status', ['cancelled', 'refunded']);
    }

    /**
     * Get products count attribute.
     */
    public function getProductsCountAttribute(): int
    {
        return $this->products()->count();
    }

    /**
     * Scope to filter active stores.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter stores by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Check if user has access to this store.
     */
    public function hasUser(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Check if user has specific role in this store.
     */
    public function hasUserWithRole(User $user, string $role): bool
    {
        return $this->users()
            ->where('user_id', $user->id)
            ->wherePivot('role', $role)
            ->wherePivot('is_active', true)
            ->exists();
    }

    /**
     * Get user's role in this store.
     */
    public function getUserRole(User $user): ?string
    {
        $relationship = $this->users()
            ->where('user_id', $user->id)
            ->wherePivot('is_active', true)
            ->first();

        return $relationship ? $relationship->pivot->role : null;
    }

    /**
     * Add user to store with role.
     */
    public function addUser(User $user, string $role = 'staff', array $permissions = []): void
    {
        $this->users()->syncWithoutDetaching([
            $user->id => [
                'role' => $role,
                'is_active' => true,
                'permissions' => empty($permissions) ? null : json_encode($permissions),
                'joined_at' => now(),
            ]
        ]);
    }

    /**
     * Remove user from store.
     */
    public function removeUser(User $user): void
    {
        $this->users()->detach($user->id);
    }

    /**
     * Update user's role in store.
     */
    public function updateUserRole(User $user, string $role): void
    {
        $this->users()->updateExistingPivot($user->id, [
            'role' => $role,
            'updated_at' => now(),
        ]);
    }

    /**
     * Deactivate user in store.
     */
    public function deactivateUser(User $user): void
    {
        $this->users()->updateExistingPivot($user->id, [
            'is_active' => false,
            'updated_at' => now(),
        ]);
    }

    /**
     * Activate user in store.
     */
    public function activateUser(User $user): void
    {
        $this->users()->updateExistingPivot($user->id, [
            'is_active' => true,
            'updated_at' => now(),
        ]);
    }
}
