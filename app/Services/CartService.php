<?php
// app/Services/CartService.php

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CartService
{
    /**
     * Obtenir le contenu du panier
     */
    public function getCartItems(?User $user = null, ?string $sessionId = null): Collection
    {
        $query = Cart::with(['product.category'])
            ->where(function ($q) use ($user, $sessionId) {
                if ($user) {
                    $q->where('user_id', $user->id);
                }
                if ($sessionId) {
                    $q->orWhere('session_id', $sessionId);
                }
            });

        return $query->get();
    }

    /**
     * Ajouter un produit au panier
     */
    public function addToCart(Product $product, int $quantity, array $options = [], ?User $user = null, ?string $sessionId = null): Cart
    {
        // Vérifier la disponibilité du stock
        if ($product->track_inventory && !$product->allow_backorder) {
            if ($product->stock_quantity < $quantity) {
                throw new \Exception("Stock insuffisant. Disponible : {$product->stock_quantity}");
            }
        }

        // Chercher un article existant avec les mêmes options
        $existingCart = Cart::where('product_id', $product->id)
            ->where(function ($q) use ($user, $sessionId) {
                if ($user) {
                    $q->where('user_id', $user->id);
                } else {
                    $q->where('session_id', $sessionId);
                }
            })
            ->where('product_options', json_encode($options))
            ->first();

        if ($existingCart) {
            // Mettre à jour la quantité
            $newQuantity = $existingCart->quantity + $quantity;

            // Vérifier à nouveau le stock pour la nouvelle quantité
            if ($product->track_inventory && !$product->allow_backorder) {
                if ($product->stock_quantity < $newQuantity) {
                    throw new \Exception("Stock insuffisant pour cette quantité. Disponible : {$product->stock_quantity}");
                }
            }

            $existingCart->update(['quantity' => $newQuantity]);
            return $existingCart;
        }

        // Créer un nouvel article
        return Cart::create([
            'user_id' => $user?->id,
            'session_id' => $sessionId,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'product_options' => $options,
        ]);
    }

    /**
     * Mettre à jour la quantité d'un article
     */
    public function updateCartItem(Cart $cartItem, int $quantity): Cart
    {
        if ($quantity <= 0) {
            $cartItem->delete();
            return $cartItem;
        }

        $product = $cartItem->product;

        // Vérifier le stock
        if ($product->track_inventory && !$product->allow_backorder) {
            if ($product->stock_quantity < $quantity) {
                throw new \Exception("Stock insuffisant. Disponible : {$product->stock_quantity}");
            }
        }

        $cartItem->update(['quantity' => $quantity]);
        return $cartItem;
    }

    /**
     * Supprimer un article du panier
     */
    public function removeFromCart(Cart $cartItem): bool
    {
        return $cartItem->delete();
    }

    /**
     * Vider le panier
     */
    public function clearCart(?User $user = null, ?string $sessionId = null): int
    {
        $query = Cart::query();

        if ($user) {
            $query->where('user_id', $user->id);
        }

        if ($sessionId) {
            $query->where('session_id', $sessionId);
        }

        return $query->delete();
    }

    /**
     * Fusionner les paniers (session vers utilisateur)
     */
    public function mergeCart(User $user, string $sessionId): void
    {
        $sessionItems = Cart::where('session_id', $sessionId)->get();

        foreach ($sessionItems as $sessionItem) {
            // Chercher un article existant pour cet utilisateur
            $existingItem = Cart::where('user_id', $user->id)
                ->where('product_id', $sessionItem->product_id)
                ->where('product_options', $sessionItem->product_options)
                ->first();

            if ($existingItem) {
                // Additionner les quantités
                $existingItem->update([
                    'quantity' => $existingItem->quantity + $sessionItem->quantity
                ]);
                $sessionItem->delete();
            } else {
                // Transférer l'article à l'utilisateur
                $sessionItem->update([
                    'user_id' => $user->id,
                    'session_id' => null,
                ]);
            }
        }
    }

    /**
     * Calculer les totaux du panier
     */
    public function calculateCartTotals(Collection $cartItems): array
    {
        $subtotal = $cartItems->sum(function ($item) {
            return $item->quantity * $item->unit_price;
        });

        $taxAmount = $this->calculateTax($subtotal);
        $shippingAmount = $this->calculateShipping($cartItems);
        $total = $subtotal + $taxAmount + $shippingAmount;

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'shipping_amount' => $shippingAmount,
            'total' => $total,
            'items_count' => $cartItems->sum('quantity'),
            'currency' => 'XAF',
        ];
    }

    /**
     * Calculer les taxes (TVA de 18% au Gabon)
     */
    private function calculateTax(float $subtotal): float
    {
        $taxRate = 0.18; // 18% TVA
        return round($subtotal * $taxRate);
    }

    /**
     * Calculer les frais de livraison
     */
    private function calculateShipping(Collection $cartItems): float
    {
        // Frais de base
        $baseShipping = 2500; // 2,500 XAF

        // Livraison gratuite à partir de 50,000 XAF
        $freeShippingThreshold = 50000;

        $subtotal = $cartItems->sum(function ($item) {
            return $item->quantity * $item->unit_price;
        });

        if ($subtotal >= $freeShippingThreshold) {
            return 0;
        }

        // Frais supplémentaires pour les gros volumes
        $totalWeight = $cartItems->sum(function ($item) {
            return $item->quantity * ($item->product->weight ?? 0);
        });

        if ($totalWeight > 5) { // Plus de 5kg
            $baseShipping += 1000; // +1,000 XAF
        }

        return $baseShipping;
    }

    /**
     * Valider le panier avant commande
     */
    public function validateCart(Collection $cartItems): array
    {
        $errors = [];

        foreach ($cartItems as $item) {
            $product = $item->product;

            // Vérifier si le produit est toujours actif
            if ($product->status !== 'active') {
                $errors[] = "Le produit '{$product->name}' n'est plus disponible";
                continue;
            }

            // Vérifier le stock
            if ($product->track_inventory && !$product->allow_backorder) {
                if ($product->stock_quantity < $item->quantity) {
                    $errors[] = "Stock insuffisant pour '{$product->name}'. Disponible : {$product->stock_quantity}";
                }
            }

            // Vérifier si le prix a changé
            if ($item->unit_price !== $product->price) {
                $errors[] = "Le prix de '{$product->name}' a changé. Nouveau prix : " . number_format($product->price, 0, ',', ' ') . ' XAF';
            }
        }

        return $errors;
    }
}
