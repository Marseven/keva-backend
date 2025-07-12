<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * @OA\Schema(
 *     schema="Category",
 *     type="object",
 *     title="Catégorie de produit",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Électronique"),
 *     @OA\Property(property="slug", type="string", example="electronique"),
 *     @OA\Property(property="description", type="string", example="Tous les produits électroniques."),
 *     @OA\Property(property="image", type="string", example="categories/electronique.jpg"),
 *     @OA\Property(property="icon", type="string", example="fa-solid fa-tv"),
 *     @OA\Property(property="color", type="string", example="#FF5733"),
 *     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
 *     @OA\Property(property="sort_order", type="integer", example=1),
 *     @OA\Property(property="meta_title", type="string", example="Électronique - Boutique KEVA"),
 *     @OA\Property(property="meta_description", type="string", example="Découvrez notre sélection d'appareils électroniques."),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="is_featured", type="boolean", example=false),
 *     @OA\Property(property="image_url", type="string", readOnly=true, example="https://keva.test/storage/categories/electronique.jpg"),
 *     @OA\Property(
 *         property="breadcrumb",
 *         type="array",
 *         readOnly=true,
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="name", type="string", example="Électronique"),
 *             @OA\Property(property="slug", type="string", example="electronique")
 *         )
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-02T00:00:00Z")
 * )
 */
class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'icon',
        'color',
        'parent_id',
        'sort_order',
        'meta_title',
        'meta_description',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    // Relations
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // Scopes
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    public function scopeRootCategories(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    // Accessors
    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    public function getBreadcrumbAttribute(): array
    {
        $breadcrumb = [];
        $category = $this;

        while ($category) {
            array_unshift($breadcrumb, [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ]);
            $category = $category->parent;
        }

        return $breadcrumb;
    }

    // Méthodes utilitaires
    public function isParent(): bool
    {
        return $this->children()->exists();
    }

    public function isChild(): bool
    {
        return !is_null($this->parent_id);
    }

    public function getActiveProducts(): HasMany
    {
        return $this->products()->where('status', 'active');
    }

    public function getActiveProductsCount(): int
    {
        return $this->getActiveProducts()->count();
    }

    public function getAllDescendantIds(): array
    {
        $ids = [$this->id];

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }

        return $ids;
    }
}
