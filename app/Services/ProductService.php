<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductService
{
    private ImageUploadService $imageService;

    public function __construct(ImageUploadService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * Créer un nouveau produit
     */
    public function createProduct(User $user, array $data): Product
    {
        // Vérifier les limites du plan
        $this->checkPlanLimits($user);

        // Générer le SKU si absent
        if (empty($data['sku'])) {
            $data['sku'] = $this->generateSku();
        }

        // Générer le slug si absent
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['name']);
        }

        $data['user_id'] = $user->id;

        // Créer le produit
        $product = Product::create($data);

        // Gérer les images
        if (isset($data['featured_image']) && $data['featured_image'] instanceof UploadedFile) {
            $this->handleFeaturedImage($product, $data['featured_image']);
        }

        if (isset($data['gallery_images']) && is_array($data['gallery_images'])) {
            $this->handleGalleryImages($product, $data['gallery_images']);
        }

        return $product->fresh(['category', 'images']);
    }

    /**
     * Mettre à jour un produit
     */
    public function updateProduct(Product $product, array $data): Product
    {
        // Générer le slug si le nom a changé
        if (isset($data['name']) && $data['name'] !== $product->name) {
            $data['slug'] = $this->generateSlug($data['name'], $product->id);
        }

        // Gérer l'image principale
        if (isset($data['featured_image']) && $data['featured_image'] instanceof UploadedFile) {
            // Supprimer l'ancienne image
            if ($product->featured_image) {
                $this->imageService->deleteProductImage($product->featured_image);
            }
            $this->handleFeaturedImage($product, $data['featured_image']);
            unset($data['featured_image']);
        }

        // Gérer les nouvelles images de galerie
        if (isset($data['gallery_images']) && is_array($data['gallery_images'])) {
            $this->handleGalleryImages($product, $data['gallery_images']);
            unset($data['gallery_images']);
        }

        $product->update($data);

        return $product->fresh(['category', 'images']);
    }

    /**
     * Supprimer un produit
     */
    public function deleteProduct(Product $product): bool
    {
        // Supprimer toutes les images
        if ($product->featured_image) {
            $this->imageService->deleteProductImage($product->featured_image);
        }

        foreach ($product->images as $image) {
            $this->imageService->deleteProductImage($image->image_path);
        }

        return $product->delete();
    }

    /**
     * Dupliquer un produit
     */
    public function duplicateProduct(Product $originalProduct, User $user): Product
    {
        $this->checkPlanLimits($user);

        $data = $originalProduct->toArray();

        // Modifier les champs uniques
        $data['name'] = $data['name'] . ' (Copie)';
        $data['slug'] = $this->generateSlug($data['name']);
        $data['sku'] = $this->generateSku();
        $data['user_id'] = $user->id;

        // Retirer les champs non copiables
        unset($data['id'], $data['created_at'], $data['updated_at'], $data['sales_count'], $data['views_count']);

        $newProduct = Product::create($data);

        // Copier les images (optionnel - peut être coûteux)
        // TODO: Implémenter la copie d'images si nécessaire

        return $newProduct->fresh(['category', 'images']);
    }

    /**
     * Rechercher des produits
     */
    public function searchProducts(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $query = Product::query()->with(['category', 'user', 'store']);

        // Recherche textuelle
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereFullText(['name', 'description', 'short_description'], $search)
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Filtrer par utilisateur
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Filtrer par catégorie
        if (!empty($filters['category_id'])) {
            $category = Category::find($filters['category_id']);
            if ($category) {
                $categoryIds = $category->getAllDescendantIds();
                $query->whereIn('category_id', $categoryIds);
            }
        }

        // Filtrer par statut
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->published(); // Par défaut, seulement les produits publiés
        }

        // Filtrer par prix
        if (!empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }
        if (!empty($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        // Filtrer par stock
        if (!empty($filters['in_stock_only'])) {
            $query->inStock();
        }

        // Filtrer par condition
        if (!empty($filters['condition'])) {
            $query->where('condition', $filters['condition']);
        }

        // Produits en vedette
        if (!empty($filters['featured'])) {
            $query->featured();
        }

        // Tri
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        switch ($sortBy) {
            case 'name':
                $query->orderBy('name', $sortOrder);
                break;
            case 'price':
                $query->orderBy('price', $sortOrder);
                break;
            case 'popularity':
                $query->orderBy('sales_count', $sortOrder);
                break;
            case 'rating':
                $query->orderBy('average_rating', $sortOrder);
                break;
            default:
                $query->orderBy('created_at', $sortOrder);
        }

        return $query->paginate($perPage);
    }

    /**
     * Mettre à jour le stock d'un produit
     */
    public function updateStock(Product $product, int $quantity, string $operation = 'set'): Product
    {
        if (!$product->track_inventory) {
            return $product;
        }

        switch ($operation) {
            case 'add':
                $product->increment('stock_quantity', $quantity);
                break;
            case 'subtract':
                $newQuantity = max(0, $product->stock_quantity - $quantity);
                $product->update(['stock_quantity' => $newQuantity]);
                break;
            case 'set':
            default:
                $product->update(['stock_quantity' => max(0, $quantity)]);
        }

        return $product->fresh();
    }

    /**
     * Vérifier les limites du plan utilisateur
     */
    private function checkPlanLimits(User $user): void
    {
        if (!$user->canCreateProducts()) {
            $plan = $user->getCurrentPlan();
            throw new \Exception(
                "Limite de produits atteinte pour votre plan ({$plan->max_products} produits maximum). " .
                    "Veuillez upgrader votre abonnement."
            );
        }
    }

    /**
     * Générer un SKU unique
     */
    private function generateSku(): string
    {
        do {
            $sku = 'PRD-' . strtoupper(Str::random(6));
        } while (Product::where('sku', $sku)->exists());

        return $sku;
    }

    /**
     * Générer un slug unique
     */
    private function generateSlug(string $name, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        $query = Product::where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;

            $query = Product::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }

    /**
     * Gérer l'image principale
     */
    private function handleFeaturedImage(Product $product, UploadedFile $file): void
    {
        $upload = $this->imageService->uploadProductImage($file);
        $product->update(['featured_image' => $upload['sizes']['original']]);
    }

    /**
     * Gérer les images de galerie
     */
    private function handleGalleryImages(Product $product, array $files): void
    {
        foreach ($files as $index => $file) {
            if ($file instanceof UploadedFile) {
                $upload = $this->imageService->uploadProductImage($file);

                $product->images()->create([
                    'image_path' => $upload['sizes']['original'],
                    'alt_text' => $product->name . ' - Image ' . ($index + 1),
                    'sort_order' => $index,
                    'metadata' => $upload,
                ]);
            }
        }
    }
}
