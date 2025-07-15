<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="Utilisateur",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="first_name", type="string", example="Jean"),
 *     @OA\Property(property="last_name", type="string", example="Mabiala"),
 *     @OA\Property(property="email", type="string", format="email", example="jean@example.com"),
 *     @OA\Property(property="phone", type="string", example="+241123456789"),
 *     @OA\Property(property="whatsapp_number", type="string", example="+241123456789"),
 *     @OA\Property(property="business_name", type="string", example="Boutique Mabiala"),
 *     @OA\Property(property="business_type", type="string", example="Alimentation"),
 *     @OA\Property(property="city", type="string", example="Libreville"),
 *     @OA\Property(property="address", type="string", example="123 Rue de la Paix"),
 *     @OA\Property(property="is_admin", type="boolean", example=false),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z")
 * )
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'whatsapp_number',
        'business_name',
        'business_type',
        'city',
        'address',
        'selected_plan',
        'agree_to_terms',
        'is_admin',
        'is_active',
        'preferences',
        'avatar',
        'timezone',
        'language',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'agree_to_terms' => 'boolean',
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
        'preferences' => 'array',
        'password' => 'hashed',
    ];

    // Relations
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->where('status', 'active');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function activeStores(): HasMany
    {
        return $this->hasMany(Store::class)->where('is_active', true);
    }

    /**
     * Get the stores that the user is associated with (many-to-many).
     */
    public function managedStores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_user')
            ->withPivot(['role', 'is_active', 'permissions', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Get the active stores that the user is associated with.
     */
    public function activeManagedStores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_user')
            ->withPivot(['role', 'is_active', 'permissions', 'joined_at'])
            ->withTimestamps()
            ->wherePivot('is_active', true);
    }

    /**
     * Get stores where user is owner.
     */
    public function ownedStores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_user')
            ->withPivot(['role', 'is_active', 'permissions', 'joined_at'])
            ->withTimestamps()
            ->wherePivot('role', 'owner')
            ->wherePivot('is_active', true);
    }

    /**
     * Get stores where user is admin.
     */
    public function administeredStores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_user')
            ->withPivot(['role', 'is_active', 'permissions', 'joined_at'])
            ->withTimestamps()
            ->wherePivot('role', 'admin')
            ->wherePivot('is_active', true);
    }

    /**
     * Get stores where user is manager.
     */
    public function managedStoresAsManager(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_user')
            ->withPivot(['role', 'is_active', 'permissions', 'joined_at'])
            ->withTimestamps()
            ->wherePivot('role', 'manager')
            ->wherePivot('is_active', true);
    }

    /**
     * Get stores where user is staff.
     */
    public function staffStores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_user')
            ->withPivot(['role', 'is_active', 'permissions', 'joined_at'])
            ->withTimestamps()
            ->wherePivot('role', 'staff')
            ->wherePivot('is_active', true);
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar
            ? asset('storage/' . $this->avatar)
            : "https://ui-avatars.com/api/?name=" . urlencode($this->full_name) . "&background=3B82F6&color=fff";
    }

    // Méthodes utilitaires
    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }

    public function getCurrentPlan()
    {
        $subscription = $this->activeSubscription;
        return $subscription ? Plan::find($subscription->plan_id) : Plan::where('slug', 'basic')->first();
    }

    public function canCreateProducts(): bool
    {
        $plan = $this->getCurrentPlan();
        if (!$plan) return false;

        if ($plan->max_products == 0) return true; // Illimité

        return $this->products()->count() < $plan->max_products;
    }

    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    /**
     * Check if user has access to a specific store.
     */
    public function hasStoreAccess(Store $store): bool
    {
        return $this->managedStores()->where('store_id', $store->id)->exists();
    }

    /**
     * Check if user has specific role in a store.
     */
    public function hasRoleInStore(Store $store, string $role): bool
    {
        return $this->managedStores()
            ->where('store_id', $store->id)
            ->wherePivot('role', $role)
            ->wherePivot('is_active', true)
            ->exists();
    }

    /**
     * Get user's role in a specific store.
     */
    public function getRoleInStore(Store $store): ?string
    {
        $relationship = $this->managedStores()
            ->where('store_id', $store->id)
            ->wherePivot('is_active', true)
            ->first();

        return $relationship ? $relationship->pivot->role : null;
    }

    /**
     * Check if user can manage a store (owner, admin, or manager).
     */
    public function canManageStore(Store $store): bool
    {
        $role = $this->getRoleInStore($store);
        return in_array($role, ['owner', 'admin', 'manager']);
    }

    /**
     * Check if user is owner of a store.
     */
    public function isOwnerOfStore(Store $store): bool
    {
        return $this->hasRoleInStore($store, 'owner');
    }

    /**
     * Check if user is admin of a store.
     */
    public function isAdminOfStore(Store $store): bool
    {
        return $this->hasRoleInStore($store, 'admin');
    }

    /**
     * Check if user is manager of a store.
     */
    public function isManagerOfStore(Store $store): bool
    {
        return $this->hasRoleInStore($store, 'manager');
    }

    /**
     * Check if user is staff of a store.
     */
    public function isStaffOfStore(Store $store): bool
    {
        return $this->hasRoleInStore($store, 'staff');
    }
}
