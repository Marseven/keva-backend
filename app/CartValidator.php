<?php

namespace App;

use App\Models\Product;
use Illuminate\Support\Collection;

class CartValidator
{
    /**
     * Validate that all items in a cart belong to the same store.
     */
    public static function validateSameStore(Collection $cartItems): array
    {
        $errors = [];
        $stores = [];

        foreach ($cartItems as $item) {
            $product = Product::find($item['product_id'] ?? $item->product_id);
            
            if (!$product) {
                $errors[] = "Produit non trouvé: {$item['product_id']}";
                continue;
            }

            if ($product->store_id) {
                $stores[] = $product->store_id;
            }
        }

        $uniqueStores = array_unique($stores);
        
        if (count($uniqueStores) > 1) {
            $errors[] = 'Tous les produits du panier doivent appartenir au même magasin.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'store_id' => count($uniqueStores) === 1 ? $uniqueStores[0] : null,
        ];
    }

    /**
     * Validate that a product can be added to existing cart items.
     */
    public static function canAddProductToCart(int $productId, Collection $existingCartItems): array
    {
        $product = Product::find($productId);
        
        if (!$product) {
            return [
                'valid' => false,
                'errors' => ['Produit non trouvé.']
            ];
        }

        if ($product->status !== 'active') {
            return [
                'valid' => false,
                'errors' => ['Ce produit n\'est pas disponible.']
            ];
        }

        // If cart is empty, product can be added
        if ($existingCartItems->isEmpty()) {
            return [
                'valid' => true,
                'errors' => []
            ];
        }

        // Check if product belongs to same store as existing items
        $existingStores = $existingCartItems->map(function ($item) {
            $product = Product::find($item['product_id'] ?? $item->product_id);
            return $product ? $product->store_id : null;
        })->filter()->unique()->values();

        // If no existing items have stores, new product can be added
        if ($existingStores->isEmpty()) {
            return [
                'valid' => true,
                'errors' => []
            ];
        }

        // If new product has no store, it can be added to any cart
        if (!$product->store_id) {
            return [
                'valid' => true,
                'errors' => []
            ];
        }

        // Check if new product belongs to same store as existing items
        if ($existingStores->count() === 1 && $existingStores->first() === $product->store_id) {
            return [
                'valid' => true,
                'errors' => []
            ];
        }

        return [
            'valid' => false,
            'errors' => ['Ce produit ne peut pas être ajouté car il appartient à un magasin différent des produits déjà dans le panier.']
        ];
    }

    /**
     * Get suggested actions when products from different stores are detected.
     */
    public static function getSuggestedActions(Collection $cartItems): array
    {
        $validation = static::validateSameStore($cartItems);
        
        if ($validation['valid']) {
            return [];
        }

        $productsByStore = [];
        
        foreach ($cartItems as $item) {
            $product = Product::find($item['product_id'] ?? $item->product_id);
            
            if (!$product) {
                continue;
            }

            $storeId = $product->store_id ?? 'no_store';
            
            if (!isset($productsByStore[$storeId])) {
                $productsByStore[$storeId] = [];
            }
            
            $productsByStore[$storeId][] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'store_name' => $product->store ? $product->store->name : 'Sans magasin',
                'quantity' => $item['quantity'] ?? $item->quantity,
            ];
        }

        return [
            'action' => 'split_into_separate_orders',
            'message' => 'Vous devez créer des commandes séparées pour chaque magasin.',
            'suggested_orders' => $productsByStore,
        ];
    }

    /**
     * Validate stock availability for cart items.
     */
    public static function validateStock(Collection $cartItems): array
    {
        $errors = [];
        
        foreach ($cartItems as $item) {
            $product = Product::find($item['product_id'] ?? $item->product_id);
            $quantity = $item['quantity'] ?? $item->quantity;
            
            if (!$product) {
                continue;
            }

            if ($product->track_inventory && !$product->allow_backorder) {
                if ($product->stock_quantity < $quantity) {
                    $errors[] = "Stock insuffisant pour '{$product->name}'. Disponible: {$product->stock_quantity}, Demandé: {$quantity}";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
