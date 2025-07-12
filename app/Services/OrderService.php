<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Cart;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OrderService
{
    private CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Créer une commande à partir du panier
     */
    public function createOrderFromCart(User $user, array $data, Collection $cartItems): Order
    {
        // Valider le panier
        $errors = $this->cartService->validateCart($cartItems);
        if (!empty($errors)) {
            throw new \Exception('Erreurs dans le panier : ' . implode(', ', $errors));
        }

        // Calculer les totaux
        $totals = $this->cartService->calculateCartTotals($cartItems);

        // Créer la commande
        $order = Order::create([
            'order_number' => $this->generateOrderNumber(),
            'user_id' => $user->id,
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'shipping_amount' => $totals['shipping_amount'],
            'total_amount' => $totals['total'],
            'currency' => $totals['currency'],
            'status' => 'pending',
            'payment_status' => 'pending',
            'shipping_address' => $data['shipping_address'],
            'billing_address' => $data['billing_address'] ?? $data['shipping_address'],
            'shipping_method' => $data['shipping_method'] ?? 'standard',
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'notes' => $data['notes'] ?? null,
        ]);

        // Créer les articles de commande
        foreach ($cartItems as $cartItem) {
            $this->createOrderItem($order, $cartItem);
        }

        // Mettre à jour les stocks
        $this->updateProductStocks($cartItems);

        // Vider le panier
        $this->cartService->clearCart($user);

        return $order->fresh(['items.product', 'user']);
    }

    /**
     * Créer un article de commande
     */
    private function createOrderItem(Order $order, Cart $cartItem): OrderItem
    {
        $product = $cartItem->product;

        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => $cartItem->quantity,
            'unit_price' => $cartItem->unit_price,
            'total_price' => $cartItem->quantity * $cartItem->unit_price,
            'product_options' => $cartItem->product_options,
            'product_snapshot' => [
                'name' => $product->name,
                'description' => $product->short_description,
                'image' => $product->featured_image,
                'sku' => $product->sku,
                'category' => $product->category->name ?? null,
            ],
        ]);
    }

    /**
     * Mettre à jour les stocks des produits
     */
    private function updateProductStocks(Collection $cartItems): void
    {
        foreach ($cartItems as $cartItem) {
            $product = $cartItem->product;

            if ($product->track_inventory) {
                $product->decrementStock($cartItem->quantity);
                $product->incrementSales($cartItem->quantity);
            }
        }
    }

    /**
     * Générer un numéro de commande unique
     */
    private function generateOrderNumber(): string
    {
        $year = date('Y');
        $month = date('m');

        $lastOrder = Order::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastOrder ?
            ((int) substr($lastOrder->order_number, -4)) + 1 : 1;

        return "KEV-{$year}{$month}-" . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Mettre à jour le statut d'une commande
     */
    public function updateOrderStatus(Order $order, string $status, array $additionalData = []): Order
    {
        $allowedTransitions = $this->getAllowedStatusTransitions($order->status);

        if (!in_array($status, $allowedTransitions)) {
            throw new \Exception("Transition de statut non autorisée : {$order->status} -> {$status}");
        }

        $updateData = ['status' => $status];

        // Ajouter des données spécifiques selon le statut
        switch ($status) {
            case 'shipped':
                $updateData['shipped_at'] = now();
                if (isset($additionalData['tracking_number'])) {
                    $updateData['tracking_number'] = $additionalData['tracking_number'];
                }
                break;

            case 'delivered':
                $updateData['delivered_at'] = now();
                break;

            case 'cancelled':
                // Remettre en stock les produits
                $this->restoreProductStocks($order);
                break;
        }

        // Ajouter les notes admin si fournies
        if (isset($additionalData['admin_notes'])) {
            $updateData['admin_notes'] = $additionalData['admin_notes'];
        }

        $order->update($updateData);

        return $order->fresh();
    }

    /**
     * Obtenir les transitions de statut autorisées
     */
    private function getAllowedStatusTransitions(string $currentStatus): array
    {
        return match ($currentStatus) {
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['processing', 'cancelled'],
            'processing' => ['shipped', 'cancelled'],
            'shipped' => ['delivered'],
            'delivered' => [], // Statut final
            'cancelled' => [], // Statut final
            'refunded' => [], // Statut final
            default => [],
        };
    }

    /**
     * Remettre en stock les produits d'une commande annulée
     */
    private function restoreProductStocks(Order $order): void
    {
        foreach ($order->items as $item) {
            $product = $item->product;

            if ($product && $product->track_inventory) {
                $product->incrementStock($item->quantity);
            }
        }
    }

    /**
     * Calculer les statistiques de commandes pour un utilisateur
     */
    public function getUserOrderStats(User $user): array
    {
        $orders = $user->orders();

        return [
            'total_orders' => $orders->count(),
            'pending_orders' => $orders->where('status', 'pending')->count(),
            'completed_orders' => $orders->where('status', 'delivered')->count(),
            'cancelled_orders' => $orders->where('status', 'cancelled')->count(),
            'total_spent' => $orders->where('payment_status', 'paid')->sum('total_amount'),
            'average_order_value' => $orders->where('payment_status', 'paid')->avg('total_amount') ?? 0,
            'last_order_date' => $orders->latest()->first()?->created_at,
        ];
    }

    /**
     * Rechercher des commandes avec filtres
     */
    public function searchOrders(User $user, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Order::where('user_id', $user->id)
            ->with(['items.product'])
            ->latest();

        // Filtrer par statut
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filtrer par statut de paiement
        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        // Filtrer par période
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Recherche par numéro de commande
        if (!empty($filters['order_number'])) {
            $query->where('order_number', 'like', '%' . $filters['order_number'] . '%');
        }

        $perPage = min($filters['per_page'] ?? 10, 50);

        return $query->paginate($perPage);
    }
}
